<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class LocalCatalogSync
{
    public const TABLES = ['clientes', 'locales', 'paginas', 'enlaces_pagina'];

    protected array $snapshot = [];

    public function syncRecord(Model $record, ?Connection $source = null): void
    {
        $local = DB::connection('local');
        $source ??= DB::connection('supabase');
        $this->assertConnections($local, $source);
        $table = $record->getTable();
        if (! in_array($table, ['clientes', 'locales', 'paginas'], true)) {
            throw new RuntimeException('Se requiere una sincronización completa para este registro.');
        }
        $qualified = fn (string $name): string => $source->getQueryGrammar()->wrapTable($source->getConfig('search_path').'.'.$name);
        $conditions = [$table => 'id = ?'];
        if ($table === 'paginas') {
            $conditions['enlaces_pagina'] = 'pagina_id = ?';
            $conditions['locales'] = 'id = (SELECT local_id FROM '.$qualified('paginas').' WHERE id = ?)';
            $conditions['clientes'] = 'id = (SELECT cliente_id FROM '.$qualified('locales').' WHERE id = (SELECT local_id FROM '.$qualified('paginas').' WHERE id = ?))';
        } elseif ($table === 'locales') {
            $conditions['clientes'] = 'id = (SELECT cliente_id FROM '.$qualified('locales').' WHERE id = ?)';
        }

        $completed = app(CatalogSyncState::class)->cache()->lock('catalog-sync-lock', 180)->get(function () use ($local, $source, $conditions, $qualified, $record, $table): bool {
            $parts = [];
            foreach ($conditions as $name => $condition) {
                $parts[] = "'{$name}', (SELECT COALESCE(jsonb_agg(to_jsonb(t)), '[]'::jsonb) FROM ".$qualified($name).' t WHERE '.$condition.')';
            }
            $result = $source->selectOne('SELECT jsonb_build_object('.implode(', ', $parts).') AS snapshot', array_fill(0, count($parts), $record->getKey()), false);
            $snapshot = json_decode($result->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $local->transaction(function () use ($local, $snapshot, $record, $table): void {
                if (! $local->selectOne('SELECT pg_try_advisory_xact_lock(20261013, 1) AS acquired')->acquired) {
                    throw new RuntimeException('La copia local está ocupada.');
                }
                if ($table === 'paginas') {
                    $local->table('enlaces_pagina')->where('pagina_id', $record->getKey())->delete();
                }
                foreach (self::TABLES as $name) {
                    foreach ($snapshot[$name] ?? [] as $row) {
                        foreach ($row as &$value) {
                            if (is_array($value)) {
                                $value = json_encode($value, JSON_THROW_ON_ERROR);
                            }
                        }
                        unset($value);
                        $local->table($name)->upsert([$row], ['id']);
                    }
                }
                if (($snapshot[$table] ?? []) === []) {
                    $local->table($table)->where('id', $record->getKey())->delete();
                }
            });

            return true;
        });
        if (! $completed) {
            throw new RuntimeException('La sincronización está ocupada.');
        }
    }

    /** @return array<string, int> */
    public function sync(?Connection $source = null): array
    {
        $local = DB::connection('local');
        $source ??= DB::connection('supabase');
        $this->assertConnections($local, $source);
        $state = app(CatalogSyncState::class);
        $lock = $state->cache()->lock('catalog-sync-lock', 180);

        return $lock->block(30, function () use ($local, $source, $state): array {
            $state->update(['running_since' => now()->timestamp, 'error' => null]);
            try {
                $counts = $local->transaction(function () use ($local, $source): array {
                    $lock = $local->selectOne('SELECT pg_try_advisory_xact_lock(20261013, 1) AS acquired');
                    if (! $lock->acquired) {
                        throw new RuntimeException('Ya hay una sincronización local en curso.');
                    }
                    $exists = $local->selectOne("SELECT EXISTS (SELECT 1 FROM pg_namespace WHERE nspname = 'clicktap_cache') AS present");
                    if ($exists->present && ! $local->getSchemaBuilder()->hasTable('sync_state')) {
                        throw new RuntimeException('El esquema local ya existe sin identificador de caché.');
                    }
                    $local->statement('CREATE SCHEMA IF NOT EXISTS clicktap_cache');
                    $local->statement('CREATE TABLE IF NOT EXISTS clicktap_cache.sync_state (id integer PRIMARY KEY CHECK (id = 1), completed_at timestamp with time zone NOT NULL, counts jsonb NOT NULL)');

                    $this->snapshot = $this->fetchSnapshot($source);
                    $counts = [];
                    foreach (self::TABLES as $table) {
                        $counts[$table] = $this->copyTable($source, $local, $table);
                    }
                    // Las tablas vigentes solo se bloquean durante el intercambio final.
                    foreach (self::TABLES as $table) {
                        $local->statement('DROP TABLE IF EXISTS clicktap_cache.'.$table);
                        $local->statement('ALTER TABLE clicktap_cache.next_'.$table.' RENAME TO '.$table);
                    }
                    $local->table('sync_state')->updateOrInsert(['id' => 1], [
                        'completed_at' => now(), 'counts' => json_encode($counts, JSON_THROW_ON_ERROR),
                    ]);

                    return $counts;
                });
                $state->update(['running_since' => null, 'completed_at' => now()->timestamp, 'error' => null]);

                return $counts;
            } catch (Throwable $exception) {
                $state->update(['running_since' => null, 'error' => 'No se pudo actualizar la copia local. Se conserva la última sincronización.']);
                throw $exception;
            } finally {
                $this->snapshot = [];
            }
        });
    }

    /** @return array<string, array{columns: array, rows: array}> */
    private function fetchSnapshot(Connection $source): array
    {
        $parts = [];
        $bindings = [];
        foreach (self::TABLES as $table) {
            $qualified = $source->getQueryGrammar()->wrapTable($source->getConfig('search_path').'.'.$table);
            $parts[] = "'{$table}', jsonb_build_object('columns', (SELECT jsonb_agg(jsonb_build_object('name', attname, 'type', format_type(atttypid, atttypmod)) ORDER BY attnum) FROM pg_attribute WHERE attrelid = ?::regclass AND attnum > 0 AND NOT attisdropped), 'rows', (SELECT COALESCE(jsonb_agg(to_jsonb(t)), '[]'::jsonb) FROM {$qualified} t))";
            $bindings[] = $qualified;
        }
        // Un único SELECT ofrece una instantánea coherente de las cuatro tablas.
        $row = $source->selectOne('SELECT jsonb_build_object('.implode(', ', $parts).') AS snapshot', $bindings, false);

        return json_decode($row->snapshot, true, 512, JSON_THROW_ON_ERROR);
    }

    private function assertConnections(Connection $local, Connection $source): void
    {
        if ($local->getDriverName() !== 'pgsql' || $source->getDriverName() !== 'pgsql'
            || $local->getConfig('search_path') !== 'clicktap_cache'
            || ! in_array($local->getConfig('host'), ['clicktap_db', 'db', 'localhost', '127.0.0.1', '::1'], true)
            || $source->getConfig('search_path') === 'clicktap_cache') {
            throw new RuntimeException('Se requiere PostgreSQL local con esquema exclusivo clicktap_cache y un origen separado.');
        }
    }

    protected function copyTable(Connection $source, Connection $local, string $table): int
    {
        $columns = $this->snapshot[$table]['columns'];
        if ($columns === []) {
            throw new RuntimeException('Falta una tabla requerida en el origen.');
        }
        $grammar = $local->getQueryGrammar();
        $definitions = [];
        $jsonColumns = [];
        foreach ($columns as $column) {
            $type = $column['type'];
            if (! preg_match('/\A(?:uuid|text|boolean|smallint|integer|bigint|real|double precision|jsonb?|date|character varying(?:\(\d+\))?|character\(\d+\)|numeric(?:\(\d+(?:,\s*\d+)?\))?|timestamp(?:\(\d+\))? (?:with|without) time zone)\z/', $type)) {
                throw new RuntimeException('Tipo de columna no soportado por la copia local.');
            }
            $definitions[] = $grammar->wrap($column['name']).' '.$type.($column['name'] === 'id' ? ' PRIMARY KEY' : '');
            if (in_array($type, ['json', 'jsonb'], true)) {
                $jsonColumns[] = $column['name'];
            }
        }
        $staging = 'clicktap_cache.next_'.$table;
        $qualified = $grammar->wrapTable($staging);
        $local->statement('CREATE TABLE '.$qualified.' ('.implode(', ', $definitions).')');
        foreach (array_chunk($this->snapshot[$table]['rows'], 250) as $rows) {
            foreach ($rows as &$row) {
                foreach ($jsonColumns as $column) {
                    if ($row[$column] !== null) {
                        $row[$column] = json_encode($row[$column], JSON_THROW_ON_ERROR);
                    }
                }
            }
            unset($row);
            $local->table($staging)->insert($rows);
        }
        foreach (['cliente_id', 'local_id', 'pagina_id', 'slug'] as $indexed) {
            if (in_array($indexed, array_column($columns, 'name'), true)) {
                $local->statement('CREATE INDEX ON '.$qualified.' ('.$grammar->wrap($indexed).')');
            }
        }

        return count($this->snapshot[$table]['rows']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\AlbumPagina;
use App\Support\LocalCatalogSync;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class LocalCatalogSyncTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CLICKTAP_SYNC_TEST_DATABASE') !== 'clicktap_test') {
            $this->markTestSkipped('Requiere PostgreSQL aislado: CLICKTAP_SYNC_TEST_DATABASE=clicktap_test.');
        }

        $config = config('database.connections.local');
        $config['database'] = 'clicktap_test';
        $config['search_path'] = 'clicktap_cache';
        config(['database.connections.local' => $config]);
        config(['database.connections.supabase' => array_merge($config, ['search_path' => 'sync_test_source'])]);
        DB::purge('local');
        DB::purge('supabase');
        $local = DB::connection('local');
        $local->statement('DROP SCHEMA IF EXISTS clicktap_cache CASCADE');
        $local->statement('DROP SCHEMA IF EXISTS sync_test_source CASCADE');
        $local->statement('CREATE SCHEMA sync_test_source');
        foreach (LocalCatalogSync::TABLES as $table) {
            DB::connection('supabase')->statement('CREATE TABLE '.$table.' (id uuid PRIMARY KEY, nombre text, activo boolean, contenido_extra jsonb, updated_at timestamp without time zone)');
            DB::connection('supabase')->table($table)->insert([
                'id' => '00000000-0000-0000-0000-000000000001',
                'nombre' => 'Original', 'activo' => true,
                'contenido_extra' => '{"texto":"Prueba ñ"}', 'updated_at' => '2026-10-13 10:00:00',
            ]);
        }
        DB::connection('supabase')->statement('CREATE TABLE users (password text)');
    }

    protected function tearDown(): void
    {
        if (getenv('CLICKTAP_SYNC_TEST_DATABASE') === 'clicktap_test') {
            DB::connection('local')->statement('DROP SCHEMA IF EXISTS clicktap_cache CASCADE');
            DB::connection('local')->statement('DROP SCHEMA IF EXISTS sync_test_source CASCADE');
            DB::purge('local');
            DB::purge('supabase');
        }
        parent::tearDown();
    }

    public function test_full_copy_preserves_types_and_excludes_credentials(): void
    {
        $this->artisan('clicktap:sync-local')->assertSuccessful();
        $local = DB::connection('local');
        foreach (LocalCatalogSync::TABLES as $table) {
            $row = $local->table($table)->first();
            $this->assertSame('Original', $row->nombre);
            $this->assertTrue($row->activo);
            $this->assertSame(['texto' => 'Prueba ñ'], json_decode($row->contenido_extra, true));
        }
        $this->assertFalse($local->getSchemaBuilder()->hasTable('users'));
        $this->assertNotNull($local->table('sync_state')->value('completed_at'));
    }

    public function test_repeat_sync_reconciles_updates_and_deletions(): void
    {
        $sync = new LocalCatalogSync;
        $sync->sync();
        DB::connection('supabase')->table('clientes')->update(['nombre' => 'Cambio']);
        DB::connection('supabase')->table('enlaces_pagina')->delete();
        $counts = $sync->sync();
        $this->assertSame('Cambio', DB::connection('local')->table('clientes')->value('nombre'));
        $this->assertSame(0, $counts['enlaces_pagina']);
        $this->assertSame(0, DB::connection('local')->table('enlaces_pagina')->count());
        $this->assertSame('Cambio', DB::connection('supabase')->table('clientes')->value('nombre'));
    }

    public function test_album_partial_sync_reconciles_assignment_and_deletion_without_deleting_pages(): void
    {
        $remote = DB::connection('supabase');
        $remote->statement('DROP TABLE album_pagina');
        $remote->statement('DROP TABLE albumes_paginas');
        $remote->statement('CREATE TABLE albumes_paginas (id uuid PRIMARY KEY, nombre text, sector text, fecha_ruta date, created_at timestamp without time zone, updated_at timestamp without time zone)');
        $remote->statement('CREATE TABLE album_pagina (album_id uuid, pagina_id uuid, PRIMARY KEY (album_id, pagina_id))');
        $albumId = '00000000-0000-0000-0000-000000000002';
        $pageId = '00000000-0000-0000-0000-000000000001';
        $remote->table('albumes_paginas')->insert(['id' => $albumId, 'nombre' => 'Ruta centro']);
        $remote->table('album_pagina')->insert(['album_id' => $albumId, 'pagina_id' => $pageId]);

        $sync = new LocalCatalogSync;
        $sync->sync();
        $this->assertSame(1, DB::connection('local')->table('album_pagina')->count());

        $remote->table('albumes_paginas')->where('id', $albumId)->update(['nombre' => 'Ruta renovada']);
        $remote->table('album_pagina')->where('album_id', $albumId)->delete();
        $album = new AlbumPagina;
        $album->id = $albumId;
        $sync->syncRecord($album, $remote);
        $this->assertSame('Ruta renovada', DB::connection('local')->table('albumes_paginas')->value('nombre'));
        $this->assertSame(0, DB::connection('local')->table('album_pagina')->count());

        $remote->table('albumes_paginas')->where('id', $albumId)->delete();
        $sync->syncRecord($album, $remote);
        $this->assertSame(0, DB::connection('local')->table('albumes_paginas')->count());
        $this->assertSame(1, DB::connection('local')->table('paginas')->count());
    }

    public function test_interrupted_copy_rolls_back_all_tables_and_success_time(): void
    {
        (new LocalCatalogSync)->sync();
        $previous = DB::connection('local')->table('sync_state')->first();
        DB::connection('supabase')->table('clientes')->update(['nombre' => 'Cambio']);
        $sync = new class extends LocalCatalogSync
        {
            protected function copyTable(Connection $source, Connection $local, string $table): int
            {
                $count = parent::copyTable($source, $local, $table);
                if ($table === 'paginas') {
                    throw new RuntimeException('Fallo simulado durante la copia');
                }

                return $count;
            }
        };
        try {
            $sync->sync();
            $this->fail('La sincronización debe fallar.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Fallo simulado durante la copia', $exception->getMessage());
        }
        $this->assertSame('Original', DB::connection('local')->table('clientes')->value('nombre'));
        $this->assertEquals($previous, DB::connection('local')->table('sync_state')->first());
    }

    public function test_missing_source_table_keeps_previous_copy(): void
    {
        (new LocalCatalogSync)->sync();
        DB::connection('supabase')->statement('DROP TABLE enlaces_pagina');
        $this->artisan('clicktap:sync-local')->assertFailed();
        $this->assertSame(1, DB::connection('local')->table('enlaces_pagina')->count());
    }

    public function test_refuses_an_unidentified_existing_schema(): void
    {
        DB::connection('local')->statement('CREATE SCHEMA clicktap_cache');
        DB::connection('local')->statement('CREATE TABLE clicktap_cache.clientes (id integer)');
        DB::connection('local')->table('clientes')->insert(['id' => 42]);
        $this->artisan('clicktap:sync-local')->assertFailed();
        $this->assertSame(42, DB::connection('local')->table('clientes')->value('id'));
    }

    public function test_concurrent_sync_is_rejected_without_changing_the_copy(): void
    {
        (new LocalCatalogSync)->sync();
        config(['database.connections.sync_lock_test' => config('database.connections.local')]);
        $other = DB::connection('sync_lock_test');
        $other->beginTransaction();
        try {
            $other->select('SELECT pg_advisory_xact_lock(20261013, 1)');
            $this->artisan('clicktap:sync-local')->assertFailed();
            $this->assertSame(1, DB::connection('local')->table('clientes')->count());
        } finally {
            $other->rollBack();
            DB::purge('sync_lock_test');
        }
    }
}

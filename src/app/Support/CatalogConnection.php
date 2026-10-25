<?php

namespace App\Support;

use Closure;
use Filament\Notifications\Notification;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Throwable;

class CatalogConnection
{
    public static function enabled(): bool
    {
        return (bool) config('clicktap.local_catalog', false);
    }

    public static function name(): string
    {
        return self::enabled() ? 'catalog' : config('database.default');
    }

    public static function connection(): Connection
    {
        return DB::connection(self::name());
    }

    public static function configure(): void
    {
        $local = config('database.connections.local');
        $remote = DB::connection('supabase')->getConfig();
        unset($remote['url'], $remote['name']);
        config(['database.connections.catalog' => array_merge($remote, [
            'read' => $local,
            'write' => $remote,
            'sticky' => true,
        ])]);
        DB::connection('catalog')->setReadPdo(function () {
            try {
                $local = DB::connection('local');
                if ($local->getSchemaBuilder()->hasTable('sync_state') && $local->table('sync_state')->exists()) {
                    return $local->getPdo();
                }
            } catch (Throwable) {
                app(CatalogSyncState::class)->update(['error' => 'La copia local no está disponible. Se está consultando Supabase.']);
            }

            return DB::connection('catalog')->getPdo();
        });
    }

    public static function transaction(Closure $callback): mixed
    {
        $result = self::connection()->transaction($callback);
        if (self::connection()->transactionLevel() === 0) {
            self::refreshAfterWrite($result instanceof Model ? $result : null);
        }

        return $result;
    }

    public static function remoteReads(Closure $callback): mixed
    {
        self::connection()->useWriteConnectionWhenReading();
        try {
            return $callback();
        } finally {
            self::connection()->useWriteConnectionWhenReading(false);
        }
    }

    public static function refreshAfterWrite(?Model $record = null): void
    {
        if (! self::enabled()) {
            return;
        }
        if ($record === null) {
            app(CatalogSyncState::class)->request();

            return;
        }
        try {
            app(LocalCatalogSync::class)->syncRecord($record, self::connection());
        } catch (Throwable) {
            app(CatalogSyncState::class)->request();
            if (! app()->runningInConsole() || app()->runningUnitTests()) {
                Notification::make()->warning()->persistent()
                    ->title('Guardado en Supabase')
                    ->body('La copia local está pendiente de actualizar. No repitas el guardado; pulsa Sincronizar ahora para reintentar.')
                    ->send();
            }
        }
    }
}

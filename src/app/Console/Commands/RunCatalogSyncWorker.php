<?php

namespace App\Console\Commands;

use App\Support\CatalogSyncState;
use App\Support\LocalCatalogSync;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class RunCatalogSyncWorker extends Command
{
    protected $signature = 'clicktap:sync-worker {--once : Procesar un ciclo y salir}';

    protected $description = 'Sincroniza al iniciar, cada 20 minutos y cuando se solicita desde el panel';

    public function handle(CatalogSyncState $state, LocalCatalogSync $sync): int
    {
        $next = 0;
        do {
            $state->cache()->put('catalog-sync-worker-heartbeat', time(), 15);
            if (time() >= $next || $state->requested()) {
                $state->cache()->forget('catalog-sync-requested');
                try {
                    $sync->sync();
                    $this->info('Copia local actualizada.');
                } catch (Throwable) {
                    $this->warn('Sin conexión o sincronización ocupada. Se conserva la copia anterior.');
                } finally {
                    DB::disconnect('supabase');
                    $next = time() + (int) config('clicktap.sync_interval_seconds', 1200);
                }
            }
            if ($this->option('once')) {
                break;
            }
            sleep(2);
        } while (true);

        return self::SUCCESS;
    }
}

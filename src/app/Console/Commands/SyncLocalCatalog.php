<?php

namespace App\Console\Commands;

use App\Support\LocalCatalogSync;
use Illuminate\Console\Command;
use Throwable;

class SyncLocalCatalog extends Command
{
    protected $signature = 'clicktap:sync-local';

    protected $description = 'Reconstruye la copia local de clientes, locales, páginas y enlaces desde Supabase';

    public function handle(LocalCatalogSync $sync): int
    {
        try {
            $counts = $sync->sync();
            foreach ($counts as $table => $count) {
                $this->line($table.': '.$count);
            }
            $this->info('Copia local sincronizada.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('No se pudo sincronizar. La copia anterior se conserva. Revisa conexiones, tablas y si hay otra sincronización en curso.');

            return self::FAILURE;
        }
    }
}

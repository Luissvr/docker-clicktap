<?php

namespace App\Console\Commands;

use App\Support\PublicationQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clicktap:publication-worker {--once : Procesar una solicitud y salir}')]
#[Description('Publica el sitio sin bloquear las peticiones del panel')]
class RunPublicationWorker extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PublicationQueue $queue): int
    {
        do {
            try {
                $queue->work();
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('No se pudo procesar la solicitud. Se conserva su estado para revisión.');
            } finally {
                DB::disconnect('supabase');
                DB::disconnect('catalog');
            }
            if ($this->option('once')) {
                break;
            }
            sleep(2);
        } while (true);

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Support\Research\BusinessResearchQueue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('clicktap:research-worker {--once : Procesar una tarea y salir}')]
#[Description('Investiga negocios en segundo plano sin bloquear el panel')]
class RunResearchWorker extends Command
{
    public function handle(BusinessResearchQueue $queue): int
    {
        do {
            try {
                $worked = $queue->work();
            } catch (\Throwable $exception) {
                report($exception);
                $this->warn('No se pudo procesar la tarea. Se conserva su estado para revisión.');
                $worked = false;
            } finally {
                DB::disconnect('supabase');
                DB::disconnect('catalog');
            }
            if ($this->option('once')) {
                break;
            }
            if (! $worked) {
                sleep(2);
            }
        } while (true);

        return self::SUCCESS;
    }
}

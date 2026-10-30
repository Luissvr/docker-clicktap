<?php

namespace App\Console\Commands;

use App\Models\Pagina;
use App\Support\PageTrash;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('clicktap:prune-page-trash')]
#[Description('Elimina páginas tras 30 días en papelera, cuando la retirada pública está comprobada')]
class PrunePageTrash extends Command
{
    public function handle(PageTrash $trash): int
    {
        if (! $trash->available()) {
            $this->warn('La papelera todavía no está disponible en el catálogo.');

            return self::SUCCESS;
        }

        $deleted = 0;
        $held = 0;
        Pagina::query()->useWritePdo()->whereNotNull('papelera_en')
            ->where('papelera_en', '<=', now()->subDays(30))
            ->chunkById(100, function ($pages) use ($trash, &$deleted, &$held): void {
                foreach ($pages as $page) {
                    if (! $trash->canLeaveTrash($page)) {
                        $held++;

                        continue;
                    }
                    try {
                        $trash->purge($page->id);
                        $deleted++;
                    } catch (Throwable $exception) {
                        report($exception);
                        $held++;
                    }
                }
            });
        $this->info("Páginas eliminadas: {$deleted}. Pendientes de retirada o revisión: {$held}.");

        return self::SUCCESS;
    }
}

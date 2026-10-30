<?php

namespace App\Actions;

use App\Models\Pagina;
use App\Models\User;
use App\Support\BusinessLedger;
use App\Support\CatalogConnection;
use App\Support\PublicationQueue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RejectSaleAction
{
    public function handle(User $actor, string $saleId, string $reason): ?Pagina
    {
        abort_unless($actor->activo, 403);
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['rejectionReason' => 'Indica un motivo de 3 a 500 caracteres.']);
        }

        $database = app(BusinessLedger::class)->connection();
        $pageId = $database->transaction(function () use ($database, $actor, $saleId, $reason): ?string {
            $sale = $database->table('ventas')->where('id', $saleId)->lockForUpdate()->first();
            abort_unless($sale, 404);
            abort_unless(in_array($sale->estado, ['borrador', 'confirmada', 'rechazada', 'cancelacion_pendiente'], true), 409);

            if (! in_array($sale->estado, ['rechazada', 'cancelacion_pendiente'], true)) {
                $state = $sale->estado === 'confirmada' ? 'cancelacion_pendiente' : 'rechazada';
                $database->table('ventas')->where('id', $saleId)->update([
                    'estado' => $state,
                    'updated_at' => now(),
                ]);
                $database->table('eventos_auditoria')->insert([
                    'id' => (string) Str::uuid(),
                    'administrador_id' => $actor->getKey(),
                    'entidad_tipo' => 'venta',
                    'entidad_id' => $saleId,
                    'accion' => 'cliente_no_continua',
                    'ocurrido_en' => now(),
                    'resumen' => $state === 'rechazada' ? 'Venta rechazada' : 'Cancelación comercial pendiente',
                    'motivo' => $reason,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($sale->pagina_id) {
                $page = $database->table('paginas')->where('id', $sale->pagina_id)->lockForUpdate()->first();
                abort_unless($page, 409, 'La página asociada a la venta ya no existe.');
                if ($page->activo || ! in_array($page->publicacion_estado, ['retirada_pendiente', 'retirando', 'retirada_enviada', 'retirada'], true)) {
                    $database->table('paginas')->where('id', $sale->pagina_id)->update([
                        'activo' => false,
                        'publicacion_estado' => 'retirada_pendiente',
                        'actualizada_por' => $actor->getKey(),
                        'revision' => $database->raw('revision + 1'),
                        'updated_at' => now(),
                    ]);
                }
            }

            return $sale->pagina_id;
        }, 3);

        if (! $pageId) {
            return null;
        }

        $page = CatalogConnection::remoteReads(fn (): Pagina => Pagina::findOrFail($pageId));
        CatalogConnection::refreshAfterWrite($page);
        if ($page->publicacion_estado !== 'retirada') {
            app(PublicationQueue::class)->requestWithdrawal($page);
        }

        return $page;
    }
}

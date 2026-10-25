<?php

namespace App\Support;

use Filament\Notifications\Notification;

trait UsesCatalogTransactions
{
    protected function performCatalogOperation(\Closure $callback): void
    {
        try {
            $callback();
        } catch (\PDOException $exception) {
            if (! CatalogConnection::enabled()) {
                throw $exception;
            }
            Notification::make()->danger()->persistent()
                ->title('No se pudo confirmar el guardado')
                ->body('Tus cambios siguen en el formulario. Revisa la conexión y sincroniza antes de reintentar para comprobar si Supabase recibió el cambio.')
                ->send();
        }
    }

    protected function beginDatabaseTransaction(): void
    {
        CatalogConnection::connection()->beginTransaction();
    }

    protected function commitDatabaseTransaction(): void
    {
        CatalogConnection::connection()->commit();
        if (CatalogConnection::connection()->transactionLevel() === 0) {
            CatalogConnection::refreshAfterWrite($this->getRecord());
        }
    }

    protected function rollBackDatabaseTransaction(): void
    {
        CatalogConnection::connection()->rollBack();
    }
}

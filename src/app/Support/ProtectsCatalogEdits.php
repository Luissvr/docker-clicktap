<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ProtectsCatalogEdits
{
    use UsesCatalogTransactions;

    #[Locked]
    public int $loadedRevision = 0;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        $this->performCatalogOperation(fn () => parent::save($shouldRedirect, $shouldSendSavedNotification));
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->loadedRevision = (int) ($data['revision'] ?? 1);

        return $data;
    }

    protected function beforeSave(): void
    {
        $current = $this->getRecord()->newQuery()
            ->whereKey($this->getRecord()->getKey())->lockForUpdate()->first();
        if (! $current || (int) $current->revision !== $this->loadedRevision) {
            $field = match ($this->getRecord()->getTable()) {
                'clientes' => 'nombre',
                'locales' => 'nombre_interno',
                default => 'nombre_publico',
            };
            throw ValidationException::withMessages([
                'data.'.$field => 'Otro socio modificó o eliminó este registro. Copia tus cambios, sincroniza y abre de nuevo la vista antes de guardar.',
            ]);
        }
        // También cambia la revisión cuando solo se editan los enlaces relacionados.
        $this->getRecord()->revision = $this->loadedRevision + 1;
    }

    protected function afterSave(): void
    {
        $this->loadedRevision = (int) $this->getRecord()->refresh()->revision;
    }
}

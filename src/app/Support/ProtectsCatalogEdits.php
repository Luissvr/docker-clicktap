<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ProtectsCatalogEdits
{
    #[Locked]
    public int $loadedRevision = 0;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->loadedRevision = (int) ($data['revision'] ?? 1);

        return $data;
    }

    protected function beforeSave(): void
    {
        $current = $this->getRecord()->newQuery()
            ->whereKey($this->getRecord()->getKey())->lockForUpdate()->firstOrFail();
        if ((int) $current->revision !== $this->loadedRevision) {
            $field = match ($current->getTable()) {
                'clientes' => 'nombre',
                'locales' => 'nombre_interno',
                default => 'nombre_publico',
            };
            throw ValidationException::withMessages([
                'data.'.$field => 'Otro socio modificó este registro. Copia tus cambios y recarga antes de guardar.',
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

<?php

namespace App\Filament\Resources\PaginaResource\Pages\Concerns;

use App\Support\LinkTypeHelper;
use Illuminate\Support\Str;

trait OrdersPageLinks
{
    public bool $isOrderingLinks = false;

    public ?string $expandedLinkKey = null;

    /** @return array<string, string> */
    public function linkOrderOptions(): array
    {
        $options = [];

        foreach ($this->data['enlaces'] ?? [] as $key => $link) {
            $name = trim((string) ($link['etiqueta'] ?? ''));
            $options[$key] = (count($options) + 1).'. '.($name !== '' ? $name : LinkTypeHelper::label($link['tipo'] ?? null));
        }

        return $options;
    }

    public function toggleLinkOrdering(): void
    {
        $this->isOrderingLinks = ! $this->isOrderingLinks;
    }

    public function addLinkOfType(string $type): ?string
    {
        if (! array_key_exists($type, LinkTypeHelper::options())) {
            return null;
        }

        $this->expandedLinkKey = (string) Str::uuid();
        $this->data['enlaces'][$this->expandedLinkKey] = [
            'tipo' => $type,
            'etiqueta' => match ($type) {
                'review' => 'Déjanos una reseña',
                'transferencia' => 'Copiar datos de transferencia',
                default => LinkTypeHelper::label($type),
            },
            'banco_transferencia' => $type === 'transferencia' ? 'bancoestado' : 'none',
            'place_id' => $type === 'review' ? ($this->data['contenido_extra']['place_id'] ?? null) : null,
            'archivo_path' => null,
            'url' => $type === 'review' ? LinkTypeHelper::reviewUrlFromPlaceId($this->data['contenido_extra']['place_id'] ?? null) : null,
            'copiar_texto' => $type === 'transferencia',
            'activo' => true,
        ];

        return $this->expandedLinkKey;
    }

    public function moveSelectedLink(string $selectedKey, int $direction): void
    {
        if (! $this->isOrderingLinks || ! in_array($direction, [-1, 1], true)) {
            return;
        }

        $links = $this->data['enlaces'] ?? [];
        $keys = array_keys($links);
        $index = array_search($selectedKey, $keys, true);

        if ($index === false || ! isset($keys[$index + $direction])) {
            return;
        }

        array_splice($keys, $index, 1);
        array_splice($keys, $index + $direction, 0, [$selectedKey]);

        $orderedLinks = [];

        foreach ($keys as $key) {
            $orderedLinks[$key] = $links[$key];
        }

        $this->data['enlaces'] = $orderedLinks;
    }

    /** @param array<int, string> $selectedKeys */
    public function deleteSelectedLinks(array $selectedKeys): void
    {
        $links = $this->data['enlaces'] ?? [];

        foreach ($selectedKeys as $key) {
            unset($links[$key]);
        }

        $this->data['enlaces'] = $links;
    }
}

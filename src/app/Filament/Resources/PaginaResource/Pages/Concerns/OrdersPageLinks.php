<?php

namespace App\Filament\Resources\PaginaResource\Pages\Concerns;

trait OrdersPageLinks
{
    public bool $isOrderingLinks = false;

    /** @return array<string, string> */
    public function linkOrderOptions(): array
    {
        $options = [];

        foreach ($this->data['enlaces'] ?? [] as $key => $link) {
            $name = trim((string) ($link['nombre'] ?? ''));
            $options[$key] = (count($options) + 1).'. '.($name !== '' ? $name : 'Nuevo enlace');
        }

        return $options;
    }

    public function toggleLinkOrdering(): void
    {
        $this->isOrderingLinks = ! $this->isOrderingLinks;
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

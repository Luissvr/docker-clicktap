<?php

namespace App\Data;

use App\Models\EnlacePagina;
use App\Models\Pagina;

final readonly class PaginaPublica
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data)
    {
    }

    public static function fromModel(Pagina $pagina): self
    {
        $name = trim((string) $pagina->nombre_publico);
        if ($name === '') {
            throw new \InvalidArgumentException("La página {$pagina->slug} no tiene nombre público.");
        }

        $highlights = [];
        if ($address = trim((string) $pagina->direccion_publica)) {
            $highlights[] = ['id' => 'location', 'icon' => 'location', 'label' => 'Ubicación', 'value' => $address];
        }

        $contact = collect([$pagina->telefono_publico, $pagina->correo_publico])
            ->filter(fn (?string $value): bool => trim((string) $value) !== '')
            ->map(fn (?string $value): string => trim((string) $value))
            ->implode(' · ');
        if ($contact !== '') {
            $highlights[] = ['id' => 'contact', 'icon' => 'contact', 'label' => 'Contacto', 'value' => $contact];
        }

        return new self([
            'slug' => $pagina->slug,
            'name' => $name,
            'tagline' => null,
            'description' => trim((string) $pagina->descripcion),
            'theme' => $pagina->tema ?: 'default',
            'logo' => self::publicPath($pagina->logo_path),
            'logoAlt' => "Logo de {$name}",
            'favicon' => self::publicPath($pagina->logo_path) ?? '/favicon.png',
            'footer' => null,
            'highlights' => $highlights,
            'links' => $pagina->enlaces
                ->where('activo', true)
                ->map(fn (EnlacePagina $enlace): array => self::link($enlace))
                ->values()
                ->all(),
            'colors' => [
                'background' => $pagina->color_fondo ?: '#ffffff',
                'text' => $pagina->color_texto ?: '#000000',
                'button' => $pagina->color_boton ?: '#111827',
                'buttonText' => $pagina->color_texto_boton ?: '#ffffff',
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    private static function link(EnlacePagina $enlace): array
    {
        $type = (string) $enlace->tipo;
        $url = trim((string) $enlace->url);
        $copyText = $enlace->copiar_texto ? trim((string) $enlace->texto_copia) : '';

        return [
            'type' => $type,
            'icon' => match ($type) {
                'whatsapp', 'instagram', 'facebook', 'review', 'location' => $type,
                default => 'contact',
            },
            'label' => trim((string) $enlace->etiqueta) ?: self::defaultLabel($type),
            'href' => $url !== '' ? $url : null,
            'copyText' => $copyText !== '' ? $copyText : null,
            'isDisabled' => $url === '' && $copyText === '',
        ];
    }

    private static function defaultLabel(string $type): string
    {
        return match ($type) {
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'facebook' => 'Facebook',
            'review' => 'Escribir una reseña',
            'location' => 'Cómo llegar',
            'transferencia' => 'Copiar datos de transferencia',
            'web' => 'Sitio web',
            default => 'Enlace',
        };
    }

    private static function publicPath(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return preg_match('/^https?:\/\//i', $path) === 1
            ? $path
            : '/storage/'.ltrim($path, '/');
    }
}

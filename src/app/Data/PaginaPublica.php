<?php

namespace App\Data;

use App\Models\EnlacePagina;
use App\Models\Pagina;
use App\Support\PageAppearance;
use App\Support\TransferBank;

final readonly class PaginaPublica
{
    /** @param array<string, mixed> $data */
    private function __construct(private array $data) {}

    public static function fromModel(Pagina $pagina): self
    {
        $name = trim((string) $pagina->nombre_publico);
        if ($name === '') {
            throw new \InvalidArgumentException("La página {$pagina->slug} no tiene nombre público.");
        }

        $extra = is_array($pagina->contenido_extra) ? $pagina->contenido_extra : [];
        $highlights = [];
        if ($address = trim((string) $pagina->direccion_publica)) {
            $highlights[] = ['id' => 'location', 'icon' => 'location', 'label' => 'Ubicación', 'value' => $address];
        }

        $phone = trim((string) $pagina->telefono_publico);
        $email = trim((string) $pagina->correo_publico);
        $contact = collect([$phone, $email])
            ->filter(fn (?string $value): bool => trim((string) $value) !== '')
            ->map(fn (?string $value): string => trim((string) $value))
            ->implode("\n");
        $contactDetails = [];
        if ($contact !== '' && $pagina->tema === 'comercio') {
            if ($phone !== '') {
                $contactDetails[] = [
                    'label' => $phone,
                    'href' => 'tel:'.preg_replace('/[^\d+]/', '', $phone),
                ];
            }
            if ($email !== '') {
                $contactDetails[] = ['label' => $email, 'href' => 'mailto:'.$email];
            }
        } elseif ($contact !== '') {
            $highlights[] = [
                'id' => 'contact',
                'icon' => 'contact',
                'label' => 'Contacto',
                'value' => $contact,
                'phone' => $phone ?: null,
                'email' => $email ?: null,
            ];
        }
        if ($schedule = trim((string) ($extra['schedule'] ?? ''))) {
            $highlights[] = ['id' => 'schedule', 'icon' => 'schedule', 'label' => 'Horario', 'value' => $schedule];
        }
        $fallbackTransferLogo = TransferBank::publicLogo($extra['transfer_logo'] ?? 'none');

        $links = $pagina->enlaces
            ->where('activo', true)
            ->map(fn (EnlacePagina $enlace): array => self::link($enlace, $fallbackTransferLogo))
            ->values()
            ->all();

        $appearance = PageAppearance::resolve([
            'color_fondo' => $pagina->color_fondo,
            'color_texto' => $pagina->color_texto,
            'color_boton' => $pagina->color_boton,
            'color_texto_boton' => $pagina->color_texto_boton,
        ]);

        return new self([
            'slug' => $pagina->slug,
            'name' => $name,
            'tagline' => trim((string) ($extra['tagline'] ?? '')) ?: null,
            'description' => trim((string) $pagina->descripcion),
            'theme' => $pagina->tema ?: 'default',
            'typography' => $pagina->tipografia ?: 'inter',
            'logo' => self::publicPath($pagina->logo_path),
            'logoAlt' => "Logo de {$name}",
            'logoLayout' => $pagina->logo_layout ?: 'original',
            'logoWidth' => (int) ($pagina->logo_width ?: 160),
            'favicon' => self::publicPath($pagina->logo_path) ?? '/favicon.png',
            'footer' => $pagina->tema === 'comercio' ? '' : null,
            'highlights' => $highlights,
            'contactDetails' => $contactDetails,
            'links' => $links,
            'colors' => [
                'background' => $appearance['color_fondo'],
                'text' => $appearance['color_texto'],
                'button' => $appearance['color_boton'],
                'buttonText' => $appearance['color_texto_boton'],
                'accent' => $appearance['color_acento'],
            ],
        ]);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return $this->data;
    }

    /** @return array<string, mixed> */
    private static function link(EnlacePagina $enlace, ?string $fallbackTransferLogo = null): array
    {
        $type = (string) $enlace->tipo;
        $url = $type === 'transferencia' ? '' : trim((string) $enlace->url);
        $copyText = ($type === 'transferencia' || ($type !== 'review' && $enlace->copiar_texto)) ? trim((string) $enlace->texto_copia) : '';

        return [
            'type' => $type,
            'name' => trim((string) $enlace->nombre) ?: null,
            'icon' => match ($type) {
                'whatsapp', 'instagram', 'tiktok', 'facebook', 'review', 'location' => $type,
                default => 'contact',
            },
            'label' => trim((string) ($enlace->etiqueta ?: $enlace->nombre)) ?: self::defaultLabel($type),
            'href' => $url !== '' ? $url : null,
            'copyText' => $type === 'transferencia' || $copyText !== '' ? $copyText : null,
            'logo' => $type === 'transferencia'
                ? (TransferBank::publicLogo($enlace->banco_transferencia) ?? $fallbackTransferLogo)
                : null,
            'isDisabled' => $type !== 'transferencia' && $url === '' && $copyText === '',
        ];
    }

    private static function defaultLabel(string $type): string
    {
        return match ($type) {
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
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

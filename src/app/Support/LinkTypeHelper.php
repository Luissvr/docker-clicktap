<?php

namespace App\Support;

use Illuminate\Support\HtmlString;

final class LinkTypeHelper
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'whatsapp' => 'WhatsApp',
            'instagram' => 'Instagram',
            'tiktok' => 'TikTok',
            'facebook' => 'Facebook',
            'web' => 'Página web',
            'review' => 'Reseñas (Google)',
            'location' => 'Ubicación / Mapa',
            'transferencia' => 'Datos de transferencia',
            'otro' => 'Otro enlace',
        ];
    }

    public static function label(?string $type): string
    {
        return self::options()[$type ?? ''] ?? 'Enlace';
    }

    public static function heroicon(?string $type): string
    {
        return match ($type) {
            'whatsapp' => 'heroicon-o-chat-bubble-left-ellipsis',
            'instagram' => 'heroicon-o-camera',
            'tiktok' => 'heroicon-o-musical-note',
            'facebook' => 'heroicon-o-user-group',
            'web' => 'heroicon-o-globe-alt',
            'review' => 'heroicon-s-star',
            'location' => 'heroicon-o-map-pin',
            'transferencia' => 'heroicon-o-building-library',
            default => 'heroicon-o-link',
        };
    }

    public static function brandColor(?string $type): string
    {
        return match ($type) {
            'whatsapp' => '#25D366',
            'instagram' => '#E1306C',
            'tiktok' => '#111827',
            'facebook' => '#1877F2',
            'web' => '#2563EB',
            'review' => '#EAB308',
            'location' => '#EF4444',
            'transferencia' => '#0D9488',
            default => '#6B7280',
        };
    }

    public static function reviewUrlFromPlaceId(?string $placeId): ?string
    {
        $placeId = trim((string) $placeId);

        return $placeId === ''
            ? null
            : 'https://search.google.com/local/writereview?placeid='.rawurlencode($placeId);
    }

    public static function iconSvg(?string $type, string $class = 'w-4 h-4'): string
    {
        return match ($type) {
            'whatsapp' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.248-.446.05-.149.025-.273-.025-.397-.05-.124-.67-1.611-.916-2.206-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982 1-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.887 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.893c0 2.096.547 4.142 1.588 5.946L.057 24l6.304-1.654a11.882 11.882 0 0 0 5.684 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.815 11.815 0 0 0-3.479-8.413" fill="currentColor"/></svg>',
            'instagram' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="3" width="18" height="18" rx="5" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="4" stroke="currentColor" stroke-width="2"/><circle cx="17.5" cy="6.5" r="1.25" fill="currentColor"/></svg>',
            'tiktok' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>',
            'facebook' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M13.5 21v-8h2.75l.42-3h-3.17V8.1c0-.87.24-1.46 1.5-1.46h1.8V3.96c-.31-.04-1.37-.13-2.61-.13-2.58 0-4.35 1.57-4.35 4.46V10H7v3h2.84v8h3.66Z"/></svg>',
            'web' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>',
            'review' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg>',
            'location' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>',
            'transferencia' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v4M12 14v4M16 14v4"/></svg>',
            default => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>',
        };
    }

    /**
     * @param array<string, mixed> $state
     */
    public static function itemLabel(array $state): HtmlString
    {
        $tipo = $state['tipo'] ?? 'web';
        $nombre = trim((string) ($state['nombre'] ?? ''));
        $tipoNombre = self::label($tipo);
        $color = self::brandColor($tipo);
        $svg = self::iconSvg($tipo, 'w-3.5 h-3.5');

        $titulo = $nombre !== '' ? $nombre : 'Nuevo enlace';
        $escapedTitulo = htmlspecialchars($titulo);

        $html = '<span class="inline-flex items-center gap-2 max-w-full truncate text-sm">';
        $html .= '<span class="inline-flex items-center justify-center w-6 h-6 rounded-md text-white shrink-0 shadow-xs" style="background-color: '.$color.';" title="'.$tipoNombre.'">'.$svg.'</span>';
        $html .= '<span class="font-semibold text-gray-900 dark:text-gray-100 truncate">'.$escapedTitulo.'</span>';
        $html .= '</span>';

        return new HtmlString($html);
    }
}

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

    public static function reviewPlaceIdFromUrl(?string $url): ?string
    {
        $query = parse_url((string) $url, PHP_URL_QUERY);
        if (! is_string($query)) {
            return null;
        }

        parse_str($query, $parameters);
        $placeId = $parameters['placeid'] ?? $parameters['query_place_id'] ?? null;

        return is_string($placeId) && trim($placeId) !== '' ? trim($placeId) : null;
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
        $whatsappPath = 'M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.248-.446.05-.149.025-.273-.025-.397-.05-.124-.67-1.611-.916-2.206-.242-.579-.487-.5-.67-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 0 1-5.031-1.378l-.361-.214-3.741.982 1-3.648-.235-.374a9.86 9.86 0 0 1-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 0 1 2.893 6.994c-.003 5.45-4.437 9.884-9.887 9.884m8.413-18.297A11.815 11.815 0 0 0 12.05 0C5.495 0 .16 5.335.157 11.893c0 2.096.547 4.142 1.588 5.946L.057 24l6.304-1.654a11.882 11.882 0 0 0 5.684 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.815 11.815 0 0 0-3.479-8.413';
        $tiktokPath = 'M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z';
        $gradientId = uniqid('ct-instagram-');

        $body = match ($type) {
            'whatsapp' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#25D366"/><path transform="translate(5 5) scale(.9167)" d="'.$whatsappPath.'" fill="#fff"/>',
            'instagram' => '<defs><linearGradient id="'.$gradientId.'" x1="0" y1="1" x2="1" y2="0"><stop stop-color="#FFB900"/><stop offset=".45" stop-color="#E1306C"/><stop offset="1" stop-color="#833AB4"/></linearGradient></defs><rect x="1" y="1" width="30" height="30" rx="8" fill="url(#'.$gradientId.')"/><rect x="7" y="7" width="18" height="18" rx="5.5" stroke="#fff" stroke-width="2"/><circle cx="16" cy="16" r="4.3" stroke="#fff" stroke-width="2"/><circle cx="22" cy="10" r="1.3" fill="#fff"/>',
            'tiktok' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#111827"/><path transform="translate(5.2 4.2) scale(.9)" d="'.$tiktokPath.'" fill="#25F4EE"/><path transform="translate(6.8 5.8) scale(.9)" d="'.$tiktokPath.'" fill="#FE2C55"/><path transform="translate(6 5) scale(.9)" d="'.$tiktokPath.'" fill="#fff"/>',
            'facebook' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#1877F2"/><path d="M18.4 28V17.5H22l.6-4h-4.2v-2.6c0-1.2.3-2 2-2h2.3V5.3c-.4-.1-1.8-.2-3.4-.2-3.4 0-5.7 2.1-5.7 5.8v2.6H10v4h3.6V28Z" fill="#fff"/>',
            'review' => '<path d="m16 3 4.02 8.15 9 1.31-6.51 6.34L24.05 28 16 23.66 7.95 28l1.54-9.2-6.51-6.34 9-1.31Z" fill="#FACC15" stroke="#A16207" stroke-width="1.25" stroke-linejoin="round"/>',
            'web' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#2563EB"/><g stroke="#fff" stroke-width="1.7" stroke-linecap="round"><circle cx="16" cy="16" r="9"/><ellipse cx="16" cy="16" rx="4" ry="9"/><path d="M7 16h18M9 11h14M9 21h14"/></g>',
            'location' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#DC2626"/><path d="M16 26s8-7.5 8-13a8 8 0 0 0-16 0c0 5.5 8 13 8 13Z" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/><circle cx="16" cy="13" r="2.8" stroke="#fff" stroke-width="1.8"/>',
            'transferencia' => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#0F766E"/><g stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="m6 12 10-6 10 6H6ZM7 26h18M9 15v7m7-7v7m7-7v7"/><circle cx="16" cy="10" r=".7" fill="#fff" stroke="none"/></g>',
            default => '<rect x="1" y="1" width="30" height="30" rx="8" fill="#475569"/><g stroke="#fff" stroke-width="2" stroke-linecap="round"><path d="m13 19 6-6M13 12l3-3a5 5 0 0 1 7 7l-3 3M19 20l-3 3a5 5 0 0 1-7-7l3-3"/></g>',
        };

        return '<svg class="'.e($class).'" width="32" height="32" viewBox="0 0 32 32" fill="none" aria-hidden="true" focusable="false">'.$body.'</svg>';
    }

    /**
     * @param  array<string, mixed>  $state
     */
    public static function itemLabel(array $state): HtmlString
    {
        $tipo = $state['tipo'] ?? 'web';
        $nombre = trim((string) ($state['nombre'] ?? ''));
        $tipoNombre = self::label($tipo);
        $svg = self::iconSvg($tipo, 'w-6 h-6');

        $titulo = $nombre !== '' ? $nombre : 'Nuevo enlace';
        $escapedTitulo = htmlspecialchars($titulo);

        $html = '<span class="inline-flex items-center gap-2 max-w-full truncate text-sm">';
        $html .= '<span class="inline-flex items-center justify-center w-7 h-7 shrink-0" title="'.$tipoNombre.'">'.$svg.'</span>';
        $html .= '<span class="font-semibold text-gray-900 dark:text-gray-100 truncate">'.$escapedTitulo.'</span>';
        $html .= '</span>';

        return new HtmlString($html);
    }
}

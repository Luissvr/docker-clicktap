<?php

namespace App\Support;

use App\Models\Pagina;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class PagePreviewCompiler
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function compile(array $data, ?Pagina $record = null): string
    {
        $nombre = $data['nombre_publico'] ?? ($record?->nombre_publico ?? '');
        $descripcion = $data['descripcion'] ?? ($record?->descripcion ?? '');
        $direccion = $data['direccion_publica'] ?? ($record?->direccion_publica ?? '');
        $telefono = $data['telefono_publico'] ?? ($record?->telefono_publico ?? '');
        $correo = $data['correo_publico'] ?? ($record?->correo_publico ?? '');
        $tema = $data['tema'] ?? ($record?->tema ?? 'default');
        $extra = $data['contenido_extra'] ?? ($record?->contenido_extra ?? []);
        $extra = is_array($extra) ? $extra : [];
        $tagline = $extra['tagline'] ?? '';
        $schedule = $extra['schedule'] ?? '';
        $bankLogo = TransferBank::publicLogo($extra['transfer_logo'] ?? 'none');
        $tipografia = $data['tipografia'] ?? ($record?->tipografia ?? 'inter');
        $colorFondo = $data['color_fondo'] ?? ($record?->color_fondo ?? '#ffffff');
        $colorTexto = $data['color_texto'] ?? ($record?->color_texto ?? '#000000');
        $colorBoton = $data['color_boton'] ?? ($record?->color_boton ?? '#111827');
        $colorTextoBoton = $data['color_texto_boton'] ?? ($record?->color_texto_boton ?? '#ffffff');
        $appearance = PageAppearance::resolve([
            'color_fondo' => $colorFondo,
            'color_texto' => $colorTexto,
            'color_boton' => $colorBoton,
            'color_texto_boton' => $colorTextoBoton,
        ]);
        $rawEnlaces = $data['enlaces'] ?? ($record?->enlaces?->toArray() ?? []);

        $enlaces = collect($rawEnlaces)
            ->filter(fn ($item) => ($item['activo'] ?? true))
            ->map(function (array $item) use ($extra, $bankLogo): array {
                if (($item['tipo'] ?? null) === 'review') {
                    if (array_key_exists('place_id', $item)) {
                        $item['url'] = LinkTypeHelper::reviewUrlFromPlaceId($item['place_id']);
                    }
                    $item['copiar_texto'] = false;
                }

                if (($item['tipo'] ?? null) === 'transferencia') {
                    $item['transfer_logo'] = TransferBank::publicLogo($item['banco_transferencia'] ?? $extra['transfer_logo'] ?? 'none') ?? $bankLogo;
                }

                return $item;
            })
            ->values()
            ->all();

        $logoState = $data['logo_path'] ?? ($record?->logo_path ?? null);
        $logoUrl = self::resolveLogoUrl($logoState, $record);

        $fontFamily = TypographyHelper::fontFamily($tipografia);
        $googleFontUrl = TypographyHelper::googleFontUrl($tipografia);

        return view('paginas.mini-preview', [
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'correo' => $correo,
            'tema' => $tema,
            'tagline' => $tagline,
            'schedule' => $schedule,
            'bankLogo' => $bankLogo,
            'tipografia' => $tipografia,
            'colorFondo' => $appearance['color_fondo'],
            'colorTexto' => $appearance['color_texto'],
            'colorBoton' => $appearance['color_boton'],
            'colorTextoBoton' => $appearance['color_texto_boton'],
            'colorAcento' => $appearance['color_acento'],
            'enlaces' => $enlaces,
            'logoUrl' => $logoUrl,
            'logoLayout' => $data['logo_layout'] ?? ($record?->logo_layout ?? 'original'),
            'logoWidth' => (int) ($data['logo_width'] ?? ($record?->logo_width ?? 160)),
            'fontFamily' => $fontFamily,
            'googleFontUrl' => $googleFontUrl,
        ])->render();
    }

    public static function resolveLogoUrl(mixed $logoState, ?Pagina $record = null): ?string
    {
        if (is_array($logoState) && ! empty($logoState)) {
            $first = reset($logoState);
            if ($first instanceof TemporaryUploadedFile) {
                try {
                    return $first->temporaryUrl();
                } catch (\Throwable) {
                    // Fallback
                }
            }
            if (is_string($first) && trim($first) !== '') {
                return self::formatStorageUrl($first, $record?->logo_disk);
            }
        }

        if (is_string($logoState) && trim($logoState) !== '') {
            return self::formatStorageUrl($logoState, $record?->logo_disk);
        }

        if ($record?->logo_path) {
            return self::formatStorageUrl($record->logo_path, $record->logo_disk);
        }

        return null;
    }

    private static function formatStorageUrl(string $path, ?string $disk = null): string
    {
        $path = trim($path);
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $disk = $disk ?? config('clicktap.media_disk', 'public');
        if ($disk === 'public') {
            return '/storage/'.ltrim($path, '/');
        }

        try {
            return Storage::disk($disk)->temporaryUrl($path, now()->addMinutes(15));
        } catch (\Throwable) {
            return '/storage/'.ltrim($path, '/');
        }
    }
}

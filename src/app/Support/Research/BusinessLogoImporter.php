<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Descarga el logo encontrado en la web de un negocio y lo guarda en el disco de medios.
 */
class BusinessLogoImporter
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public function __construct(private SafeHttpFetcher $fetcher) {}

    /**
     * @return string Ruta guardada en el disco de medios.
     *
     * @throws BusinessResearchException
     */
    public function import(string $url): string
    {
        $response = $this->fetcher->get($url, 'image/png,image/jpeg,image/webp,image/gif,*/*;q=0.5');
        if ($response['status'] < 200 || $response['status'] >= 300) {
            throw new BusinessResearchException('El sitio respondió con el código '.$response['status'].' al pedir el logo.');
        }

        $info = @getimagesizefromstring($response['body']);
        $extension = is_array($info) ? (self::EXTENSIONS[$info['mime'] ?? ''] ?? null) : null;
        if ($extension === null || $info[0] < 32 || $info[1] < 32) {
            throw new BusinessResearchException('El archivo encontrado no es una imagen utilizable (se aceptan JPG, PNG, WEBP y GIF).');
        }

        $diskName = (string) config('clicktap.media_disk', 'public');
        $path = 'logos/'.Str::uuid().'.'.$extension;
        Storage::disk($diskName)->put($path, $response['body'], $diskName === 'public' ? 'public' : 'private');

        return $path;
    }
}

<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Archivos de los enlaces tipo "Menú": una foto (se optimiza) o un PDF.
 */
final class MenuFile
{
    public const DIRECTORY = 'menus';

    public const MAX_UPLOAD_KB = 15360;   // 15 MB que el admin puede subir
    public const MAX_PDF_BYTES = 10 * 1024 * 1024;
    public const TARGET_IMAGE_BYTES = 350 * 1024;

    /** @var list<array{int, int}> [lado máximo en px, calidad JPEG] de mayor a menor */
    private const STEPS = [[1800, 78], [1600, 72], [1400, 66], [1200, 60], [1000, 55], [900, 50]];

    public static function disk(): string
    {
        return (string) config('clicktap.media_disk', 'public');
    }

    /** Guarda el archivo subido (optimizado si es imagen) y devuelve su ruta en el disco. */
    public static function store(UploadedFile $file, ?string $disk = null): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        [$contents, $extension] = self::prepare($contents);

        $path = self::DIRECTORY.'/'.Str::uuid().'.'.$extension;
        Storage::disk($disk ?? self::disk())->put($path, $contents);

        return $path;
    }

    /**
     * @return array{string, string} [contenido listo para guardar, extensión]
     */
    public static function prepare(string $contents): array
    {
        if (str_starts_with($contents, '%PDF-')) {
            if (strlen($contents) > self::MAX_PDF_BYTES) {
                throw new RuntimeException('El PDF del menú no puede superar los 10 MB.');
            }

            return [$contents, 'pdf'];
        }

        return [self::optimizeImage($contents), 'jpg'];
    }

    /** Reduce una foto a un JPEG progresivo lo más liviano posible sin que el texto pierda legibilidad. */
    public static function optimizeImage(string $contents, int $targetBytes = self::TARGET_IMAGE_BYTES): string
    {
        if (! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw new RuntimeException('El servidor no puede procesar imágenes (falta la extensión GD).');
        }
        $info = @getimagesizefromstring($contents);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            throw new RuntimeException('El menú debe ser una foto JPG o PNG, o un PDF.');
        }
        if ($info[0] * $info[1] > 60_000_000) {
            throw new RuntimeException('La imagen del menú es demasiado grande.');
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw new RuntimeException('No se pudo leer la imagen del menú.');
        }

        try {
            $source = self::orient($source, $contents, $info[2]);
            $width = imagesx($source);
            $height = imagesy($source);
            $best = null;

            foreach (self::STEPS as [$maxSide, $quality]) {
                $scale = min(1, $maxSide / max($width, $height));
                $newWidth = max(1, (int) round($width * $scale));
                $newHeight = max(1, (int) round($height * $scale));

                $canvas = imagecreatetruecolor($newWidth, $newHeight);
                imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255)); // aplana transparencias
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                imageinterlace($canvas, true);

                ob_start();
                imagejpeg($canvas, null, $quality);
                $candidate = (string) ob_get_clean();
                imagedestroy($canvas);

                if ($best === null || strlen($candidate) < strlen($best)) {
                    $best = $candidate;
                }
                if (strlen($candidate) <= $targetBytes) {
                    break;
                }
            }
        } finally {
            imagedestroy($source);
        }

        return (string) $best;
    }

    /** @param \GdImage $image */
    private static function orient($image, string $contents, int $type)
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        try {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        } catch (Throwable) {
            return $image;
        }
        $rotated = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => false,
        };
        if ($rotated === false) {
            return $image;
        }
        imagedestroy($image);

        return $rotated;
    }

    /** Ruta pública relativa dentro del sitio Astro. */
    public static function publicPath(string $slug, ?string $path): ?string
    {
        $path = trim((string) $path);

        return $path === '' ? null : '/generated/menus/'.$slug.'/'.basename($path);
    }

    public static function kind(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }

        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'pdf' ? 'pdf' : 'image';
    }

    public static function isValidPath(?string $path): bool
    {
        return is_string($path) && preg_match('#^'.self::DIRECTORY.'/[A-Za-z0-9._-]+\.(jpg|pdf)$#', $path) === 1;
    }

    public static function delete(?string $path, ?string $disk = null): void
    {
        if (! self::isValidPath($path)) {
            return;
        }

        try {
            Storage::disk($disk ?? self::disk())->delete($path);
        } catch (Throwable) {
            // El archivo huérfano no debe impedir guardar la página.
        }
    }
}

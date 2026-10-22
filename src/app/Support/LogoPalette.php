<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class LogoPalette
{
    /** @return array{color_fondo: string, color_texto: string, color_boton: string, color_texto_boton: string}|null */
    public static function from(mixed $upload): ?array
    {
        if (is_array($upload)) {
            $upload = reset($upload);
        }

        if ($upload instanceof TemporaryUploadedFile) {
            return self::fromContents($upload->get());
        }

        $path = $upload instanceof UploadedFile
            ? $upload->getRealPath()
            : (is_string($upload) && is_file($upload) ? $upload : null);

        return $path ? self::fromPath($path) : null;
    }

    /** @return array{color_fondo: string, color_texto: string, color_boton: string, color_texto_boton: string}|null */
    public static function fromPath(string $path): ?array
    {
        $contents = @file_get_contents($path);

        return is_string($contents) ? self::fromContents($contents) : null;
    }

    /** @return array{color_fondo: string, color_texto: string, color_boton: string, color_texto_boton: string}|null */
    public static function fromContents(string $contents): ?array
    {
        $info = @getimagesizefromstring($contents);

        if (! is_array($info) || ! isset($info['mime'])) {
            return null;
        }

        $image = in_array($info['mime'], ['image/jpeg', 'image/png', 'image/gif', 'image/webp'], true)
            ? @imagecreatefromstring($contents)
            : false;

        if (! $image) {
            return null;
        }

        $colors = [];
        $width = imagesx($image);
        $height = imagesy($image);
        $stepX = max(1, (int) ceil($width / 24));
        $stepY = max(1, (int) ceil($height / 24));

        for ($y = 0; $y < $height; $y += $stepY) {
            for ($x = 0; $x < $width; $x += $stepX) {
                $pixel = imagecolorat($image, $x, $y);
                $alpha = ($pixel >> 24) & 0x7F;

                if ($alpha > 32) {
                    continue;
                }

                $red = ($pixel >> 16) & 0xFF;
                $green = ($pixel >> 8) & 0xFF;
                $blue = $pixel & 0xFF;
                $key = implode(',', [intdiv($red, 32), intdiv($green, 32), intdiv($blue, 32)]);
                $colors[$key] = ($colors[$key] ?? 0) + 1;
            }
        }

        imagedestroy($image);

        if ($colors === []) {
            return null;
        }

        $accent = self::selectAccent($colors);
        $accentHex = self::hex($accent);
        $background = self::hex(self::mix($accent, [255, 255, 255], 0.86));
        $palette = [
            'color_fondo' => $background,
            'color_texto' => '#111827',
            'color_boton' => $accentHex,
            'color_texto_boton' => self::luminance($accent) > 0.55 ? '#111827' : '#ffffff',
        ];

        return array_intersect_key(PageAppearance::resolve($palette), $palette);
    }

    /** @param array<string, int> $colors @return array{int, int, int} */
    private static function selectAccent(array $colors): array
    {
        uasort($colors, function (int $left, int $right): int {
            return $right <=> $left;
        });

        $best = null;
        $bestScore = -1.0;
        $fallback = null;

        foreach ($colors as $key => $count) {
            $parts = array_map('intval', explode(',', $key));
            $rgb = array_map(fn (int $value): int => min(255, $value * 32 + 16), $parts);
            $fallback ??= $rgb;
            $max = max($rgb);
            $min = min($rgb);
            $chroma = $max - $min;

            // Ignore white, black and gray backgrounds when a logo has a real accent.
            if ($chroma < 24 || $max < 24 || ($min > 220 && $chroma < 48)) {
                continue;
            }

            $saturation = $max === 0 ? 0 : ($max - $min) / $max;
            $score = $count * (1 + ($saturation * 5));

            if ($score > $bestScore) {
                $best = $rgb;
                $bestScore = $score;
            }
        }

        return $best ?? $fallback ?? [17, 24, 39];
    }

    /** @param array{int, int, int} $color @param array{int, int, int} $target */
    private static function mix(array $color, array $target, float $targetWeight): array
    {
        return array_map(
            fn (int $value, int $index): int => (int) round(($value * (1 - $targetWeight)) + ($target[$index] * $targetWeight)),
            $color,
            array_keys($color),
        );
    }

    /** @param array{int, int, int} $color */
    private static function luminance(array $color): float
    {
        $normalized = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $color);

        return 0.2126 * $normalized[0] + 0.7152 * $normalized[1] + 0.0722 * $normalized[2];
    }

    /** @param array{int, int, int} $color */
    private static function hex(array $color): string
    {
        return sprintf('#%02x%02x%02x', ...$color);
    }
}

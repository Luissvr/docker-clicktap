<?php

namespace App\Support;

final class PageAppearance
{
    /**
     * @param  array{color_fondo?: mixed, color_texto?: mixed, color_boton?: mixed, color_texto_boton?: mixed}  $colors
     * @return array{color_fondo: string, color_texto: string, color_boton: string, color_texto_boton: string, color_acento: string}
     */
    public static function resolve(array $colors): array
    {
        $background = self::normalize($colors['color_fondo'] ?? '#ffffff');
        $button = self::normalize($colors['color_boton'] ?? '#111827');
        $text = self::normalize($colors['color_texto'] ?? '#000000');
        $buttonText = self::normalize($colors['color_texto_boton'] ?? '#ffffff');

        if (self::contrastRatio($text, $background) < 4.5) {
            $text = self::bestContrastingText($background);
        }

        $accent = self::contrastRatio($button, $background) >= 4.5 ? $button : self::bestContrastingText($background);
        if (self::contrastRatio($button, $background) < 3) {
            $button = self::bestContrastingText($background);
        }

        if (self::contrastRatio($buttonText, $button) < 4.5) {
            $buttonText = self::bestContrastingText($button);
        }

        return [
            'color_fondo' => $background,
            'color_texto' => $text,
            'color_boton' => $button,
            'color_texto_boton' => $buttonText,
            'color_acento' => $accent,
        ];
    }

    public static function contrastRatio(string $foreground, string $background): float
    {
        $first = self::luminance(self::rgb($foreground));
        $second = self::luminance(self::rgb($background));
        $lighter = max($first, $second);
        $darker = min($first, $second);

        return ($lighter + 0.05) / ($darker + 0.05);
    }

    private static function normalize(mixed $value): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) === 1
            ? strtolower($value)
            : '#ffffff';
    }

    /** @return array{int, int, int} */
    private static function rgb(string $hex): array
    {
        return [
            hexdec(substr($hex, 1, 2)),
            hexdec(substr($hex, 3, 2)),
            hexdec(substr($hex, 5, 2)),
        ];
    }

    /** @param array{int, int, int} $color */
    private static function luminance(array $color): float
    {
        $channels = array_map(static function (int $channel): float {
            $value = $channel / 255;

            return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, $color);

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    }

    private static function bestContrastingText(string $background): string
    {
        return self::contrastRatio('#000000', $background) >= self::contrastRatio('#ffffff', $background)
            ? '#000000'
            : '#ffffff';
    }
}

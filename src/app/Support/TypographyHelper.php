<?php

namespace App\Support;

final class TypographyHelper
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            'inter' => 'Inter (Predeterminada ClickTap · Sans-serif moderna)',
            'poppins' => 'Poppins (Geométrica, moderna y amigable)',
            'montserrat' => 'Montserrat (Elegante y contemporánea)',
            'roboto' => 'Roboto (Clásica y de alta legibilidad)',
            'plus-jakarta-sans' => 'Plus Jakarta Sans (Limpia y prémium)',
            'outfit' => 'Outfit (Tecnológica y minimalista)',
            'playfair-display' => 'Playfair Display (Serif sofisticada · Gastronomía y moda)',
            'merriweather' => 'Merriweather (Serif clásica y cálida)',
            'oswald' => 'Oswald (Condensada, deportiva y con impacto)',
            'system' => 'Sistema (Tipografía nativa de cada dispositivo)',
        ];
    }

    public static function fontFamily(?string $key): string
    {
        return match ($key) {
            'poppins' => "'Poppins', ui-sans-serif, system-ui, sans-serif",
            'montserrat' => "'Montserrat', ui-sans-serif, system-ui, sans-serif",
            'roboto' => "'Roboto', ui-sans-serif, system-ui, sans-serif",
            'plus-jakarta-sans' => "'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif",
            'outfit' => "'Outfit', ui-sans-serif, system-ui, sans-serif",
            'playfair-display' => "'Playfair Display', Georgia, serif",
            'merriweather' => "'Merriweather', Georgia, serif",
            'oswald' => "'Oswald', Impact, sans-serif",
            'system' => "ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
            default => "'Inter', ui-sans-serif, system-ui, sans-serif",
        };
    }

    public static function googleFontFamily(?string $key): ?string
    {
        return match ($key) {
            'poppins' => 'Poppins:wght@400;500;600;700',
            'montserrat' => 'Montserrat:wght@400;500;600;700',
            'roboto' => 'Roboto:wght@400;500;700',
            'plus-jakarta-sans' => 'Plus+Jakarta+Sans:wght@400;500;600;700',
            'outfit' => 'Outfit:wght@400;500;600;700',
            'playfair-display' => 'Playfair+Display:ital,wght@0,400;0,600;0,700;1,400',
            'merriweather' => 'Merriweather:wght@400;700',
            'oswald' => 'Oswald:wght@400;600;700',
            'system' => null,
            default => 'Inter:wght@400;500;600;700',
        };
    }

    public static function googleFontUrl(?string $key): ?string
    {
        $family = self::googleFontFamily($key);
        if ($family === null) {
            return null;
        }

        return 'https://fonts.googleapis.com/css2?family='.$family.'&display=swap';
    }
}

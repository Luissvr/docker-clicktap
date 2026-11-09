<?php

namespace Tests\Unit;

use App\Support\Research\SocialProfileSearch;
use App\Support\Research\WebsitePageReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SocialProfileUrlTest extends TestCase
{
    /**
     * @return array<string, array{string, string, ?string}>
     */
    public static function inputs(): array
    {
        return [
            'enlace completo de instagram' => ['https://www.instagram.com/7ebarber.shop/', 'instagram', 'https://instagram.com/7ebarber.shop'],
            'enlace con parámetros' => ['https://www.instagram.com/7ebarber.shop/?hl=es&igsh=abc', 'instagram', 'https://instagram.com/7ebarber.shop'],
            'sin esquema' => ['instagram.com/7ebarber.shop', 'instagram', 'https://instagram.com/7ebarber.shop'],
            'solo @usuario' => ['@7ebarber.shop', 'instagram', 'https://instagram.com/7ebarber.shop'],
            'solo usuario' => ['7ebarber.shop', 'instagram', 'https://instagram.com/7ebarber.shop'],
            'tiktok con arroba' => ['https://www.tiktok.com/@7ebarber', 'tiktok', 'https://tiktok.com/@7ebarber'],
            'tiktok solo usuario' => ['7ebarber', 'tiktok', 'https://tiktok.com/@7ebarber'],
            'facebook página' => ['https://www.facebook.com/altabarberconcepcion/', 'facebook', 'https://facebook.com/altabarberconcepcion'],
            'facebook people' => ['https://www.facebook.com/people/Olimpo-Barber-Shop/100080161843513/', 'facebook', 'https://facebook.com/people/Olimpo-Barber-Shop/100080161843513'],
            'una publicación no es un perfil' => ['https://www.instagram.com/p/ABC123/', 'instagram', null],
            'un reel no es un perfil' => ['https://www.instagram.com/reel/ABC123/', 'instagram', null],
            'un video de tiktok no es un perfil' => ['https://www.tiktok.com/@7ebarber/video/7218964975056948485', 'tiktok', null],
            'compartir de facebook' => ['https://www.facebook.com/sharer/sharer.php?u=x', 'facebook', null],
            'profile.php sin usuario' => ['https://www.facebook.com/profile.php?id=100077596942830', 'facebook', null],
            'enlace de otra red' => ['https://www.facebook.com/altabarber', 'instagram', null],
            'otro sitio web' => ['https://example.org/7ebarber', 'instagram', null],
            'texto con espacios' => ['barbería siete', 'instagram', null],
            'vacío' => ['', 'instagram', null],
            'red desconocida' => ['@7ebarber', 'myspace', null],
            'usuario demasiado corto' => ['@a', 'instagram', null],
        ];
    }

    #[DataProvider('inputs')]
    public function test_it_accepts_only_real_profiles(string $input, string $network, ?string $expected): void
    {
        $this->assertSame($expected, (new SocialProfileSearch(new WebsitePageReader))->profileUrl($input, $network));
    }
}

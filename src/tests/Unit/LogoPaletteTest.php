<?php

namespace Tests\Unit;

use App\Support\LogoPalette;
use Tests\TestCase;

class LogoPaletteTest extends TestCase
{
    public function test_extracts_a_palette_from_a_logo_image(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'clicktap-logo-');
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 220, 160, 30));
        imagepng($image, $path);
        imagedestroy($image);

        $palette = LogoPalette::fromPath($path);
        unlink($path);

        $this->assertNotNull($palette);
        $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $palette['color_boton']);
        $this->assertNotSame('#111827', $palette['color_boton']);
        $this->assertContains($palette['color_texto_boton'], ['#111827', '#ffffff']);
    }

    public function test_ignores_a_white_background_when_finding_the_logo_accent(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'clicktap-logo-');
        $image = imagecreatetruecolor(24, 24);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $accent = imagecolorallocate($image, 220, 160, 30);

        for ($y = 8; $y < 16; $y++) {
            for ($x = 8; $x < 16; $x++) {
                imagesetpixel($image, $x, $y, $accent);
            }
        }

        imagepng($image, $path);
        imagedestroy($image);

        $palette = LogoPalette::fromPath($path);
        unlink($path);

        $this->assertNotNull($palette);
        $this->assertNotSame('#ffffff', $palette['color_boton']);
        $this->assertStringStartsWith('#', $palette['color_boton']);
    }
}

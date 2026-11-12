<?php

namespace Tests\Feature;

use App\Models\EnlacePagina;
use App\Models\Pagina;
use App\Support\MenuFile;
use App\Support\PublicPagePublisher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class MenuLinkTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function noisyPng(int $width = 2400, int $height = 3200): string
    {
        $image = imagecreatetruecolor($width, $height);
        for ($y = 0; $y < $height; $y += 6) {
            imagefilledrectangle($image, 0, $y, $width, $y + 5, imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
        }
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function test_images_are_shrunk_to_a_light_progressive_jpeg(): void
    {
        $png = $this->noisyPng();
        $optimized = MenuFile::optimizeImage($png);
        $info = getimagesizefromstring($optimized);

        $this->assertSame(IMAGETYPE_JPEG, $info[2]);
        $this->assertLessThanOrEqual(1800, max($info[0], $info[1]));
        $this->assertLessThan(1024 * 1024, strlen($optimized));
    }

    public function test_pdf_is_kept_and_other_files_are_rejected(): void
    {
        [$contents, $extension] = MenuFile::prepare("%PDF-1.4\nmenu");
        $this->assertSame('pdf', $extension);
        $this->assertSame("%PDF-1.4\nmenu", $contents);

        $this->expectException(RuntimeException::class);
        MenuFile::prepare('<svg xmlns="http://www.w3.org/2000/svg"></svg>');
    }

    public function test_pdf_over_ten_megabytes_is_rejected(): void
    {
        $this->expectException(RuntimeException::class);
        MenuFile::prepare('%PDF-'.str_repeat('a', MenuFile::MAX_PDF_BYTES));
    }

    public function test_store_writes_the_optimized_file_to_the_media_disk(): void
    {
        Storage::fake('public');
        config()->set('clicktap.media_disk', 'public');
        $file = UploadedFile::fake()->createWithContent('carta.png', $this->noisyPng(1200, 1600));

        $path = MenuFile::store($file);

        $this->assertMatchesRegularExpression('#^menus/[0-9a-f-]+\.jpg$#', $path);
        Storage::disk('public')->assertExists($path);
        $this->assertTrue(MenuFile::isValidPath($path));
        $this->assertFalse(MenuFile::isValidPath('../.env'));
        $this->assertFalse(MenuFile::isValidPath('menus/../../x.pdf'));
    }

    public function test_public_contract_points_to_the_site_file_and_opens_in_a_new_tab(): void
    {
        $page = Pagina::factory()->create(['slug' => 'cafe', 'nombre_publico' => 'Café', 'activo' => true]);
        $page->enlaces()->create([
            'tipo' => 'menu', 'etiqueta' => 'Ver carta', 'posicion' => 0, 'activo' => true,
            'url' => 'https://ignored.test', 'copiar_texto' => true,
            'archivo_path' => 'menus/abc.pdf',
        ]);

        $link = \App\Data\PaginaPublica::fromModel($page->fresh('enlaces'))->toArray()['links'][0];

        $this->assertSame('/generated/menus/cafe/abc.pdf', $link['href']);
        $this->assertSame('pdf', $link['fileKind']);
        $this->assertTrue($link['opensInNewTab']);
        $this->assertSame('book', $link['icon']);
        $this->assertNull($link['copyText']);
        $this->assertFalse($link['isDisabled']);
    }

    public function test_menu_without_file_is_disabled_and_other_types_never_keep_a_file(): void
    {
        $page = Pagina::factory()->create(['slug' => 'sinmenu', 'nombre_publico' => 'Sin menú', 'activo' => true]);
        $menu = $page->enlaces()->create(['tipo' => 'menu', 'posicion' => 0, 'activo' => true]);
        $web = $page->enlaces()->create(['tipo' => 'web', 'posicion' => 1, 'activo' => true, 'url' => 'https://a.test', 'archivo_path' => 'menus/x.pdf']);

        $this->assertNull($web->fresh()->archivo_path);
        $links = \App\Data\PaginaPublica::fromModel($page->fresh('enlaces'))->toArray()['links'];
        $this->assertTrue($links[0]['isDisabled']);
        $this->assertNull($menu->fresh()->url);
    }

    public function test_replacing_or_deleting_a_link_removes_its_old_file(): void
    {
        Storage::fake('public');
        config()->set('clicktap.media_disk', 'public');
        Storage::disk('public')->put('menus/old.pdf', '%PDF-old');
        Storage::disk('public')->put('menus/new.pdf', '%PDF-new');
        $page = Pagina::factory()->create();
        $link = $page->enlaces()->create(['tipo' => 'menu', 'posicion' => 0, 'activo' => true, 'archivo_path' => 'menus/old.pdf']);

        $link->update(['archivo_path' => 'menus/new.pdf']);
        Storage::disk('public')->assertMissing('menus/old.pdf');
        Storage::disk('public')->assertExists('menus/new.pdf');

        $link->delete();
        Storage::disk('public')->assertMissing('menus/new.pdf');
    }

    public function test_publication_sends_the_menu_file_and_removes_stale_ones(): void
    {
        config()->set([
            'services.github.api_url' => 'https://api.github.test',
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
            'clicktap.media_disk' => 'public',
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('menus/carta.pdf', '%PDF-carta');
        Http::fake(function (Request $request) {
            return match (true) {
                str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
                str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
                str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response(['content' => base64_encode(json_encode(['version' => 1, 'pages' => []]))]),
                str_contains($request->url(), '/contents/public/generated/menus/cafe') => Http::response([
                    ['type' => 'file', 'path' => 'public/generated/menus/cafe/carta.pdf'],
                    ['type' => 'file', 'path' => 'public/generated/menus/cafe/vieja.jpg'],
                ]),
                str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob-'.md5($request->body())], 201),
                str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'tree-new'], 201),
                str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'commit-new'], 201),
                str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([]),
                default => Http::response([], 404),
            };
        });
        $page = Pagina::factory()->create(['slug' => 'cafe', 'nombre_publico' => 'Café', 'activo' => true, 'logo_path' => null]);
        $page->enlaces()->create(['tipo' => 'menu', 'etiqueta' => 'Carta', 'posicion' => 0, 'activo' => true, 'archivo_path' => 'menus/carta.pdf']);

        app(PublicPagePublisher::class)->publish($page->fresh());

        $tree = collect(Http::recorded())
            ->first(fn (array $recorded): bool => str_ends_with($recorded[0]->url(), '/git/trees'))[0]->data()['tree'];
        $byPath = collect($tree)->keyBy('path');
        $this->assertNotNull($byPath['public/generated/menus/cafe/carta.pdf']['sha']);
        $this->assertArrayHasKey('sha', $byPath['public/generated/menus/cafe/vieja.jpg']);
        $this->assertNull($byPath['public/generated/menus/cafe/vieja.jpg']['sha']);
    }

    public function test_menu_file_is_not_accepted_from_the_api_with_a_foreign_path(): void
    {
        $this->assertFalse(MenuFile::isValidPath('logos/otro.jpg'));
        $this->assertFalse(MenuFile::isValidPath('menus/a b.pdf'));
        $this->assertTrue(MenuFile::isValidPath('menus/3f2a-1.jpg'));
    }
}

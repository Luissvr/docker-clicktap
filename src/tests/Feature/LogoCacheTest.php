<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use App\Support\LocalCatalogSync;
use App\Support\LogoCache;
use App\Support\PagePreviewCompiler;
use App\Support\PageTrash;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LogoCacheTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('supabase');
    }

    public function test_private_logo_is_downloaded_once_and_survives_loss_of_the_remote_object(): void
    {
        $logo = $this->png();
        Storage::disk('supabase')->put('logos/demo.png', $logo);
        $page = Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/demo.png']);
        $cache = app(LogoCache::class);
        $url = $cache->url($page);

        $this->get($url)->assertRedirect();
        $this->actingAs(User::factory()->create(['activo' => true]));
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->assertTrue($cache->isCached($page));
        $this->assertSame($logo, file_get_contents($cache->path($page)));
        $this->assertSame($url, PagePreviewCompiler::resolveLogoUrl($page->logo_path, $page));
        $this->assertSame($url, app(PageTrash::class)->coverUrl($page));

        Storage::disk('supabase')->delete('logos/demo.png');
        $this->get($url)->assertOk();
        $this->assertSame($logo, file_get_contents($cache->path($page)));
    }

    public function test_missing_logo_is_not_requested_again_until_a_retry_is_requested(): void
    {
        $page = Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/missing.png']);
        $cache = app(LogoCache::class);

        $this->assertNull($cache->path($page));
        $this->assertTrue($cache->isKnownMissing($page));
        $this->assertNull(app(PageTrash::class)->coverUrl($page));
        $this->assertNull(PagePreviewCompiler::resolveLogoUrl($page->logo_path, $page));
        Storage::disk('supabase')->put('logos/missing.png', $this->png());
        $this->assertNull($cache->path($page));
        $this->assertNotNull($cache->path($page, retryMissing: true));
        $this->assertTrue($cache->isCached($page));
        $this->assertFalse($cache->isKnownMissing($page));
    }

    public function test_logo_route_rejects_old_versions_and_inactive_users(): void
    {
        Storage::disk('supabase')->put('logos/demo.png', $this->png());
        $page = Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/demo.png']);
        $this->actingAs(User::factory()->create(['activo' => false]));
        $this->get(app(LogoCache::class)->url($page))->assertForbidden();
        $this->actingAs(User::factory()->create(['activo' => true]));
        $this->get(route('paginas.cached-logo', ['pagina' => $page, 'version' => 'obsolete']))->assertNotFound();
    }

    public function test_warm_command_reports_missing_images_without_erasing_cached_ones(): void
    {
        Storage::disk('supabase')->put('logos/available.png', $this->png());
        Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/available.png']);
        Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/absent.png']);

        $this->artisan('clicktap:cache-supabase-logos')->assertSuccessful();
        $this->artisan('clicktap:cache-supabase-logos')->assertSuccessful();
        $this->assertCount(1, Storage::disk('local')->files('logo-cache'));
    }

    public function test_catalog_worker_warms_images_after_a_successful_sync(): void
    {
        Storage::disk('supabase')->put('logos/worker.png', $this->png());
        $page = Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/worker.png']);
        $this->mock(LocalCatalogSync::class)->shouldReceive('sync')->once()->andReturn([]);

        $this->artisan('clicktap:sync-worker', ['--once' => true])->assertSuccessful();

        $this->assertTrue(app(LogoCache::class)->isCached($page));
    }

    private function png(): string
    {
        $image = imagecreatetruecolor(1, 1);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}

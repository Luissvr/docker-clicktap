<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use App\Support\LocalCatalogSync;
use App\Support\LogoCache;
use App\Support\PagePreviewCompiler;
use App\Support\PageTrash;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
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
        $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png')
            ->assertHeader('Content-Disposition', 'inline; filename="'.$page->slug.'.png"');
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

    public function test_missing_supabase_logo_uses_the_read_only_published_copy(): void
    {
        config(['services.github.repository' => 'owner/site', 'services.github.token' => 'test-token']);
        $logo = $this->png();
        Http::fake(fn (Request $request) => str_contains($request->url(), '/git/blobs/')
            ? Http::response(['encoding' => 'base64', 'content' => base64_encode($logo)])
            : Http::response(['encoding' => 'none', 'size' => strlen($logo), 'sha' => str_repeat('b', 40)]));
        $page = Pagina::factory()->create([
            'slug' => 'published-copy',
            'logo_disk' => 'supabase',
            'logo_path' => 'logos/lost.png',
            'publicacion_estado' => 'enviada',
            'publicacion_commit' => str_repeat('a', 40),
        ]);
        $cache = app(LogoCache::class);

        $this->assertNotNull($cache->path($page));
        $this->assertTrue($cache->isRecovered($page));
        $this->assertSame($logo, file_get_contents($cache->path($page)));
        Storage::disk('supabase')->assertMissing('logos/lost.png');
        Http::assertSentCount(2);

        $this->actingAs(User::factory()->create(['activo' => true]))
            ->get($cache->url($page))->assertOk();
    }

    public function test_published_branch_is_used_when_the_recorded_commit_has_no_logo(): void
    {
        config(['services.github.repository' => 'owner/site', 'services.github.token' => 'test-token', 'services.github.branch' => 'main']);
        $logo = $this->png();
        Http::fake(fn (Request $request) => str_contains($request->url(), str_repeat('a', 40))
            ? Http::response([], 404)
            : Http::response(['encoding' => 'base64', 'content' => base64_encode($logo), 'size' => strlen($logo)]));
        $page = Pagina::factory()->create([
            'slug' => 'newer-copy', 'logo_disk' => 'supabase', 'logo_path' => 'logos/lost.png',
            'publicacion_estado' => 'enviada', 'publicacion_commit' => str_repeat('a', 40),
        ]);

        $this->assertNotNull(app(LogoCache::class)->path($page));
        $this->assertTrue(app(LogoCache::class)->isRecovered($page));
        Http::assertSentCount(2);
    }

    public function test_legacy_page_can_recover_its_logo_from_the_published_branch(): void
    {
        config(['services.github.repository' => 'owner/site', 'services.github.token' => 'test-token', 'services.github.branch' => 'main']);
        $logo = $this->png();
        Http::fake(['*' => Http::response(['encoding' => 'base64', 'content' => base64_encode($logo), 'size' => strlen($logo)])]);
        $page = Pagina::factory()->create([
            'slug' => 'legacy-copy', 'logo_disk' => 'supabase', 'logo_path' => 'logos/lost.png',
            'publicacion_estado' => 'legado', 'publicacion_commit' => null,
        ]);

        $this->assertNotNull(app(LogoCache::class)->path($page));
        $this->assertTrue(app(LogoCache::class)->isRecovered($page));
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'ref=main'));
        Http::assertSentCount(1);
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

    public function test_logo_route_uses_a_short_lived_supabase_url_if_local_cache_cannot_be_written(): void
    {
        $page = Pagina::factory()->create(['logo_disk' => 'supabase', 'logo_path' => 'logos/new.png']);
        $url = app(LogoCache::class)->url($page);
        Storage::disk('supabase')->buildTemporaryUrlsUsing(fn (): string => 'https://storage.example.test/signed-logo.png');
        $cache = \Mockery::mock(new LogoCache)->makePartial();
        $cache->shouldReceive('path')->andThrow(new RuntimeException('Cache unavailable'));
        app()->instance(LogoCache::class, $cache);

        $this->actingAs(User::factory()->create(['activo' => true]))
            ->get($url)->assertRedirect('https://storage.example.test/signed-logo.png');
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

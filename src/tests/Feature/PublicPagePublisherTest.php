<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Support\PublicPagePublisher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class PublicPagePublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('publishedPageCases')]
    public function test_manual_publication_sends_the_contract_and_assets_without_deleting_existing_files(bool $alreadyPublished): void
    {
        config()->set([
            'services.github.api_url' => 'https://api.github.test',
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
            'clicktap.publication_slugs' => ['demo', 'legacy'],
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('logos/demo.jpg', 'fake-image');
        $existingPages = [['slug' => 'legacy', 'name' => 'Production unchanged', 'logo' => '/generated/logos/legacy.jpg']];
        if ($alreadyPublished) {
            $existingPages[] = ['slug' => 'demo', 'name' => 'Previous demo'];
        }
        Http::fake(function (Request $request) use ($existingPages) {
            return match (true) {
                str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']], 200),
                str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']], 200),
                str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response(['content' => base64_encode(json_encode([
                    'version' => 1,
                    'metadata' => ['source' => 'existing-production'],
                    'pages' => $existingPages,
                ]))]),
                str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob-'.md5($request->body())], 201),
                str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'tree-new'], 201),
                str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'commit-new'], 201),
                str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([], 200),
                default => Http::response([], 404),
            };
        });

        $page = Pagina::factory()->create([
            'slug' => 'demo',
            'nombre_publico' => 'Demo actualizada',
            'logo_path' => 'logos/demo.jpg',
            'activo' => true,
        ]);
        $otherSelectedPage = Pagina::factory()->create([
            'slug' => 'legacy', 'nombre_publico' => 'Local edits not ready to publish',
            'logo_path' => 'missing.jpg', 'activo' => true,
            'publicacion_estado' => 'enviada', 'publicacion_commit' => 'previous-commit',
        ]);
        $excludedPage = Pagina::factory()->create(['slug' => 'excluded-new', 'logo_path' => 'also-missing.jpg', 'activo' => true]);
        $otherSelectedPageBefore = $otherSelectedPage->fresh()->getAttributes();
        $excludedPageBefore = $excludedPage->fresh()->getAttributes();

        $this->assertSame('commit-new', app(PublicPagePublisher::class)->publish($page));
        $this->assertDatabaseHas('paginas', [
            'id' => $page->id,
            'publicacion_estado' => 'enviada',
            'publicacion_commit' => 'commit-new',
        ]);
        $this->assertSame($otherSelectedPageBefore, $otherSelectedPage->fresh()->getAttributes());
        $this->assertSame($excludedPageBefore, $excludedPage->fresh()->getAttributes());
        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/contents/src/data/generated/pages.json?ref=head'));

        $treeRequest = collect(Http::recorded())
            ->first(fn (array $recorded): bool => str_ends_with($recorded[0]->url(), '/git/trees'))[0];
        $paths = collect($treeRequest->data()['tree'])->pluck('path')->all();

        $this->assertContains('src/data/generated/pages.json', $paths);
        $this->assertContains('public/generated/logos/demo.jpg', $paths);
        $this->assertCount(2, $paths);
        $contractRequest = collect(Http::recorded())->first(fn (array $recorded): bool => str_ends_with($recorded[0]->url(), '/git/blobs') && str_contains(base64_decode($recorded[0]['content']), '"version"'))[0];
        $contract = json_decode(base64_decode($contractRequest['content']), true);
        $this->assertSame(['legacy', 'demo'], array_column($contract['pages'], 'slug'));
        $this->assertSame(['slug' => 'legacy', 'name' => 'Production unchanged', 'logo' => '/generated/logos/legacy.jpg'], $contract['pages'][0]);
        $this->assertSame('Demo actualizada', $contract['pages'][1]['name']);
        $this->assertSame(['source' => 'existing-production'], $contract['metadata']);
        $this->assertFalse(collect(Http::recorded())->contains(fn (array $recorded): bool => $recorded[0]->method() === 'DELETE'));
    }

    /** @return array<string, array{bool}> */
    public static function publishedPageCases(): array
    {
        return ['new page' => [false], 'existing page' => [true]];
    }

    #[DataProvider('invalidContracts')]
    public function test_invalid_remote_contract_is_not_overwritten(array $contract): void
    {
        config()->set([
            'services.github.api_url' => 'https://api.github.test',
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
            'clicktap.publication_slugs' => ['demo'],
        ]);
        Http::fake(function (Request $request) use ($contract) {
            return match (true) {
                str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
                str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
                str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response(['content' => base64_encode(json_encode($contract))]),
                default => Http::response([], 500),
            };
        });
        $page = Pagina::factory()->create(['slug' => 'demo', 'activo' => true]);

        try {
            app(PublicPagePublisher::class)->publish($page);
            $this->fail('An invalid remote contract must stop publication.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('catálogo actual', $exception->getMessage());
        }

        Http::assertNotSent(fn (Request $request): bool => $request->method() !== 'GET');
        $this->assertDatabaseHas('paginas', [
            'id' => $page->id, 'publicacion_estado' => 'fallida', 'publicacion_commit' => null,
        ]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function invalidContracts(): array
    {
        return [
            'unsupported version' => [['version' => 2, 'pages' => []]],
            'missing version' => [['pages' => []]],
            'pages are not a list' => [['version' => 1, 'pages' => ['demo' => ['slug' => 'demo']]]],
            'missing slug' => [['version' => 1, 'pages' => [['name' => 'Without route']]]],
            'duplicate slug' => [['version' => 1, 'pages' => [['slug' => 'demo'], ['slug' => 'demo']]]],
        ];
    }

    public function test_page_outside_publication_selection_cannot_be_sent(): void
    {
        config()->set('clicktap.publication_slugs', ['selected']);
        Http::fake();
        $page = Pagina::factory()->create(['slug' => 'excluded', 'activo' => true]);

        try {
            app(PublicPagePublisher::class)->publish($page);
            $this->fail('A page outside the selection must not be published.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('selección de publicación', $exception->getMessage());
        }

        Http::assertNothingSent();
        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'publicacion_estado' => 'sin_publicar']);
    }
}

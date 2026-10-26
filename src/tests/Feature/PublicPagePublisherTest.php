<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Support\PublicPagePublisher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PublicPagePublisherTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manual_publication_sends_the_contract_and_assets_without_deleting_existing_files(): void
    {
        config()->set([
            'services.github.api_url' => 'https://api.github.test',
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
        ]);
        Storage::fake('public');
        Storage::disk('public')->put('logos/demo.jpg', 'fake-image');
        Http::fake(function (Request $request) {
            return match (true) {
                str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']], 200),
                str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']], 200),
                str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response([
                    'content' => base64_encode(json_encode(['version' => 1, 'pages' => []])),
                ], 200),
                str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob-'.md5($request->body())], 201),
                str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'tree-new'], 201),
                str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'commit-new'], 201),
                str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([], 200),
                default => Http::response([], 404),
            };
        });

        $page = Pagina::factory()->create([
            'slug' => 'demo',
            'logo_path' => 'logos/demo.jpg',
            'activo' => true,
        ]);

        $this->assertSame('commit-new', app(PublicPagePublisher::class)->publish($page));
        $this->assertDatabaseHas('paginas', [
            'id' => $page->id,
            'publicacion_estado' => 'enviada',
            'publicacion_commit' => 'commit-new',
        ]);

        $treeRequest = collect(Http::recorded())
            ->first(fn (array $recorded): bool => str_ends_with($recorded[0]->url(), '/git/trees'))[0];
        $paths = collect($treeRequest->data()['tree'])->pluck('path')->all();

        $this->assertContains('src/data/generated/pages.json', $paths);
        $this->assertContains('public/generated/logos/demo.jpg', $paths);
        $this->assertFalse(collect(Http::recorded())->contains(fn (array $recorded): bool => $recorded[0]->method() === 'DELETE'));
    }
}

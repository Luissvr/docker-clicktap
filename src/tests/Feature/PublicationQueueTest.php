<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PublicationQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['clicktap.publication_slugs' => null]);
    }

    public function test_each_page_is_published_once_in_order_without_network_requests_in_the_web_action(): void
    {
        $this->actingAs(User::factory()->create());
        config(['services.github.token' => 'test-token', 'services.github.repository' => 'owner/site', 'services.github.branch' => 'main']);
        Http::fake(fn (Request $request) => match (true) {
            str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
            str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
            str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response([
                'content' => base64_encode(json_encode(['version' => 1, 'pages' => []])),
            ]),
            str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob']),
            str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'tree-new']),
            str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'commit-new']),
            str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([]),
            default => Http::response([], 404),
        });
        $first = Pagina::factory()->create(['activo' => true]);
        $last = Pagina::factory()->create(['activo' => true]);
        $queue = app(PublicationQueue::class);
        $queue->request($first);
        $firstRequestId = $queue->status()['pending']['id'];
        $this->assertSame('received', $queue->status()['pending']['events'][0]['stage']);
        $queue->request($last);
        $queue->request($first);
        Http::assertNothingSent();
        $this->assertSame($firstRequestId, $queue->status()['pending']['id']);
        $this->assertSame($first->id, $queue->status()['pending']['page_id']);
        $queue->work();
        $this->assertSame($last->id, $queue->status()['pending']['page_id']);
        $this->assertSame($first->id, $queue->status()['result']['page_id']);
        $this->assertSame(['received', 'started', 'repository', 'preparing', 'sending', 'repository_sent'], array_column($queue->status()['result']['events'], 'stage'));
        $this->assertNotNull($queue->status()['result']['started_at']);
        $this->assertNotNull($queue->status()['workerHeartbeatAt']);
        $this->assertSame('enviada', $first->fresh()->publicacion_estado);
        $this->assertSame('sin_publicar', $last->fresh()->publicacion_estado);
        $queue->work();
        $this->assertNull($queue->status()['pending']);
        $this->assertNull($queue->status()['running']);
        $this->assertSame($last->id, $queue->status()['result']['page_id']);
        $this->assertSame('enviada', $queue->status()['result']['state']);
        $this->assertSame('commit-new', $queue->status()['result']['commit']);
        $this->assertSame('enviada', $last->fresh()->publicacion_estado);
        $this->assertCount(2, Http::recorded(fn (Request $request): bool => str_ends_with($request->url(), '/git/commits')));
        $requests = count(Http::recorded());
        $queue->work();
        Http::assertSentCount($requests);
    }

    public function test_new_draft_outside_legacy_selection_is_published_without_changing_existing_page(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config([
            'clicktap.publication_slugs' => ['existing'],
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
        ]);
        $existing = Pagina::factory()->create([
            'slug' => 'existing', 'activo' => true, 'publicacion_estado' => 'publicada',
            'publicacion_commit' => 'existing-commit', 'publicada_en' => now(),
        ]);
        $existingBefore = $existing->fresh()->getAttributes();
        $draft = Pagina::factory()->create(['slug' => 'new-draft', 'activo' => true, 'publicacion_estado' => 'sin_publicar']);
        $existingContract = ['slug' => 'existing', 'name' => 'Production unchanged'];
        Http::fake(fn (Request $request) => match (true) {
            str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
            str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
            str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response([
                'content' => base64_encode(json_encode(['version' => 1, 'pages' => [$existingContract]])),
            ]),
            str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob']),
            str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'new-tree']),
            str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'new-commit']),
            str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([]),
            default => Http::response([], 404),
        });

        $queue = app(PublicationQueue::class);
        $queue->request($draft);
        $queue->work();

        $this->assertSame('enviada', $queue->status()['result']['state']);
        $this->assertSame('new-commit', $draft->fresh()->publicacion_commit);
        $this->assertSame($existingBefore, $existing->fresh()->getAttributes());
        $blob = collect(Http::recorded())->first(fn (array $item): bool => str_ends_with($item[0]->url(), '/git/blobs'))[0];
        $contract = json_decode(base64_decode($blob['content']), true);
        $this->assertSame($existingContract, $contract['pages'][0]);
        $this->assertSame('new-draft', $contract['pages'][1]['slug']);
    }

    public function test_existing_published_page_outside_selection_cannot_be_queued(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config(['clicktap.publication_slugs' => ['another-page']]);
        $published = Pagina::factory()->create([
            'slug' => 'published-outside-selection', 'activo' => true,
            'publicacion_estado' => 'publicada', 'publicacion_commit' => 'existing-commit', 'publicada_en' => now(),
        ]);

        try {
            app(PublicationQueue::class)->request($published);
            $this->fail('Una página ya publicada fuera de la selección entró a la cola.');
        } catch (HttpException $exception) {
            $this->assertSame(422, $exception->getStatusCode());
        }

        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
        $this->assertSame('publicada', $published->fresh()->publicacion_estado);
    }

    public function test_failed_sends_are_visible_and_not_blindly_repeated(): void
    {
        $this->actingAs(User::factory()->create());
        config(['services.github.token' => 'test-token', 'services.github.repository' => 'owner/site']);
        Http::fake(['*' => Http::response([], 500)]);
        $queue = app(PublicationQueue::class);
        $queue->request(Pagina::factory()->create(['activo' => true]));
        $queue->work();
        $this->assertSame('fallida', $queue->status()['result']['state']);
        $this->assertSame('failed', $queue->status()['result']['events'][array_key_last($queue->status()['result']['events'])]['stage']);
        $queue->work();
        Http::assertSentCount(1);
    }

    public function test_a_failed_page_does_not_discard_other_pending_pages(): void
    {
        $this->actingAs(User::factory()->create());
        config(['services.github.token' => 'test-token', 'services.github.repository' => 'owner/site']);
        Http::fake(['*' => Http::response([], 500)]);
        $first = Pagina::factory()->create(['activo' => true]);
        $second = Pagina::factory()->create(['activo' => true]);
        $queue = app(PublicationQueue::class);
        $queue->request($first);
        $queue->request($second);

        $queue->work();

        $this->assertSame('fallida', $queue->status()['result']['state']);
        $this->assertSame($first->id, $queue->status()['result']['page_id']);
        $this->assertSame($second->id, $queue->status()['pending']['page_id']);

        $queue->work();

        $this->assertSame($second->id, $queue->status()['result']['page_id']);
        $this->assertNull($queue->status()['pending']);
        Http::assertSentCount(2);
    }

    public function test_a_legacy_pending_request_is_preserved_when_another_page_is_queued(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake();
        $first = Pagina::factory()->create(['activo' => false]);
        $second = Pagina::factory()->create(['activo' => true]);
        $legacyTask = [
            'id' => 'legacy-request', 'page_id' => $first->id,
            'requested_at' => now()->toIso8601String(), 'name' => $first->nombre_publico,
        ];
        cache()->store(config('clicktap.publication_store'))->forever('publication-pending', $legacyTask);
        $queue = app(PublicationQueue::class);

        $this->assertSame($legacyTask, $queue->status()['pending']);
        $queue->request($second);
        $queue->work();

        $this->assertSame('legacy-request', $queue->status()['result']['id']);
        $this->assertSame('fallida', $queue->status()['result']['state']);
        $this->assertSame($second->id, $queue->status()['pending']['page_id']);
        Http::assertNothingSent();
    }

    public function test_inactive_page_is_not_published_after_it_is_queued(): void
    {
        $this->actingAs(User::factory()->create());
        Http::fake();
        $page = Pagina::factory()->create(['activo' => true]);
        $queue = app(PublicationQueue::class);
        $queue->request($page);
        $page->update(['activo' => false]);
        $queue->work();
        Http::assertNothingSent();
        $this->assertSame('fallida', $queue->status()['result']['state']);
    }

    public function test_rejection_replaces_pending_publication_and_removes_only_that_page_from_remote_contract(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        config([
            'services.github.token' => 'test-token',
            'services.github.repository' => 'owner/site',
            'services.github.branch' => 'main',
            'clicktap.publication_slugs' => ['another-page'],
        ]);
        $page = Pagina::factory()->create(['slug' => 'rejected-page', 'activo' => true]);
        $other = Pagina::factory()->create(['slug' => 'another-page', 'activo' => true]);
        $contract = ['version' => 1, 'metadata' => ['source' => 'current'], 'pages' => [
            ['slug' => 'rejected-page', 'name' => 'Old public content'],
            ['slug' => 'another-page', 'name' => 'Keep this page'],
        ]];
        Http::fake(fn (Request $request) => match (true) {
            str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
            str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
            str_contains($request->url(), '/contents/src/data/generated/pages.json') => Http::response(['content' => base64_encode(json_encode($contract))]),
            str_contains($request->url(), '/contents/src/pages/rejected-page.astro') => Http::response(['type' => 'file', 'sha' => 'manual-route']),
            str_ends_with($request->url(), '/git/blobs') => Http::response(['sha' => 'blob']),
            str_ends_with($request->url(), '/git/trees') => Http::response(['sha' => 'new-tree']),
            str_ends_with($request->url(), '/git/commits') => Http::response(['sha' => 'withdraw-commit']),
            str_ends_with($request->url(), '/git/refs/heads/main') => Http::response([]),
            default => Http::response([], 404),
        });

        $queue = app(PublicationQueue::class);
        config(['clicktap.publication_slugs' => null]);
        $queue->request($page);
        $page->update(['activo' => false]);
        config(['clicktap.publication_slugs' => ['another-page']]);
        $queue->requestWithdrawal($page);

        $this->assertSame(1, $queue->status()['pendingCount']);
        $this->assertSame('withdraw', $queue->status()['pending']['type']);

        $queue->work();

        $this->assertSame('retirada_enviada', $queue->status()['result']['state']);
        $this->assertSame('retirada_enviada', $page->fresh()->publicacion_estado);
        $this->assertSame('sin_publicar', $other->fresh()->publicacion_estado);
        $blob = collect(Http::recorded())->first(fn (array $item): bool => str_ends_with($item[0]->url(), '/git/blobs'))[0];
        $sent = json_decode(base64_decode($blob['content']), true);
        $this->assertSame(['another-page'], array_column($sent['pages'], 'slug'));
        $this->assertSame(['source' => 'current'], $sent['metadata']);
        $tree = collect(Http::recorded())->first(fn (array $item): bool => str_ends_with($item[0]->url(), '/git/trees'))[0];
        $this->assertContains(['path' => 'src/pages/rejected-page.astro', 'mode' => '100644', 'type' => 'blob', 'sha' => null], $tree['tree']);
    }

    public function test_worker_recovers_a_pending_withdrawal_after_the_request_is_lost(): void
    {
        $page = Pagina::factory()->create(['activo' => false, 'publicacion_estado' => 'retirada_pendiente']);
        Http::fake(['*' => Http::response([], 500)]);

        app(PublicationQueue::class)->work();

        $result = app(PublicationQueue::class)->status()['result'];
        $this->assertSame($page->id, $result['page_id']);
        $this->assertSame('withdraw', $result['type']);
        $this->assertSame('retirada_fallida', $result['state']);
    }

    public function test_reactivation_replaces_a_withdrawal_that_has_not_started(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['activo' => false]);
        $queue = app(PublicationQueue::class);
        $queue->requestWithdrawal($page);
        $page->update(['activo' => true]);

        $queue->request($page);

        $this->assertSame(1, $queue->status()['pendingCount']);
        $this->assertSame('publish', $queue->status()['pending']['type']);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PublicationQueueTest extends TestCase
{
    use RefreshDatabase;

    public function test_requests_are_combined_and_no_network_request_runs_in_the_web_action(): void
    {
        $this->actingAs(User::factory()->create());
        config(['services.github.token' => 'test-token', 'services.github.repository' => 'owner/site', 'services.github.branch' => 'main']);
        Http::fake(fn (Request $request) => match (true) {
            str_ends_with($request->url(), '/git/ref/heads/main') => Http::response(['object' => ['sha' => 'head']]),
            str_ends_with($request->url(), '/git/commits/head') => Http::response(['tree' => ['sha' => 'tree']]),
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
        $queue->request($last);
        Http::assertNothingSent();
        $this->assertSame($last->id, $queue->status()['pending']['page_id']);
        $queue->work();
        $this->assertNull($queue->status()['pending']);
        $this->assertNull($queue->status()['running']);
        $this->assertSame('enviada', $queue->status()['result']['state']);
        $this->assertSame('commit-new', $queue->status()['result']['commit']);
        $requests = count(Http::recorded());
        $queue->work();
        Http::assertSentCount($requests);
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
        $queue->work();
        Http::assertSentCount(1);
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
}

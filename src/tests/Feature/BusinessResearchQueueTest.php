<?php

namespace Tests\Feature;

use App\Support\Research\BusinessResearchQueue;
use App\Support\Research\SafeHttpFetcher;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BusinessResearchQueueTest extends TestCase
{
    private const WEBSITE = <<<'HTML'
        <html><head><title>Barbería Siete</title></head><body>
        <a href="https://www.instagram.com/barberiasiete/">Instagram</a>
        <a href="https://wa.me/56987654321">WhatsApp</a>
        </body></html>
        HTML;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => null, 'services.brave.key' => null, 'services.brave.delay_ms' => 0]);
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher(fn (string $host): array => match ($host) {
            'negocio.example' => ['93.184.216.34'],
            'interno.example' => ['10.0.0.5'],
            default => [],
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $places
     */
    public static function embed(array $places): string
    {
        $nodes = array_map(function (array $place): array {
            $node = array_fill(0, 34, null);
            $node[0] = ['0xexample', $place['name'], [-36.8, -73.0]];
            $node[1] = $place['name'];
            $node[3] = 4.5;
            $node[4] = '(120)';
            $node[11] = isset($place['website']) ? [$place['website'], 'sitio'] : null;
            $node[12] = $place['category'] ?? 'Barbería';
            $node[13] = $place['address'];
            $node[27] = $place['place_id'];
            if (isset($place['phone'])) {
                $node[7] = ['tel:'.$place['phone']];
            }

            return $node;
        }, $places);

        return '<script>initEmbed('.json_encode([null, [null, ...$nodes]], JSON_THROW_ON_ERROR).');</script>';
    }

    /**
     * @return array<string, mixed>
     */
    private function place(string $suffix = '', array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Barbería 7E'.$suffix,
            'address' => 'Barros Arana 123, Concepción, Bío Bío',
            'place_id' => 'ChIJ3V-xSmq1aZYRFcjRjq-3D_o'.$suffix,
            'website' => 'https://negocio.example/',
            'phone' => '+56412238749',
        ];
    }

    private function fakeMaps(array $places): void
    {
        Http::fake([
            'maps.google.com/*' => Http::response(self::embed($places)),
            '*negocio.example*' => Http::response(self::WEBSITE, 200, ['Content-Type' => 'text/html']),
        ]);
    }

    private function queue(): BusinessResearchQueue
    {
        return app(BusinessResearchQueue::class);
    }

    public function test_search_lists_every_business_with_that_name(): void
    {
        $this->fakeMaps([$this->place('A'), $this->place('B', ['address' => 'Ejército 1282, Concepción'])]);

        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->assertSame('buscando', $job['estado']);
        $this->assertTrue($this->queue()->work());

        $job = $this->queue()->find($job['id']);
        $this->assertSame('esperando_eleccion', $job['estado']);
        $this->assertCount(2, $job['candidatos']);
        $this->assertSame('Ejército 1282, Concepción', $job['candidatos'][1]['address']);
        $this->assertStringContainsString('place_id:ChIJ', $job['candidatos'][0]['maps_url']);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'q=Barber') && str_contains($request->url(), 'Concepci'));
    }

    public function test_search_without_results_is_reported(): void
    {
        $this->fakeMaps([]);

        $job = $this->queue()->start('user-1', 'Nombre inexistente', 'Concepción');
        $this->queue()->work();

        $this->assertSame('sin_resultados', $this->queue()->find($job['id'])['estado']);
    }

    public function test_search_retries_without_the_city_when_google_finds_nothing_with_it(): void
    {
        Http::fakeSequence('maps.google.com/*')
            ->push('<html></html>')
            ->push(self::embed([$this->place('')]));

        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepcion');
        $this->queue()->work();

        $this->assertSame('esperando_eleccion', $this->queue()->find($job['id'])['estado']);
        Http::assertSentCount(2);
    }

    public function test_google_failure_marks_the_task_as_failed_with_a_clear_message(): void
    {
        Http::fake(['maps.google.com/*' => Http::response('', 503)]);

        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();

        $job = $this->queue()->find($job['id']);
        $this->assertSame('fallida', $job['estado']);
        $this->assertStringContainsString('HTTP 503', $job['error']);
    }

    public function test_choosing_a_business_investigates_each_link_in_the_background(): void
    {
        $this->fakeMaps([$this->place()]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->assertSame('investigando', $this->queue()->find($job['id'])['estado']);

        $this->queue()->work();

        $job = $this->queue()->find($job['id']);
        $this->assertSame('lista', $job['estado']);
        $steps = collect($job['pasos'])->pluck('estado', 'clave');
        $this->assertSame('encontrado', $steps['ficha']);
        $this->assertSame('encontrado', $steps['web']);
        $this->assertSame('encontrado', $steps['instagram']);
        $this->assertSame('no_encontrado', $steps['facebook']);
        $this->assertSame('no_encontrado', $steps['tiktok']);
        $this->assertSame('encontrado', $steps['whatsapp']);

        $fields = $job['campos'];
        $this->assertSame('Barbería 7E', $fields['nombre']['valor']);
        $this->assertSame('+56412238749', $fields['telefono']['valor']);
        $this->assertSame('ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $fields['resenas']['valor']);
        $this->assertStringContainsString('place_id:ChIJ', $fields['ubicacion']['valor']);
        $this->assertSame('https://negocio.example', $fields['web']['valor']);
        $this->assertSame('https://instagram.com/barberiasiete', $fields['instagram']['valor']);
        $this->assertTrue($fields['instagram']['verificado']);
        $this->assertSame('+56987654321', $fields['whatsapp']['valor']);
        $this->assertArrayNotHasKey('facebook', $fields);

        $facebook = collect($job['pasos'])->firstWhere('clave', 'facebook');
        $this->assertStringContainsString('google.com/search', $facebook['busqueda_manual']);
    }

    public function test_a_business_without_a_website_skips_that_step_and_suggests_whatsapp_for_mobile_numbers(): void
    {
        $this->fakeMaps([$this->place('', ['website' => null, 'phone' => '+56987654321'])]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->queue()->work();

        $job = $this->queue()->find($job['id']);
        $this->assertSame('lista', $job['estado']);
        $this->assertSame('omitido', collect($job['pasos'])->firstWhere('clave', 'web')['estado']);
        $this->assertSame('+56987654321', $job['campos']['whatsapp']['valor']);
        $this->assertFalse($job['campos']['whatsapp']['verificado']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'negocio.example'));
    }

    public function test_a_google_listing_that_points_to_instagram_is_used_directly(): void
    {
        $this->fakeMaps([$this->place('', ['website' => 'https://www.instagram.com/barberia7e/?hl=es'])]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->queue()->work();

        $job = $this->queue()->find($job['id']);
        $this->assertSame('https://instagram.com/barberia7e', $job['campos']['instagram']['valor']);
        $this->assertTrue($job['campos']['instagram']['verificado']);
        $this->assertSame('omitido', collect($job['pasos'])->firstWhere('clave', 'web')['estado']);
    }

    public function test_a_failing_website_does_not_stop_the_other_steps(): void
    {
        $this->fakeMaps([$this->place('', ['website' => 'https://interno.example/'])]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->queue()->work();

        $job = $this->queue()->find($job['id']);
        $web = collect($job['pasos'])->firstWhere('clave', 'web');
        $this->assertSame('lista', $job['estado']);
        $this->assertSame('error', $web['estado']);
        $this->assertStringContainsString('red interna', $web['detalle']);
        $this->assertSame('encontrado', collect($job['pasos'])->firstWhere('clave', 'ficha')['estado']);
    }

    public function test_a_cancelled_task_is_not_processed_and_a_choice_needs_a_valid_index(): void
    {
        $this->fakeMaps([$this->place()]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->cancel($job['id']);
        $this->queue()->work();

        $this->assertSame('cancelada', $this->queue()->find($job['id'])['estado']);
        Http::assertNothingSent();

        $other = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->assertNull($this->queue()->choose($other['id'], 5));
        $this->assertSame('esperando_eleccion', $this->queue()->find($other['id'])['estado']);
    }

    public function test_work_reports_when_nothing_is_pending_and_registers_the_worker_heartbeat(): void
    {
        $this->assertFalse($this->queue()->status()['workerAvailable']);
        $this->assertFalse($this->queue()->work());
        $this->assertTrue($this->queue()->status()['workerAvailable']);
    }

    private function braveQuery(Request $request): string
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return (string) ($query['q'] ?? '');
    }

    private function braveResult(string $url, string $title, string $description = ''): array
    {
        return ['url' => $url, 'title' => $title, 'description' => $description];
    }

    private function investigateWithBrave(array $responses): array
    {
        config([
            'services.brave.key' => 'brave-key',
            'services.brave.api_url' => 'https://brave.test/res/v1/web/search',
        ]);
        Http::fake([
            'maps.google.com/*' => Http::response(self::embed([$this->place('', ['website' => null])])),
            'brave.test/*' => fn (Request $request) => $responses[$this->networkOf($this->braveQuery($request))],
        ]);

        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->queue()->work();

        return $this->queue()->find($job['id']);
    }

    private function networkOf(string $query): string
    {
        return match (true) {
            str_contains($query, 'site:instagram.com') => 'instagram',
            str_contains($query, 'site:facebook.com') => 'facebook',
            default => 'tiktok',
        };
    }

    public function test_brave_search_suggests_only_real_profiles_that_mention_the_business(): void
    {
        $job = $this->investigateWithBrave([
            'instagram' => Http::response(['web' => ['results' => [
                $this->braveResult('https://www.instagram.com/p/ABC123/', 'Barbería 7E en Instagram: "Corte nuevo"'),
                $this->braveResult('https://www.instagram.com/otro_local/', 'Otro Local (@otro_local) • Instagram'),
                $this->braveResult('https://www.instagram.com/barberia7e/?hl=es', 'Barbería 7E (@barberia7e) • Fotos y videos de Instagram'),
            ]]]),
            'facebook' => Http::response(['web' => ['results' => []]]),
            'tiktok' => Http::response(['web' => ['results' => [
                $this->braveResult('https://www.tiktok.com/@barberia7e', 'Barbería 7E (@barberia7e) | TikTok'),
            ]]]),
        ]);

        $fields = $job['campos'];
        $this->assertSame('https://instagram.com/barberia7e', $fields['instagram']['valor']);
        $this->assertFalse($fields['instagram']['verificado']);
        $this->assertSame('busqueda_web', $fields['instagram']['origen']);
        $this->assertSame('https://tiktok.com/@barberia7e', $fields['tiktok']['valor']);
        $this->assertArrayNotHasKey('facebook', $fields);

        $steps = collect($job['pasos'])->keyBy('clave');
        $this->assertSame('encontrado', $steps['instagram']['estado']);
        $this->assertStringContainsString('Brave Search', $steps['instagram']['detalle']);
        $this->assertSame('no_encontrado', $steps['facebook']['estado']);
        $this->assertStringContainsString('ni en Brave Search', $steps['facebook']['detalle']);

        Http::assertSentCount(4);
        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'brave.test')
            && $request->hasHeader('X-Subscription-Token', 'brave-key')
            && str_contains($this->braveQuery($request), 'Barbería 7E')
            && ! str_contains($this->braveQuery($request), '"')
            && str_contains($this->braveQuery($request), 'Concepción'));
    }

    public function test_brave_failures_are_reported_in_each_step_and_do_not_stop_the_investigation(): void
    {
        $limited = Http::response(['error' => 'rate limit'], 429);
        $job = $this->investigateWithBrave(['instagram' => $limited, 'facebook' => $limited, 'tiktok' => $limited]);

        $steps = collect($job['pasos'])->keyBy('clave');
        $this->assertSame('lista', $job['estado']);
        $this->assertSame('encontrado', $steps['ficha']['estado']);
        foreach (['instagram', 'facebook', 'tiktok'] as $network) {
            $this->assertSame('error', $steps[$network]['estado']);
            $this->assertStringContainsString('limitó las consultas', $steps[$network]['detalle']);
        }
    }

    public function test_without_a_brave_key_social_steps_explain_that_the_search_is_not_configured(): void
    {
        $this->fakeMaps([$this->place('', ['website' => null])]);
        $job = $this->queue()->start('user-1', 'Barbería 7E', 'Concepción');
        $this->queue()->work();
        $this->queue()->choose($job['id'], 0);
        $this->queue()->work();

        $instagram = collect($this->queue()->find($job['id'])['pasos'])->firstWhere('clave', 'instagram');
        $this->assertSame('no_encontrado', $instagram['estado']);
        $this->assertStringContainsString('no está configurada', $instagram['detalle']);
        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'brave'));
    }
}

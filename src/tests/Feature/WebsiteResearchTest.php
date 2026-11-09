<?php

namespace Tests\Feature;

use App\Exceptions\BusinessResearchException;
use App\Support\Research\OpenRouterExtractor;
use App\Support\Research\SafeHttpFetcher;
use App\Support\Research\WebsitePageReader;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class WebsiteResearchTest extends TestCase
{
    private const PAGE = <<<'HTML'
        <html><head>
        <title>Barbería Siete</title>
        <meta property="og:description" content="Cortes clásicos en el centro de Concepción">
        <meta property="og:image" content="/img/logo.png">
        <script type="application/ld+json">{"@context":"https://schema.org","@type":"LocalBusiness","name":"Barbería Siete","telephone":"+56 41 223 8749","address":{"streetAddress":"Barros Arana 123","addressLocality":"Concepción"},"openingHours":["Mo-Fr 10:00-20:00"]}</script>
        </head><body>
        <h1>Barbería Siete</h1><p>Atendemos de lunes a viernes. Llámanos al +56 9 8765 4321.</p>
        <a href="https://www.instagram.com/barberiasiete/">Instagram</a>
        <a href="https://www.facebook.com/sharer/sharer.php?u=x">Compartir</a>
        <a href="https://wa.me/56987654321">WhatsApp</a>
        <a href="tel:+56412238749">Llamar</a>
        <script>alert('ignorar')</script>
        </body></html>
        HTML;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.openrouter.key' => 'test-key',
            'services.openrouter.model' => 'openrouter/free',
            'services.openrouter.api_url' => 'https://openrouter.test/api/v1',
        ]);
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher(fn (string $host): array => match ($host) {
            'negocio.example' => ['93.184.216.34'],
            'interno.example', 'clicktap_db' => ['172.18.0.2'],
            default => [],
        }));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function blockedUrls(): array
    {
        return [
            'loopback' => ['http://127.0.0.1/'],
            'metadata de nube' => ['http://169.254.169.254/latest/meta-data'],
            'contenedor interno' => ['http://clicktap_db/'],
            'dominio a ip privada' => ['https://interno.example/'],
            'esquema no web' => ['file:///etc/passwd'],
            'puerto no estándar' => ['http://negocio.example:5432/'],
            'credenciales en url' => ['http://user:pass@negocio.example/'],
            'cgnat' => ['http://100.64.0.1/'],
        ];
    }

    #[DataProvider('blockedUrls')]
    public function test_fetcher_blocks_internal_or_non_web_addresses(string $url): void
    {
        Http::fake();

        $this->expectException(BusinessResearchException::class);
        app(SafeHttpFetcher::class)->get($url);
    }

    public function test_fetcher_revalidates_redirects_and_blocks_internal_targets(): void
    {
        Http::fake(['negocio.example/*' => Http::response('', 302, ['Location' => 'http://interno.example/admin'])]);

        try {
            app(SafeHttpFetcher::class)->get('https://negocio.example/');
            $this->fail('La redirección a una red interna debió bloquearse.');
        } catch (BusinessResearchException $exception) {
            $this->assertStringContainsString('red interna', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_fetcher_follows_public_redirect_and_returns_body(): void
    {
        Http::fake([
            'negocio.example/' => Http::response('', 301, ['Location' => '/inicio']),
            'negocio.example/inicio' => Http::response('<html>ok</html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $result = app(SafeHttpFetcher::class)->get('https://negocio.example/');

        $this->assertSame('https://negocio.example/inicio', $result['url']);
        $this->assertSame('<html>ok</html>', $result['body']);
    }

    public function test_reader_extracts_contact_data_and_ignores_share_links_and_scripts(): void
    {
        $page = app(WebsitePageReader::class)->read(self::PAGE, 'https://negocio.example/');

        $this->assertSame(['instagram' => 'https://instagram.com/barberiasiete'], $page['social']);
        $this->assertSame(['56987654321'], $page['whatsapp']);
        $this->assertContains('56412238749', $page['phones']);
        $this->assertSame('https://negocio.example/img/logo.png', $page['image']);
        $this->assertSame('Barbería Siete', $page['json_ld'][0]['name']);
        $this->assertStringNotContainsString('alert', $page['text']);
    }

    public function test_extractor_keeps_only_data_found_on_the_page(): void
    {
        $this->fakeOpenRouter([
            'nombre' => 'Barbería Siete',
            'rubro' => 'Barbería',
            'direccion' => null,
            'telefono' => '+56 9 8765 4321',
            'whatsapp' => '+56 9 1111 2222',
            'horario' => 'Lunes a viernes',
            'descripcion' => null,
            'instagram' => 'https://instagram.com/barberiasiete',
            'facebook' => 'https://facebook.com/barberia-inventada',
            'tiktok' => null,
        ]);
        $page = app(WebsitePageReader::class)->read(self::PAGE, 'https://negocio.example/');

        $result = app(OpenRouterExtractor::class)->extract($page, 'Barbería Siete');

        $this->assertTrue($result['campos']['telefono']['verificado']);
        $this->assertSame('+56987654321', $result['campos']['telefono']['valor']);
        $this->assertTrue($result['campos']['instagram']['verificado']);
        $this->assertFalse($result['campos']['rubro']['verificado']);
        $this->assertArrayNotHasKey('whatsapp', $result['campos']);
        $this->assertArrayNotHasKey('facebook', $result['campos']);
        $this->assertCount(2, $result['descartados']);
        $this->assertSame('modelo/gratis:free', $result['modelo']);

        Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer test-key')
            && $request['response_format']['type'] === 'json_schema'
            && $request['response_format']['json_schema']['strict'] === true
            && $request['provider']['require_parameters'] === true
            && $request['model'] === 'openrouter/free');
    }

    public function test_extractor_retries_with_json_object_when_no_provider_supports_the_schema(): void
    {
        Http::fakeSequence('openrouter.test/*')
            ->push(['error' => ['code' => 404, 'message' => 'No endpoints found']], 404)
            ->push($this->completion(['nombre' => 'Barbería Siete']));
        $page = app(WebsitePageReader::class)->read(self::PAGE, 'https://negocio.example/');

        $result = app(OpenRouterExtractor::class)->extract($page);

        $this->assertSame('Barbería Siete', $result['campos']['nombre']['valor']);
        Http::assertSentCount(2);
        Http::assertSent(fn (Request $request): bool => $request['response_format']['type'] === 'json_object' && ! isset($request['provider']));
    }

    public function test_rate_limit_fails_once_without_retrying(): void
    {
        Http::fake(['openrouter.test/*' => Http::response(
            ['error' => ['code' => 429, 'message' => 'Rate limit exceeded']],
            429,
            ['X-RateLimit-Reset' => '1762700400000'],
        )]);
        $page = app(WebsitePageReader::class)->read(self::PAGE, 'https://negocio.example/');

        try {
            app(OpenRouterExtractor::class)->extract($page);
            $this->fail('Debió informar el límite de consultas.');
        } catch (BusinessResearchException $exception) {
            $this->assertStringContainsString('limitó las consultas', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public function test_command_reports_page_data_without_ai_and_without_saving(): void
    {
        Http::fake(['negocio.example/*' => Http::response(self::PAGE, 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

        $this->artisan('clicktap:research-website', ['url' => 'https://negocio.example/', '--sin-ia' => true])
            ->expectsOutputToContain('https://instagram.com/barberiasiete')
            ->expectsOutputToContain('+56987654321')
            ->expectsOutputToContain('Barros Arana 123, Concepción')
            ->assertSuccessful();

        Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'openrouter'));
    }

    public function test_command_fails_clearly_for_internal_address(): void
    {
        Http::fake();

        $this->artisan('clicktap:research-website', ['url' => 'http://clicktap_db/'])
            ->expectsOutputToContain('red interna')
            ->assertFailed();
        Http::assertNothingSent();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function fakeOpenRouter(array $data): void
    {
        Http::fake(['openrouter.test/*' => Http::response($this->completion($data))]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function completion(array $data): array
    {
        return [
            'model' => 'modelo/gratis:free',
            'choices' => [['message' => ['role' => 'assistant', 'content' => json_encode($data, JSON_UNESCAPED_UNICODE)]]],
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\Pagina;
use App\Models\User;
use App\Support\ProspectMapResearch;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RouteProspectsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $manifest;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manifest = 'storage/framework/testing/route-'.bin2hex(random_bytes(8)).'.json';
        File::ensureDirectoryExists(dirname(base_path($this->manifest)));
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::delete(base_path($this->manifest));
        parent::tearDown();
    }

    public function test_google_research_captures_the_matching_business_and_unwraps_its_social_link(): void
    {
        $this->writeManifest([$this->prospect()]);
        Http::fake(['maps.google.com/*' => Http::response($this->embed(), 200)]);

        $this->artisan('clicktap:research-route', ['--file' => $this->manifest])->assertSuccessful();

        $data = json_decode(File::get(base_path($this->manifest)), true);
        $this->assertSame('matched', $data[0]['research']['status']);
        $this->assertSame('ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $data[0]['research']['place']['place_id']);
        $this->assertSame('+56961516839', $data[0]['research']['place']['phone']);
        $this->assertSame('https://www.instagram.com/altabanderaccp/?hl=es', $data[0]['research']['place']['website']);
        $this->assertSame('instagram', $data[0]['research']['place']['website_type']);
        $this->assertStringContainsString('maps.google.com', $data[0]['research']['source_url']);
        $this->assertNotEmpty($data[0]['research']['fetched_at']);

        $this->artisan('clicktap:research-route', ['--file' => $this->manifest])->assertSuccessful();
        Http::assertSentCount(1);
    }

    #[DataProvider('changedResearchInputs')]
    public function test_changed_inputs_invalidate_a_previously_matching_google_result(string $field, string $value): void
    {
        $prospect = $this->prospect();
        $prospect['search_query'] = 'Alta Bandera Tucapel 1498 Concepción Chile';
        $this->writeManifest([$prospect]);
        Http::fake(['maps.google.com/*' => Http::response($this->embed(), 200)]);
        $arguments = ['--file' => $this->manifest];
        $this->artisan('clicktap:research-route', $arguments)->assertSuccessful();
        $data = json_decode(File::get(base_path($this->manifest)), true);
        $previousFingerprint = $data[0]['research']['input_fingerprint'];
        $data[0][$field] = $value;
        $this->writeManifest($data);

        $this->artisan('clicktap:research-route', $arguments)->assertSuccessful();

        Http::assertSentCount(2);
        $updated = json_decode(File::get(base_path($this->manifest)), true);
        $this->assertNotSame($previousFingerprint, $updated[0]['research']['input_fingerprint']);
        $this->assertSame($field === 'search_query' ? 'matched' : 'needs_review', $updated[0]['research']['status']);
        if ($field !== 'search_query') {
            $this->assertArrayNotHasKey('place', $updated[0]['research']);
        }
    }

    /** @return array<string, array{string, string}> */
    public static function changedResearchInputs(): array
    {
        return [
            'business name' => ['name', 'Otro Taller'],
            'address' => ['address', 'Tucapel 999, Concepción, Bío Bío'],
            'custom query' => ['search_query', 'Alta Bandera Servicio Automotriz Tucapel 1498'],
        ];
    }

    public function test_legacy_research_without_an_input_fingerprint_is_refreshed(): void
    {
        $this->writeManifest([$this->prospect()]);
        Http::fake(['maps.google.com/*' => Http::response($this->embed(), 200)]);
        $arguments = ['--file' => $this->manifest];
        $this->artisan('clicktap:research-route', $arguments)->assertSuccessful();
        $data = json_decode(File::get(base_path($this->manifest)), true);
        unset($data[0]['research']['input_fingerprint']);
        $this->writeManifest($data);

        $this->artisan('clicktap:research-route', $arguments)->assertSuccessful();

        Http::assertSentCount(2);
        $updated = json_decode(File::get(base_path($this->manifest)), true);
        $this->assertSame('matched', $updated[0]['research']['status']);
        $this->assertNotEmpty($updated[0]['research']['input_fingerprint']);
    }

    public function test_generic_description_words_do_not_replace_the_distinctive_business_name(): void
    {
        $result = (new ProspectMapResearch)->parse(
            $this->embed(name: 'Serviteca del Aceite Otro Dueño'),
            'Rebecar La Serviteca del Aceite',
            'Tucapel 1498, Concepción, Bío Bío',
        );

        $this->assertSame('needs_review', $result['status']);
        $this->assertArrayNotHasKey('place', $result);
    }

    public function test_extra_google_name_details_and_accents_preserve_verified_business_matches(): void
    {
        $parser = new ProspectMapResearch;
        foreach ([
            ['Rebecar · La Serviteca del Aceite', 'REBECAR desde 1988 LA SERVITECA DEL ACEITE'],
            ['Moreno Muñoz', 'Moreno Munoz Cristian Dario'],
            ['Full Center Car Service', 'Full Center Car Service - Taller Mecánico en Concepción'],
        ] as [$expected, $actual]) {
            $result = $parser->parse($this->embed(name: $actual), $expected, 'Tucapel 1498, Concepción, Bío Bío');
            $this->assertSame('matched', $result['status']);
        }
    }

    public function test_an_address_only_a_different_business_or_a_different_branch_is_never_accepted(): void
    {
        $parser = new ProspectMapResearch;
        foreach ([
            $this->embed(name: 'Tucapel 1498', category: null),
            $this->embed(name: 'Otro Taller'),
            $this->embed(address: 'Tucapel 999, Concepción, Bío Bío'),
            $this->embed(address: 'Tucapel 1498, Temuco, Araucanía'),
        ] as $html) {
            $result = $parser->parse($html, 'Alta Bandera Servicio Automotriz', 'Tucapel 1498, Concepción, Bío Bío');
            $this->assertSame('needs_review', $result['status']);
            $this->assertArrayNotHasKey('place', $result);
        }
        $this->assertSame('unavailable', $parser->parse('<html>Consent required</html>', 'Alta Bandera', 'Tucapel 1498, Concepción')['status']);
    }

    public function test_network_failure_is_recorded_and_reported_without_inventing_business_data(): void
    {
        $this->writeManifest([$this->prospect()]);
        Http::fake(['maps.google.com/*' => Http::response('Try again', 429)]);

        $this->artisan('clicktap:research-route', ['--file' => $this->manifest])->assertFailed();

        $data = json_decode(File::get(base_path($this->manifest)), true);
        $this->assertSame('unavailable', $data[0]['research']['status']);
        $this->assertSame('HTTP 429', $data[0]['research']['reason']);
        $this->assertArrayNotHasKey('place', $data[0]['research']);
    }

    public function test_creation_uses_the_requested_batch_and_template_without_copying_client_content(): void
    {
        $actor = User::factory()->create();
        $template = Pagina::factory()->create([
            'slug' => 'taller-80s', 'tema' => 'comercio', 'color_fondo' => '#fddede',
            'color_boton' => '#f01010', 'logo_path' => 'logos/other-business.png', 'activo' => true,
            'contenido_extra' => ['tagline' => 'Otra empresa', 'transfer_logo' => 'bancoestado'],
        ]);
        $template->enlaces()->create([
            'tipo' => 'transferencia', 'texto_copia' => 'Datos de otra empresa', 'posicion' => 0, 'activo' => true,
        ]);
        $research = (new ProspectMapResearch)->parse($this->embed(), 'Alta Bandera Servicio Automotriz', 'Tucapel 1498, Concepción');
        $first = $this->prospect();
        $first['research'] = $research;
        $second = $this->prospect();
        $second['order'] = 2;
        $second['slug'] = 'otro-prospecto';
        $this->writeManifest([$first, $second]);
        $arguments = ['--file' => $this->manifest, '--actor' => $actor->id, '--limit' => 1];

        $this->artisan('clicktap:create-route-pages', [...$arguments, '--dry-run' => true])->assertSuccessful();
        $this->assertSame(1, Pagina::query()->count());
        $this->artisan('clicktap:create-route-pages', $arguments)->assertSuccessful();

        $page = Pagina::query()->where('slug', 'alta-bandera')->with('enlaces', 'local')->firstOrFail();
        $this->assertFalse($page->activo);
        $this->assertSame('comercio', $page->tema);
        $this->assertSame('#fddede', $page->color_fondo);
        $this->assertSame('#f01010', $page->color_boton);
        $this->assertNull($page->logo_path);
        $this->assertNull($page->local->cliente_id);
        $this->assertSame('Servicio automotriz', $page->contenido_extra['tagline']);
        $this->assertSame('+56961516839', $page->telefono_publico);
        $this->assertSame(['review', 'instagram', 'location'], $page->enlaces->pluck('tipo')->all());
        $this->assertStringContainsString('placeid=ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $page->enlaces->first()->url);
        $this->assertStringContainsString('query_place_id=ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $page->enlaces->last()->url);
        $this->assertTrue($template->fresh()->activo);
        $this->assertDatabaseMissing('paginas', ['slug' => 'otro-prospecto']);

        $this->artisan('clicktap:create-route-pages', [...$arguments, '--actor' => $actor->email])->assertSuccessful();
        $this->assertSame(2, Pagina::query()->count());
    }

    public function test_unmatched_google_candidates_do_not_produce_review_buttons(): void
    {
        $actor = User::factory()->create();
        Pagina::factory()->create(['slug' => 'taller-80s']);
        $prospect = $this->prospect();
        $prospect['research'] = (new ProspectMapResearch)->parse(
            $this->embed(name: 'Tucapel 1498', category: null), $prospect['name'], $prospect['address'],
        );
        $this->writeManifest([$prospect]);

        $this->artisan('clicktap:create-route-pages', [
            '--file' => $this->manifest, '--actor' => $actor->id,
        ])->assertSuccessful();

        $page = Pagina::query()->where('slug', 'alta-bandera')->with('enlaces')->firstOrFail();
        $this->assertSame(['location'], $page->enlaces->pluck('tipo')->all());
        $this->assertNull($page->contenido_extra['prospect_route']['place_id']);
        $this->assertStringNotContainsString('query_place_id', $page->enlaces->first()->url);
    }

    /** @return array<string, mixed> */
    private function prospect(): array
    {
        return [
            'order' => 1, 'slug' => 'alta-bandera', 'name' => 'Alta Bandera Servicio Automotriz',
            'address' => 'Tucapel 1498, Concepción, Bío Bío', 'category' => 'Servicio automotriz',
            'template' => 'taller-80s', 'sources' => ['https://example.test/business'],
        ];
    }

    /** @param list<array<string, mixed>> $data */
    private function writeManifest(array $data): void
    {
        File::put(base_path($this->manifest), json_encode($data, JSON_THROW_ON_ERROR));
    }

    private function embed(
        string $name = 'ALTA BANDERA SERVICIO AUTOMOTRIZ',
        string $address = 'Tucapel 1498, 4061061 Concepción, Bío Bío',
        ?string $category = 'Taller de automóviles',
    ): string {
        $place = array_fill(0, 34, null);
        $place[0] = ['0xexample', $name.' - '.$address, [-36.8145079, -73.0524954]];
        $place[1] = $name;
        $place[2] = explode(', ', $address);
        $place[3] = 5;
        $place[4] = '6 reseñas';
        $place[11] = ['/url?q=https://www.instagram.com/altabanderaccp/%3Fhl%3Des&sa=U', 'instagram.com'];
        $place[12] = $category;
        $place[13] = $address;
        $place[27] = 'ChIJ3V-xSmq1aZYRFcjRjq-3D_o';
        $place[33] = ['tel:+56961516839'];

        return '<script>initEmbed('.json_encode([null, [null, $place]], JSON_THROW_ON_ERROR).');</script>';
    }
}

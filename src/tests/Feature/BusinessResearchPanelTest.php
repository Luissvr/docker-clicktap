<?php

namespace Tests\Feature;

use App\Filament\Resources\PaginaResource\Pages\CreatePagina;
use App\Models\Pagina;
use App\Models\User;
use App\Support\Research\BusinessResearchQueue;
use App\Support\Research\SafeHttpFetcher;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

class BusinessResearchPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.openrouter.key' => null, 'services.brave.key' => null]);
        $this->app->instance(SafeHttpFetcher::class, new SafeHttpFetcher(fn (string $host): array => match ($host) {
            'negocio.example', 'cdn.negocio.example' => ['93.184.216.34'],
            default => [],
        }));
        Http::fake([
            'maps.google.com/*' => Http::response(BusinessResearchQueueTest::embed([
                [
                    'name' => 'Barbería 7E', 'address' => 'Barros Arana 123, Concepción, Bío Bío',
                    'place_id' => 'ChIJ3V-xSmq1aZYRFcjRjq-3D_o', 'website' => 'https://negocio.example/', 'phone' => '+56412238749',
                ],
                [
                    'name' => 'Barbería 7E Sucursal', 'address' => 'Ejército 1282, Concepción, Bío Bío',
                    'place_id' => 'ChIJOtraSucursal1234567890', 'phone' => '+56412345678',
                ],
            ])),
            'cdn.negocio.example/*' => Http::response(base64_decode('iVBORw0KGgoAAAANSUhEUgAAACAAAAAgCAIAAAD8GO2jAAAAFklEQVR4nGP8z8Dwn4EIwESMolGFpAAAI4QBD3ruWWEAAAAASUVORK5CYII='), 200, ['Content-Type' => 'image/png']),
            '*negocio.example*' => Http::response('<html><head><link rel="apple-touch-icon" sizes="180x180" href="https://cdn.negocio.example/logo.png"></head><body><a href="https://www.instagram.com/barberiasiete/">IG</a><a href="https://wa.me/56987654321">WA</a></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);
    }

    private function actAsAdministrator(): User
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        RateLimiter::clear('business-research:'.$user->getKey());

        return $user;
    }

    private function runWorker(): void
    {
        app(BusinessResearchQueue::class)->work();
    }

    public function test_create_page_shows_the_name_search_box(): void
    {
        $this->actAsAdministrator();

        $this->get('/admin/paginas/create')
            ->assertOk()
            ->assertSee('Buscar negocio en internet')
            ->assertSee('Nombre del negocio')
            ->assertSee('Ciudad');
    }

    public function test_search_box_is_not_offered_when_editing(): void
    {
        $this->actAsAdministrator();
        $page = Pagina::factory()->create();

        $this->get('/admin/paginas/'.$page->getRouteKey().'/edit')
            ->assertOk()
            ->assertDontSee('Buscar negocio en internet');
    }

    public function test_typing_a_name_queues_the_search_and_shows_progress(): void
    {
        $this->actAsAdministrator();

        Livewire::test(CreatePagina::class)
            ->assertSet('researchCity', 'Concepción')
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch')
            ->assertSet('researchError', null)
            ->assertSee('Buscando «Barbería 7E» en Google…')
            ->assertSeeHtml('wire:poll.2s="refreshResearch"');
        Http::assertNothingSent();
    }

    public function test_the_user_sees_every_match_and_chooses_the_right_one(): void
    {
        $this->actAsAdministrator();

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();

        $component->call('refreshResearch')
            ->assertSee('Elige el negocio correcto (2 resultados)')
            ->assertSee('Barros Arana 123')
            ->assertSee('Ejército 1282')
            ->call('chooseResearchCandidate', 1)
            ->assertSee('Barbería 7E Sucursal')
            ->assertSee('Ficha de Google');
    }

    public function test_applying_the_result_fills_fields_review_and_links(): void
    {
        $this->actAsAdministrator();

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 0);
        $this->runWorker();
        $component->call('refreshResearch')
            ->assertSee('Datos encontrados')
            ->assertSet('researchSelected.telefono', true)
            ->assertSet('researchSelected.instagram', true)
            ->assertSet('researchSelected.whatsapp', true)
            ->call('applyResearch')
            ->assertSet('researchJobId', null)
            ->assertSet('data.nombre_publico', 'Barbería 7E')
            ->assertSet('data.slug', 'barberia-7e')
            ->assertSet('data.direccion_publica', 'Barros Arana 123, Concepción, Bío Bío')
            ->assertSet('data.telefono_publico', '+56412238749')
            ->assertSet('data.contenido_extra.place_id', 'ChIJ3V-xSmq1aZYRFcjRjq-3D_o')
            ->assertSet('data.contenido_extra.tagline', 'Barbería');

        $links = collect($component->get('data.enlaces'))->mapWithKeys(fn (array $link): array => [$link['tipo'] => $link['url'] ?? null]);
        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $links['review']);
        $this->assertStringContainsString('place_id:ChIJ3V-xSmq1aZYRFcjRjq-3D_o', $links['location']);
        $this->assertSame('https://instagram.com/barberiasiete', $links['instagram']);
        $this->assertSame('https://wa.me/56987654321', $links['whatsapp']);
        $this->assertSame('https://negocio.example', $links['web']);
    }

    private function researchWithoutSocialProfiles(): Testable
    {
        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 1);
        $this->runWorker();

        return $component->call('refreshResearch');
    }

    public function test_a_missing_network_offers_a_field_to_paste_the_profile(): void
    {
        $this->actAsAdministrator();

        $this->researchWithoutSocialProfiles()
            ->assertSee('Pega el enlace del perfil o su @usuario')
            ->assertSeeHtml('wire:click="useManualProfile(\'instagram\')"')
            ->assertSeeHtml('wire:click="useManualProfile(\'tiktok\')"');
    }

    public function test_a_pasted_profile_is_validated_saved_and_applied_to_the_form(): void
    {
        $this->actAsAdministrator();

        $component = $this->researchWithoutSocialProfiles()
            ->set('researchManual.instagram', 'https://www.instagram.com/7ebarber.shop/?hl=es')
            ->call('useManualProfile', 'instagram')
            ->assertSet('researchManualErrors', [])
            ->assertSet('researchSelected.instagram', true)
            ->assertSee('Ingresado por ti')
            ->assertSee('Ingresado manualmente por ti')
            ->call('applyResearch');

        $links = collect($component->get('data.enlaces'))->mapWithKeys(fn (array $link): array => [$link['tipo'] => $link['url'] ?? null]);
        $this->assertSame('https://instagram.com/7ebarber.shop', $links['instagram']);
    }

    public function test_a_username_alone_is_enough(): void
    {
        $this->actAsAdministrator();

        $component = $this->researchWithoutSocialProfiles()
            ->set('researchManual.tiktok', '@7ebarber')
            ->call('useManualProfile', 'tiktok')
            ->call('applyResearch');

        $links = collect($component->get('data.enlaces'))->mapWithKeys(fn (array $link): array => [$link['tipo'] => $link['url'] ?? null]);
        $this->assertSame('https://tiktok.com/@7ebarber', $links['tiktok']);
    }

    public function test_a_post_or_another_network_is_rejected_with_a_clear_message(): void
    {
        $this->actAsAdministrator();

        $component = $this->researchWithoutSocialProfiles()
            ->set('researchManual.instagram', 'https://www.instagram.com/p/ABC123/')
            ->call('useManualProfile', 'instagram')
            ->assertSee('Eso no parece el perfil de Enlace de Instagram')
            ->assertSet('researchSelected.instagram', null);

        $component->set('researchManual.instagram', 'https://www.facebook.com/7ebarber')
            ->call('useManualProfile', 'instagram')
            ->assertSet('researchSelected.instagram', null);
        $this->assertArrayNotHasKey('instagram', app(BusinessResearchQueue::class)->find($component->get('researchJobId'))['campos']);
    }

    public function test_the_paste_field_is_not_offered_for_other_steps_or_unknown_networks(): void
    {
        $this->actAsAdministrator();

        $component = $this->researchWithoutSocialProfiles()
            ->set('researchManual.whatsapp', '@7ebarber')
            ->call('useManualProfile', 'whatsapp');

        $this->assertArrayNotHasKey('whatsapp', array_filter(
            app(BusinessResearchQueue::class)->find($component->get('researchJobId'))['campos'],
            fn (array $field): bool => $field['origen'] === 'manual',
        ));
    }

    public function test_the_logo_found_on_the_website_is_downloaded_and_loaded_into_the_form(): void
    {
        Storage::fake('public');
        config(['clicktap.media_disk' => 'public']);
        $this->actAsAdministrator();

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 0);
        $this->runWorker();
        $component->call('refreshResearch')
            ->assertSee('Logo del local')
            ->assertSet('researchSelected.logo', false)
            ->set('researchSelected.logo', true)
            ->call('applyResearch');

        $state = $component->get('data.logo_path');
        $this->assertIsArray($state);
        $path = reset($state);
        $this->assertStringStartsWith('logos/', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_a_logo_that_is_not_an_image_is_reported_without_breaking_the_rest(): void
    {
        Storage::fake('public');
        config(['clicktap.media_disk' => 'public']);
        $this->actAsAdministrator();
        Http::preventStrayRequests();
        Http::swap(new HttpFactory);
        Http::fake([
            'maps.google.com/*' => Http::response(BusinessResearchQueueTest::embed([[
                'name' => 'Barbería 7E', 'address' => 'Barros Arana 123, Concepción, Bío Bío',
                'place_id' => 'ChIJ3V-xSmq1aZYRFcjRjq-3D_o', 'website' => 'https://negocio.example/', 'phone' => '+56412238749',
            ]])),
            'cdn.negocio.example/*' => Http::response('<html>no soy una imagen</html>', 200, ['Content-Type' => 'text/html']),
            '*negocio.example*' => Http::response('<html><head><link rel="apple-touch-icon" href="https://cdn.negocio.example/logo.png"></head><body></body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 0);
        $this->runWorker();
        $component->call('refreshResearch')
            ->set('researchSelected.logo', true)
            ->call('applyResearch')
            ->assertSet('data.telefono_publico', '+56412238749')
            ->assertSet('data.logo_path', fn ($value): bool => blank($value));
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_existing_values_are_kept_unless_the_user_selects_them(): void
    {
        $this->actAsAdministrator();

        $component = Livewire::test(CreatePagina::class)
            ->set('data.direccion_publica', 'Dirección escrita a mano')
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 0);
        $this->runWorker();
        $component->call('refreshResearch')
            ->assertSet('researchSelected.direccion', false)
            ->set('researchSelected.telefono', false)
            ->call('applyResearch')
            ->assertSet('data.direccion_publica', 'Dirección escrita a mano')
            ->assertSet('data.telefono_publico', null);
    }

    public function test_a_name_that_is_too_short_is_rejected_without_queueing(): void
    {
        $this->actAsAdministrator();

        Livewire::test(CreatePagina::class)
            ->set('researchName', 'ab')
            ->call('startResearch')
            ->assertSet('researchJobId', null)
            ->assertSet('researchError', 'Escribe el nombre del negocio (entre 3 y 120 caracteres).');
    }

    public function test_no_matches_show_a_message_and_allow_searching_again(): void
    {
        $this->actAsAdministrator();
        Http::swap(new HttpFactory);
        Http::fake(['maps.google.com/*' => Http::response('<html></html>')]);

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Nombre inexistente')
            ->call('startResearch');
        $this->runWorker();
        $component->call('refreshResearch')
            ->assertSee('Google no encontró negocios con ese nombre')
            ->call('discardResearch')
            ->assertSet('researchJobId', null)
            ->assertSee('Nombre del negocio');
    }

    public function test_searches_are_limited_per_user_per_hour(): void
    {
        $user = $this->actAsAdministrator();
        for ($attempt = 0; $attempt < 20; $attempt++) {
            RateLimiter::hit('business-research:'.$user->getKey(), 3600);
        }

        Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch')
            ->assertSet('researchJobId', null)
            ->assertSet('researchError', fn (?string $error): bool => str_contains((string) $error, 'límite'));
    }

    public function test_another_administrator_cannot_see_someone_elses_search(): void
    {
        $this->actAsAdministrator();
        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $jobId = $component->get('researchJobId');
        $this->runWorker();

        $this->actingAs(User::factory()->create());
        $this->assertNotNull(app(BusinessResearchQueue::class)->find($jobId));
        $other = Livewire::test(CreatePagina::class);
        $this->assertNull($other->instance()->researchJob());
    }

    public function test_the_search_state_cannot_be_tampered_with_from_the_browser(): void
    {
        $this->actAsAdministrator();

        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(CreatePagina::class)->set('researchJobId', 'otra-busqueda');
    }

    public function test_search_results_are_offered_with_a_preview_and_can_be_confirmed(): void
    {
        config([
            'services.brave.key' => 'brave-key', 'services.brave.delay_ms' => 0,
            'services.brave.api_url' => 'https://brave.test/res/v1/web/search',
        ]);
        Http::swap(new HttpFactory);
        Http::fake([
            'maps.google.com/*' => Http::response(BusinessResearchQueueTest::embed([[
                'name' => 'Barbería 7E', 'address' => 'Ejército 1282, Concepción, Bío Bío', 'place_id' => 'ChIJOtraSucursal1234567890',
            ]])),
            'brave.test/*' => Http::response(['web' => ['results' => [[
                'url' => 'https://www.instagram.com/barberia7e/', 'title' => 'Barbería 7E (@barberia7e) • Instagram',
                'description' => 'Cortes y barba en Concepción', 'thumbnail' => ['src' => 'https://imgs.search.brave.com/foto.jpg'],
            ]]]]),
        ]);
        $this->actAsAdministrator();

        $component = Livewire::test(CreatePagina::class)
            ->set('researchName', 'Barbería 7E')
            ->call('startResearch');
        $this->runWorker();
        $component->call('chooseResearchCandidate', 0);
        $this->runWorker();
        $component->call('refreshResearch')
            ->assertSee('Posibles perfiles')
            ->assertSee('Cortes y barba en Concepción')
            ->assertSeeHtml('src="https://imgs.search.brave.com/foto.jpg"')
            ->assertSeeHtml('wire:click="useSocialCandidate(\'instagram\', 0)"')
            ->assertSet('researchSelected.instagram', false)
            ->call('useSocialCandidate', 'instagram', 9)
            ->assertSet('researchSelected.instagram', false)
            ->call('useSocialCandidate', 'instagram', 0)
            ->assertSet('researchSelected.instagram', true)
            ->assertSee('Confirmado por ti')
            ->assertDontSee('Posibles perfiles')
            ->call('applyResearch');

        $links = collect($component->get('data.enlaces'))->mapWithKeys(fn (array $link): array => [$link['tipo'] => $link['url'] ?? null]);
        $this->assertSame('https://instagram.com/barberia7e', $links['instagram']);
    }
}

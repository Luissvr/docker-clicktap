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
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
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
            'negocio.example' => ['93.184.216.34'],
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
            '*negocio.example*' => Http::response('<html><body><a href="https://www.instagram.com/barberiasiete/">IG</a><a href="https://wa.me/56987654321">WA</a></body></html>', 200, ['Content-Type' => 'text/html']),
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
}

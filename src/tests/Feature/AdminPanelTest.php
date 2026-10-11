<?php

namespace Tests\Feature;

use App\Filament\Resources\PaginaResource\Pages\CreatePagina;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_active_administrator_can_open_the_content_screens(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/admin/clientes')->assertOk();
        $this->get('/admin/local-negocios')->assertOk();
        $this->get('/admin/paginas')->assertOk();
        $this->get('/admin/paginas/create')->assertOk();
    }

    public function test_administrator_can_create_a_demo_page_without_a_customer(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Local Demo',
                'slug' => 'local-demo',
                'activo' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseHas('locales', ['nombre_interno' => 'Local Demo', 'cliente_id' => null]);
        $this->assertDatabaseHas('paginas', ['slug' => 'local-demo', 'activo' => false]);
    }

    public function test_preview_shows_an_empty_link_without_a_broken_destination(): void
    {
        $user = User::factory()->create();
        $page = Pagina::factory()->create(['slug' => 'transferencia-demo']);
        $page->enlaces()->create([
            'tipo' => 'transferencia',
            'etiqueta' => 'Datos de transferencia',
            'url' => null,
            'posicion' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('paginas.preview', $page))
            ->assertOk()
            ->assertSee('Datos de transferencia', false)
            ->assertDontSee('href=""', false);
    }

    public function test_preview_renders_a_copy_button_when_configured(): void
    {
        $user = User::factory()->create();
        $page = Pagina::factory()->create(['slug' => 'copiar-demo']);
        $page->enlaces()->create([
            'tipo' => 'transferencia',
            'etiqueta' => 'Copiar datos',
            'url' => null,
            'posicion' => 0,
            'copiar_texto' => true,
            'texto_copia' => 'Banco Demo, cuenta 123456',
        ]);

        $this->actingAs($user)
            ->get(route('paginas.preview', $page))
            ->assertOk()
            ->assertSee('data-copy-text="Banco Demo, cuenta 123456"', false)
            ->assertSee('<svg', false)
            ->assertSee('class="link-button"', false)
            ->assertSee('<button', false);
    }

    public function test_preview_applies_the_selected_background_theme(): void
    {
        $user = User::factory()->create();
        $page = Pagina::factory()->create(['slug' => 'floral-demo', 'tema' => 'floral']);

        $this->actingAs($user)
            ->get(route('paginas.preview', $page))
            ->assertOk()
            ->assertSee('<body class="theme-floral">', false);
    }

    public function test_preview_can_render_as_a_mobile_device(): void
    {
        $user = User::factory()->create();
        $page = Pagina::factory()->create(['slug' => 'movil-demo']);

        $this->actingAs($user)
            ->get(route('paginas.preview', ['pagina' => $page, 'device' => 'mobile']))
            ->assertOk()
            ->assertSee('preview-device--mobile', false);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_existing_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_dashboard_uses_the_same_filament_panel_as_content_screens(): void
    {
        $user = User::factory()->make(['activo' => true]);

        $this->actingAs($user)->get('/admin')
            ->assertOk()
            ->assertSee('fi-sidebar', false)
            ->assertSee('clicktap-horizontal.png', false)
            ->assertSee('Bienvenido a ClickTap')
            ->assertSee('/admin/paginas', false)
            ->assertSee('/admin/local-negocios', false)
            ->assertSee('/admin/clientes', false)
            ->assertDontSee('ct-shell', false);
    }

    public function test_demo_navigation_is_removed(): void
    {
        $user = User::factory()->make(['activo' => true]);

        $this->actingAs($user)->get('/admin/ui?section=locales')->assertNotFound();
    }

    public function test_public_frontend_does_not_load_admin_assets(): void
    {
        $this->get('/')->assertDontSee('build/admin/');
    }

    public function test_sidebar_displays_flat_navigation_without_removed_screens(): void
    {
        $user = User::factory()->make(['activo' => true]);

        $response = $this->actingAs($user)->get('/admin');

        $response->assertOk()
            ->assertSee('fi-sidebar', false)
            ->assertSee('clicktap-logo-transparent.png', false)
            ->assertSee('clicktap-waves.png', false)
            ->assertSee('Contraer menú')
            ->assertSee('ct-sidebar-toggle', false)
            ->assertSee('Resumen')
            ->assertSee('Clientes')
            ->assertSee('Locales de negocio')
            ->assertSee('Páginas')
            ->assertSee('Stock de tarjetas')
            ->assertSee('Contabilidad')
            ->assertSee('Publicaciones')
            ->assertSee('Configuración de precios')
            ->assertDontSee('Placas NFC')
            ->assertDontSee('Solicitudes')
            ->assertDontSee('Cobros')
            ->assertSee('/admin/clientes', false)
            ->assertSee('/admin/local-negocios', false)
            ->assertSee('/admin/paginas', false)
            ->assertSee('/admin/stock-tarjetas', false)
            ->assertSee('/admin/contabilidad', false)
            ->assertDontSee('/admin/placas-nfc', false)
            ->assertDontSee('/admin/solicitudes-cambio', false)
            ->assertDontSee('/admin/cobros', false)
            ->assertSee('/admin/publicaciones-sitio', false)
            ->assertSee('/admin/configuracion-precios', false);
    }

    public function test_stock_tarjetas_and_contabilidad_screens_load_in_admin_panel(): void
    {
        $user = User::factory()->make(['activo' => true]);

        $this->actingAs($user)->get('/admin/stock-tarjetas')
            ->assertOk()
            ->assertSee('fi-sidebar', false)
            ->assertSee('Stock de tarjetas');

        $this->actingAs($user)->get('/admin/contabilidad')
            ->assertOk()
            ->assertSee('fi-sidebar', false)
            ->assertSee('Contabilidad');
    }

    public function test_theme_css_contains_sidebar_black_background_and_multiline_rules(): void
    {
        $themeCss = file_get_contents(public_path('build/admin/theme.css'));

        $this->assertStringContainsString('#101010', $themeCss);
        $this->assertStringContainsString('fi-sidebar', $themeCss);
        $this->assertStringContainsString('fi-sidebar-item-label', $themeCss);
        $this->assertStringContainsString('#b6ff00', $themeCss);
    }
}

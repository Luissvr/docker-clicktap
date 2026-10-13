<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class AdminUiTest extends TestCase
{
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
}

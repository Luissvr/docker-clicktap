<?php

namespace Tests\Feature;

use App\Filament\Resources\ClienteResource\Pages\EditCliente;
use App\Filament\Resources\LocalNegocioResource\Pages\EditLocal;
use App\Filament\Resources\PaginaResource\Pages\EditPagina;
use App\Models\Cliente;
use App\Models\LocalNegocio;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class SharedCatalogTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partners_share_clients_and_locales_and_changes_record_the_actor(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $this->actingAs($first);
        $client = Cliente::factory()->create(['nombre' => 'Cliente compartido']);
        $local = LocalNegocio::factory()->create(['cliente_id' => $client->id]);
        $this->assertSame($first->id, $client->creado_por);
        $this->actingAs($second);
        $this->get('/admin/clientes')->assertOk()->assertSee('Cliente compartido');
        $this->get('/admin/local-negocios')->assertOk()->assertSee($local->nombre_interno);
        Livewire::test(EditCliente::class, ['record' => $client->id])
            ->fillForm(['nombre' => 'Editado por socio B'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Editado por socio B', $client->fresh()->nombre);
        $this->assertSame($second->id, $client->fresh()->modificado_por);
    }

    public function test_stale_client_form_cannot_overwrite_another_partner(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Cliente::factory()->create();
        $form = Livewire::test(EditCliente::class, ['record' => $client->id]);
        $client->update(['nombre' => 'Cambio más reciente']);
        $form->fillForm(['nombre' => 'Cambio obsoleto'])->call('save')->assertHasFormErrors(['nombre']);
        $this->assertSame('Cambio más reciente', $client->fresh()->nombre);
    }

    public function test_stale_local_form_cannot_overwrite_another_partner(): void
    {
        $this->actingAs(User::factory()->create());
        $local = LocalNegocio::factory()->create();
        $form = Livewire::test(EditLocal::class, ['record' => $local->id]);
        $local->update(['nombre_interno' => 'Nuevo nombre']);
        $form->fillForm(['nombre_interno' => 'Viejo nombre'])->call('save')->assertHasFormErrors(['nombre_interno']);
        $this->assertSame('Nuevo nombre', $local->fresh()->nombre_interno);
    }

    public function test_stale_page_form_cannot_overwrite_content_or_links(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Pagina::factory()->create(['slug' => 'conflicto-demo']);
        $link = $page->enlaces()->create(['tipo' => 'web', 'etiqueta' => 'Original', 'url' => 'https://example.com', 'posicion' => 0]);
        $form = Livewire::test(EditPagina::class, ['record' => $page->id]);
        $page->update(['nombre_publico' => 'Versión nueva']);
        $form->fillForm(['nombre_publico' => 'Versión vieja', 'enlaces' => []])
            ->call('save')->assertHasFormErrors(['nombre_publico']);
        $this->assertSame('Versión nueva', $page->fresh()->nombre_publico);
        $this->assertDatabaseHas('enlaces_pagina', ['id' => $link->id]);
    }

    public function test_same_form_can_save_twice_after_successful_revision_update(): void
    {
        $this->actingAs(User::factory()->create());
        $client = Cliente::factory()->create();
        Livewire::test(EditCliente::class, ['record' => $client->id])
            ->fillForm(['nombre' => 'Primera edición'])->call('save')->assertHasNoFormErrors()
            ->fillForm(['nombre' => 'Segunda edición'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Segunda edición', $client->fresh()->nombre);
        $this->assertSame(3, (int) $client->fresh()->revision);
    }

    public function test_guest_and_inactive_account_cannot_read_private_catalog_or_preview(): void
    {
        $page = Pagina::factory()->create();
        $this->get('/admin/clientes')->assertRedirect();
        $this->get(route('paginas.preview', $page))->assertRedirect();
        $this->actingAs(User::factory()->create(['activo' => false]));
        $this->get('/admin/clientes')->assertForbidden();
        $this->get(route('paginas.preview', $page))->assertForbidden();
    }

    public function test_logo_copy_is_verified_and_does_not_delete_local_original(): void
    {
        Storage::fake('public');
        Storage::fake('supabase');
        Storage::disk('public')->put('logos/test.png', 'fake-image-content');
        $page = Pagina::factory()->create(['logo_path' => 'logos/test.png']);
        $this->artisan('clicktap:migrate-logos')->assertSuccessful();
        Storage::disk('supabase')->assertMissing('logos/test.png');
        $this->artisan('clicktap:migrate-logos', ['--apply' => true])->assertSuccessful();
        $this->assertSame('supabase', $page->fresh()->logo_disk);
        Storage::disk('public')->assertExists('logos/test.png');
        $this->assertSame('fake-image-content', Storage::disk('supabase')->get('logos/test.png'));
    }

    public function test_logo_migration_refuses_to_replace_a_different_remote_file(): void
    {
        Storage::fake('public');
        Storage::fake('supabase');
        Storage::disk('public')->put('logos/test.png', 'local-image');
        Storage::disk('supabase')->put('logos/test.png', 'other-image');
        $page = Pagina::factory()->create(['logo_path' => 'logos/test.png']);
        $this->artisan('clicktap:migrate-logos', ['--apply' => true])->assertFailed();
        $this->assertSame('public', $page->fresh()->logo_disk);
        $this->assertSame('other-image', Storage::disk('supabase')->get('logos/test.png'));
    }

    public function test_new_pages_use_remote_disk_when_configured(): void
    {
        config(['clicktap.media_disk' => 'supabase']);
        $this->assertSame('supabase', Pagina::factory()->create()->logo_disk);
    }
}

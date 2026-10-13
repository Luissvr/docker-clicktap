<?php

namespace Tests\Feature;

use App\Filament\Resources\ClienteResource\Pages\CreateCliente;
use App\Filament\Resources\ClienteResource\Pages\EditCliente;
use App\Filament\Resources\ClienteResource\Pages\ListClientes;
use App\Filament\Resources\PaginaResource\Pages\CreatePagina;
use App\Filament\Resources\PaginaResource\Pages\EditPagina;
use App\Models\Cliente;
use App\Models\Pagina;
use App\Models\User;
use App\Support\CatalogConnection;
use App\Support\LocalCatalogSync;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class CatalogConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('CLICKTAP_SYNC_TEST_DATABASE') !== 'clicktap_test') {
            $this->markTestSkipped('Requiere la base PostgreSQL aislada clicktap_test.');
        }
        $local = config('database.connections.local');
        $local['database'] = 'clicktap_test';
        config([
            'database.connections.local' => $local,
            'database.connections.supabase' => array_merge($local, ['search_path' => 'catalog_test_source']),
            'database.default' => 'supabase',
        ]);
        DB::purge('local');
        DB::purge('supabase');
        DB::connection('local')->statement('DROP SCHEMA IF EXISTS clicktap_cache CASCADE');
        DB::connection('local')->statement('DROP SCHEMA IF EXISTS catalog_test_source CASCADE');
        DB::connection('local')->statement('CREATE SCHEMA catalog_test_source');
        $this->artisan('migrate', ['--database' => 'supabase', '--force' => true])->assertSuccessful();
        $this->actingAs(User::factory()->create());
        Cliente::factory()->create(['nombre' => 'Cliente local']);
        Pagina::factory()->create(['slug' => 'pagina-prueba']);
        (new LocalCatalogSync)->sync();
        config(['clicktap.local_catalog' => true]);
        CatalogConnection::configure();
    }

    protected function tearDown(): void
    {
        if (getenv('CLICKTAP_SYNC_TEST_DATABASE') === 'clicktap_test') {
            foreach (['catalog', 'supabase'] as $connection) {
                DB::purge($connection);
            }
            DB::connection('local')->statement('DROP SCHEMA IF EXISTS clicktap_cache CASCADE');
            DB::connection('local')->statement('DROP SCHEMA IF EXISTS catalog_test_source CASCADE');
            DB::purge('local');
        }
        parent::tearDown();
    }

    public function test_lists_and_live_form_hydration_use_the_local_copy(): void
    {
        $client = Cliente::where('nombre', 'Cliente local')->firstOrFail();
        DB::connection('supabase')->table('clientes')->where('id', $client->id)->update(['nombre' => 'Cambio remoto']);
        $this->assertSame('Cliente local', Cliente::findOrFail($client->id)->nombre);
        // Un PDO remoto inaccesible detecta también lecturas forzadas por Livewire.
        CatalogConnection::connection()->setPdo(fn () => throw new RuntimeException('No debe consultar el origen'));
        $this->get('/admin/clientes')->assertOk()->assertSee('Cliente local')->assertDontSee('Cambio remoto');
        Livewire::test(EditCliente::class, ['record' => $client->id])
            ->fillForm(['nombre' => 'Borrador sin guardar'])
            ->assertSet('data.nombre', 'Borrador sin guardar');
    }

    public function test_create_and_edit_are_committed_remotely_then_visible_locally(): void
    {
        Livewire::test(CreateCliente::class)->fillForm(['nombre' => 'Nuevo cliente'])
            ->call('create')->assertHasNoFormErrors();
        $id = DB::connection('supabase')->table('clientes')->where('nombre', 'Nuevo cliente')->value('id');
        $this->assertNotNull($id);
        $this->assertSame('Nuevo cliente', DB::connection('local')->table('clientes')->where('id', $id)->value('nombre'));
        Livewire::test(EditCliente::class, ['record' => $id])
            ->fillForm(['nombre' => 'Edición confirmada'])->call('save')->assertHasNoFormErrors();
        $this->assertSame('Edición confirmada', DB::connection('supabase')->table('clientes')->where('id', $id)->value('nombre'));
        $this->assertSame('Edición confirmada', DB::connection('local')->table('clientes')->where('id', $id)->value('nombre'));
    }

    public function test_remote_revision_conflict_preserves_the_draft_and_remote_data(): void
    {
        $client = Cliente::where('nombre', 'Cliente local')->firstOrFail();
        $form = Livewire::test(EditCliente::class, ['record' => $client->id]);
        DB::connection('supabase')->table('clientes')->where('id', $client->id)
            ->update(['nombre' => 'Otro socio', 'revision' => 2]);
        $form->fillForm(['nombre' => 'Mi borrador'])->call('save')->assertHasFormErrors(['nombre'])
            ->assertSet('data.nombre', 'Mi borrador');
        $this->assertSame('Otro socio', DB::connection('supabase')->table('clientes')->where('id', $client->id)->value('nombre'));
        $this->assertSame('Cliente local', DB::connection('local')->table('clientes')->where('id', $client->id)->value('nombre'));
    }

    public function test_page_creation_and_link_changes_reach_both_databases(): void
    {
        Livewire::test(CreatePagina::class)->fillForm(['nombre_publico' => 'Página nueva', 'slug' => 'pagina-nueva', 'activo' => false])
            ->call('create')->assertHasNoFormErrors();
        $page = Pagina::where('slug', 'pagina-nueva')->firstOrFail();
        Livewire::test(EditPagina::class, ['record' => $page->id])
            ->fillForm(['enlaces' => [['tipo' => 'web', 'etiqueta' => 'Nuevo enlace', 'url' => 'https://example.com', 'activo' => true, 'copiar_texto' => false]]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(1, DB::connection('local')->table('enlaces_pagina')->where('pagina_id', $page->id)->count());
        $this->assertSame(1, DB::connection('supabase')->table('enlaces_pagina')->where('pagina_id', $page->id)->count());
        Livewire::test(EditPagina::class, ['record' => $page->id])->fillForm(['enlaces' => []])->call('save')->assertHasNoFormErrors();
        $this->assertSame(0, DB::connection('local')->table('enlaces_pagina')->where('pagina_id', $page->id)->count());
        $this->assertSame(0, DB::connection('supabase')->table('enlaces_pagina')->where('pagina_id', $page->id)->count());
    }

    public function test_failed_remote_transaction_cannot_change_the_local_copy(): void
    {
        try {
            CatalogConnection::transaction(function (): void {
                Cliente::where('nombre', 'Cliente local')->update(['nombre' => 'No confirmar']);
                throw new RuntimeException('Cancelar');
            });
            $this->fail('Debe revertir la transacción.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cancelar', $exception->getMessage());
        }
        $this->assertTrue(DB::connection('local')->table('clientes')->where('nombre', 'Cliente local')->exists());
        $this->assertTrue(DB::connection('supabase')->table('clientes')->where('nombre', 'Cliente local')->exists());
    }

    public function test_local_refresh_failure_does_not_report_the_remote_save_as_failed(): void
    {
        $this->mock(LocalCatalogSync::class)->shouldReceive('sync')->once()->andThrow(new RuntimeException('Local no disponible'));
        Livewire::test(CreateCliente::class)->fillForm(['nombre' => 'Guardado remoto'])
            ->call('create')->assertHasNoFormErrors()->assertNotified('Guardado en Supabase');
        $this->assertSame(1, DB::connection('supabase')->table('clientes')->where('nombre', 'Guardado remoto')->count());
        $this->assertSame(0, DB::connection('local')->table('clientes')->where('nombre', 'Guardado remoto')->count());
    }

    public function test_unavailable_remote_save_keeps_the_form_and_local_data(): void
    {
        $client = Cliente::where('nombre', 'Cliente local')->firstOrFail();
        $form = Livewire::test(EditCliente::class, ['record' => $client->id])->fillForm(['nombre' => 'Mi borrador']);
        CatalogConnection::connection()->setPdo(fn () => throw new \PDOException('Sin conexión'));
        $form->call('save')->assertNotified('No se pudo confirmar el guardado')->assertSet('data.nombre', 'Mi borrador');
        $this->assertSame('Cliente local', DB::connection('local')->table('clientes')->where('id', $client->id)->value('nombre'));
        $this->assertSame('Cliente local', DB::connection('supabase')->table('clientes')->where('id', $client->id)->value('nombre'));
    }

    public function test_sync_does_not_overwrite_an_unsaved_form_and_next_save_checks_remote_revision(): void
    {
        $client = Cliente::where('nombre', 'Cliente local')->firstOrFail();
        $list = Livewire::test(ListClientes::class)->assertSee('Cliente local');
        $form = Livewire::test(EditCliente::class, ['record' => $client->id])->fillForm(['nombre' => 'Mi borrador']);
        DB::connection('supabase')->table('clientes')->where('id', $client->id)->update(['nombre' => 'Otro socio', 'revision' => 2]);
        (new LocalCatalogSync)->sync();
        $list->dispatch('catalog-synced')->assertSee('Otro socio');
        $form->call('$refresh')->assertSet('data.nombre', 'Mi borrador');
        $form->call('save')->assertHasFormErrors(['nombre']);
        $this->assertSame('Otro socio', DB::connection('supabase')->table('clientes')->where('id', $client->id)->value('nombre'));
    }
}

<?php

namespace Tests\Feature;

use App\Filament\Resources\ClienteResource\Pages\ListClientes;
use App\Filament\Resources\PaginaResource\Pages\ListPaginas;
use App\Models\Cliente;
use App\Models\LocalNegocio;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ResponsiveContentTablesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_clients_offer_creation_and_contact_actions_in_the_compact_layout(): void
    {
        Cliente::factory()->create(['nombre_contacto' => null, 'telefono_contacto' => null]);

        $this->actingAs(User::factory()->create(['activo' => true]))
            ->get('/admin/clientes')
            ->assertOk()
            ->assertSee('id="ct-page-title"', false)
            ->assertSee('x-teleport="#ct-page-title"', false)
            ->assertSee('Crear cliente')
            ->assertSee('/admin/clientes/create', false)
            ->assertSee('Añadir contacto')
            ->assertSee('Sin contacto')
            ->assertSee('Sin teléfono')
            ->assertSee('Filtros');
    }

    public function test_client_filters_combine_with_phone_search(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $complete = Cliente::factory()->create([
            'nombre_contacto' => 'Ana Pérez',
            'telefono_contacto' => '+56 9 1234 5678',
        ]);
        $missing = Cliente::factory()->create(['nombre_contacto' => null, 'telefono_contacto' => null]);
        $empty = Cliente::factory()->create(['nombre_contacto' => '', 'telefono_contacto' => '']);
        LocalNegocio::factory()->create(['cliente_id' => $complete->id]);

        Livewire::test(ListClientes::class)
            ->filterTable('contacto_completo', true)
            ->assertCanSeeTableRecords([$complete])
            ->assertCanNotSeeTableRecords([$missing, $empty])
            ->filterTable('contacto_completo', false)
            ->assertCanSeeTableRecords([$missing, $empty])
            ->assertCanNotSeeTableRecords([$complete])
            ->resetTableFilters()
            ->filterTable('con_locales', false)
            ->assertCanSeeTableRecords([$missing, $empty])
            ->assertCanNotSeeTableRecords([$complete])
            ->filterTable('con_locales', true)
            ->searchTable('1234 5678')
            ->assertCanSeeTableRecords([$complete])
            ->assertCanNotSeeTableRecords([$missing, $empty]);
    }

    public function test_pages_keep_all_details_and_actions_with_long_content(): void
    {
        $page = Pagina::factory()->create([
            'nombre_publico' => str_repeat('Nombre público extenso ', 10),
            'slug' => str_repeat('ruta-larga-', 20).'final',
            'activo' => false,
        ]);

        $response = $this->actingAs(User::factory()->create(['activo' => true]))
            ->get('/admin/paginas');

        $response->assertOk()
            ->assertSee($page->nombre_publico)
            ->assertSee('Local: '.$page->local->nombre_interno)
            ->assertSee('Ruta: /'.$page->slug)
            ->assertSee('Enlaces: 0')
            ->assertSee('No está lista para publicar')
            ->assertSee('Sin publicar')
            ->assertSee('Última edición:')
            ->assertSee('Ordenar por')
            ->assertSee('50 por página')
            ->assertDontSee('https://clicktap.app/'.$page->slug, false)
            ->assertSee('Vista previa')
            ->assertSee('Vista móvil')
            ->assertSee('Editar');

        file_put_contents(storage_path('app/responsive-paginas.html'), $response->getContent());
    }

    public function test_pages_link_to_the_public_site_only_after_publication_is_sent(): void
    {
        Pagina::factory()->create(['slug' => 'pagina-publicada', 'publicacion_estado' => 'enviada']);
        Pagina::factory()->create(['slug' => 'pagina-borrador', 'publicacion_estado' => 'sin_publicar']);

        $this->actingAs(User::factory()->create(['activo' => true]))
            ->get('/admin/paginas')
            ->assertOk()
            ->assertSee('https://clicktap.app/pagina-publicada', false)
            ->assertDontSee('https://clicktap.app/pagina-borrador', false);
    }

    public function test_pages_start_with_fifty_records_per_page(): void
    {
        Pagina::factory()->count(51)->create();
        $this->actingAs(User::factory()->create(['activo' => true]));

        $list = Livewire::test(ListPaginas::class);

        $list->assertSet('tableRecordsPerPage', 50);
        $this->assertCount(50, $list->instance()->getTableRecords());
        $this->assertSame(51, $list->instance()->getTableRecords()->total());

        $list->set('tableRecordsPerPage', 25);
        $this->assertCount(25, $list->instance()->getTableRecords());
    }

    public function test_pages_can_be_ordered_by_name_from_the_toolbar(): void
    {
        $zeta = Pagina::factory()->create(['nombre_publico' => 'Zeta']);
        $alfa = Pagina::factory()->create(['nombre_publico' => 'Alfa']);
        $this->actingAs(User::factory()->create(['activo' => true]));

        $list = Livewire::test(ListPaginas::class)->set('paginaSort', 'nombre_publico:asc');

        $this->assertSame($alfa->id, $list->instance()->getTableRecords()->first()->id);
        $this->assertSame($zeta->id, $list->instance()->getTableRecords()->last()->id);
    }

    public function test_locals_show_full_addresses_and_missing_relations(): void
    {
        $client = Cliente::factory()->create(['nombre' => str_repeat('ClienteSinEspacios', 12)]);
        $local = LocalNegocio::factory()->create([
            'cliente_id' => $client->id,
            'nombre_interno' => str_repeat('LocalSinEspacios', 12),
            'direccion_operativa' => str_repeat('Dirección muy extensa ', 10),
        ]);
        Pagina::factory()->create(['local_id' => $local->id]);
        LocalNegocio::factory()->create(['cliente_id' => null, 'direccion_operativa' => null]);

        $response = $this->actingAs(User::factory()->create(['activo' => true]))
            ->get('/admin/local-negocios')
            ->assertOk()
            ->assertSee($local->nombre_interno)
            ->assertSee('Cliente: '.$client->nombre)
            ->assertSee('Dirección: '.$local->direccion_operativa)
            ->assertSee('Cliente: Sin asignar')
            ->assertSee('Dirección: Sin completar')
            ->assertSee('Página: Creada')
            ->assertSee('Página: Sin crear')
            ->assertSee('Editar datos');

        file_put_contents(storage_path('app/responsive-locales.html'), $response->getContent());
    }
}

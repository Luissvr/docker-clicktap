<?php

namespace Tests\Feature;

use App\Filament\Resources\PaginaResource\Pages\CreatePagina;
use App\Filament\Resources\PaginaResource\Pages\EditPagina;
use App\Models\Pagina;
use App\Models\User;
use App\Support\LogoPalette;
use App\Support\PagePreviewCompiler;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    public function test_page_form_rejects_more_than_150_description_words(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Local Demo',
                'slug' => 'local-demo',
                'descripcion' => implode(' ', array_fill(0, 151, 'palabra')),
            ])
            ->call('create')
            ->assertHasFormErrors(['descripcion']);

        $this->assertDatabaseCount('paginas', 0);
    }

    public function test_recommended_colors_can_be_restored_from_a_saved_logo(): void
    {
        Storage::fake('public');
        $image = imagecreatetruecolor(2, 2);
        imagefilledrectangle($image, 0, 0, 1, 1, imagecolorallocate($image, 30, 120, 220));
        ob_start();
        imagepng($image);
        $contents = ob_get_clean();
        imagedestroy($image);
        Storage::disk('public')->put('logos/colors.png', $contents);

        $page = Pagina::factory()->create([
            'logo_disk' => 'public',
            'logo_path' => 'logos/colors.png',
        ]);

        $this->actingAs(User::factory()->create());

        Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()])
            ->fillForm([
                'color_fondo' => '#000000',
                'color_texto' => '#000000',
                'color_boton' => '#000000',
                'color_texto_boton' => '#000000',
            ])
            ->callFormComponentAction('apariencia', 'restaurarColoresRecomendados')
            ->assertFormSet(LogoPalette::fromContents($contents));
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

    public function test_page_can_be_saved_with_chosen_typography_and_link_name(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Barbería Vintage',
                'slug' => 'barberia-vintage',
                'tema' => 'default',
                'tipografia' => 'poppins',
                'activo' => false,
                'enlaces' => [
                    [
                        'tipo' => 'whatsapp',
                        'nombre' => 'WhatsApp Citas',
                        'etiqueta' => 'Agendar por WhatsApp',
                        'url' => 'https://wa.me/56912345678',
                        'activo' => true,
                        'copiar_texto' => false,
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('paginas', [
            'slug' => 'barberia-vintage',
            'tipografia' => 'poppins',
        ]);

        $this->assertDatabaseHas('enlaces_pagina', [
            'tipo' => 'whatsapp',
            'nombre' => 'WhatsApp Citas',
            'etiqueta' => 'Agendar por WhatsApp',
        ]);

        $pagina = Pagina::where('slug', 'barberia-vintage')->firstOrFail();
        $this->get(route('paginas.preview', $pagina))
            ->assertOk()
            ->assertSee('fonts.googleapis.com/css2?family=Poppins', false)
            ->assertSee("'Poppins'", false)
            ->assertSee('Agendar por WhatsApp', false);
    }

    public function test_review_link_is_created_from_its_own_place_id(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(CreatePagina::class)
            ->fillForm(['nombre_publico' => 'Reseñas', 'slug' => 'resenas'])
            ->callFormComponentAction('enlaces', 'add');
        $key = array_key_first($component->get('data.enlaces'));

        $component->set("data.enlaces.{$key}.tipo", 'review')
            ->set("data.enlaces.{$key}.place_id", 'ChIJ_Resena_123')
            ->assertSee('Place ID de Google')
            ->assertDontSee('Destino (URL)')
            ->assertDontSee('Copiar texto al pulsar')
            ->call('create')
            ->assertHasNoFormErrors();

        $link = Pagina::where('slug', 'resenas')->firstOrFail()->enlaces()->sole();
        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ_Resena_123', $link->url);
        $this->assertFalse($link->copiar_texto);
    }

    public function test_existing_review_place_id_can_be_edited_without_the_page_place_id(): void
    {
        $this->actingAs(User::factory()->create());
        $page = Pagina::factory()->create([
            'tema' => 'comercio',
            'contenido_extra' => ['place_id' => 'ChIJ_Obsoleto', 'tagline' => 'Una frase'],
        ]);
        $link = $page->enlaces()->create([
            'tipo' => 'review', 'url' => 'https://search.google.com/local/writereview?placeid=ChIJ_Anterior',
            'posicion' => 0, 'activo' => true,
        ]);
        $component = Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()]);
        $key = array_key_first($component->get('data.enlaces'));

        $component->assertSet("data.enlaces.{$key}.place_id", 'ChIJ_Anterior')
            ->set("data.enlaces.{$key}.place_id", 'ChIJ_Nuevo')
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ_Nuevo', $link->fresh()->url);
        $this->assertArrayNotHasKey('place_id', $page->fresh()->contenido_extra);
        $this->assertSame('Una frase', $page->fresh()->contenido_extra['tagline']);
    }

    public function test_empty_transfer_can_be_saved_active_and_renders_as_a_button(): void
    {
        $this->actingAs(User::factory()->create());
        Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Transferencia pendiente', 'slug' => 'transferencia-pendiente',
                'enlaces' => [['tipo' => 'transferencia', 'activo' => true, 'texto_copia' => null]],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $page = Pagina::where('slug', 'transferencia-pendiente')->firstOrFail();
        $link = $page->enlaces()->sole();
        $this->assertTrue($link->activo);
        $this->assertTrue($link->copiar_texto);
        $this->get(route('paginas.preview', $page))->assertOk()
            ->assertSee('type="button" data-copy-text=""', false)
            ->assertDontSee('<span class="link-button link-button--disabled">', false);
        $html = PagePreviewCompiler::compile([], $page);
        $this->assertStringContainsString('type="button" data-copy-text=""', $html);
    }

    public function test_both_appearance_modes_show_description_and_only_explicit_review_links(): void
    {
        foreach (['comercio', 'default'] as $theme) {
            $html = PagePreviewCompiler::compile([
                'tema' => $theme, 'descripcion' => "Descripción del negocio\nSegunda línea",
                'contenido_extra' => ['place_id' => 'ChIJ_Obsoleto'],
                'enlaces' => [['tipo' => 'review', 'place_id' => 'ChIJ_Enlace', 'activo' => true]],
            ]);
            $this->assertStringContainsString('<body class="theme-'.$theme.'">', $html);
            $this->assertStringContainsString("Descripción del negocio\nSegunda línea", $html);
            $this->assertStringContainsString('writereview?placeid=ChIJ_Enlace', $html);
            $this->assertStringNotContainsString('ChIJ_Obsoleto', $html);
        }
    }

    public function test_add_link_button_adds_an_editable_link_on_create_page(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreatePagina::class)
            ->assertSet('data.enlaces', [])
            ->callFormComponentAction('enlaces', 'add')
            ->assertCount('data.enlaces', 1)
            ->assertSee('Nuevo enlace');
    }

    public function test_order_mode_selects_one_link_at_a_time_and_restores_collapse_arrows(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Orden manual',
                'slug' => 'orden-manual',
            ])
            ->callFormComponentAction('enlaces', 'add')
            ->callFormComponentAction('enlaces', 'add')
            ->callFormComponentAction('enlaces', 'add')
            ->assertFormComponentActionHidden('enlaces', 'seleccionarEnlaceOrden');

        $keys = array_keys($component->get('data.enlaces'));
        $component->set('data.enlaces', [
            $keys[0] => ['tipo' => 'web', 'nombre' => 'Primero', 'url' => 'https://example.com/uno', 'activo' => true, 'copiar_texto' => false],
            $keys[1] => ['tipo' => 'web', 'nombre' => 'Segundo', 'url' => 'https://example.com/dos', 'activo' => true, 'copiar_texto' => false],
            $keys[2] => ['tipo' => 'web', 'nombre' => 'Tercero', 'url' => 'https://example.com/tres', 'activo' => true, 'copiar_texto' => false],
        ]);
        $repeater = $component->instance()->form->getComponent('data.enlaces');
        $this->assertTrue($repeater->isCollapsible());
        $this->assertTrue($repeater->isCollapsed());
        $this->assertTrue($repeater->getAction('collapseAll')->isHidden());
        $this->assertTrue($repeater->getAction('expandAll')->isHidden());

        $component
            ->callFormComponentAction('pagina-enlaces-section', 'ordenarEnlaces')
            ->assertSet('isOrderingLinks', true)
            ->assertFormComponentActionVisible('enlaces', 'seleccionarEnlaceOrden');

        $html = $component->html();
        $this->assertStringContainsString('selectedLink: null', $html);
        $this->assertStringContainsString('selectedLink = null; $dispatch(`repeater-collapse`, `data.enlaces`)', $html);
        $this->assertStringContainsString('moveLink(-1)', $html);
        $this->assertStringContainsString('moveLink(1)', $html);
        $this->assertStringContainsString('await this.$wire.moveSelectedLink(this.selectedLink, direction)', $html);
        $this->assertStringContainsString('this.$dispatch(`repeater-collapse`, `data.enlaces`)', $html);
        $this->assertStringContainsString('selectedLink = selectedLink ===', $html);
        $this->assertStringNotContainsString('selectedLink === &amp;#039;', $html);
        $this->assertStringContainsString('selectedLink === atob(`', $html);
        $this->assertStringContainsString('ct-link-ordering', $html);

        $this->assertFalse($component->instance()->form->getComponent('data.enlaces')->isCollapsible());
        $this->assertTrue($component->instance()->form->getComponent('data.enlaces')->isCollapsed());

        $selectionAction = $component->instance()->form->getComponent('data.enlaces')
            ->getAction('seleccionarEnlaceOrden')(['item' => $keys[1]]);
        $this->assertNull($selectionAction->getLivewireClickHandler());
        $this->assertStringContainsString('selectedLink = selectedLink ===', $selectionAction->getExtraAttributes()['x-on:click']);
        $this->assertStringContainsString('selectedLink ===', $selectionAction->getExtraAttributes()['x-bind:aria-checked']);

        $component->call('moveSelectedLink', $keys[1], -1);
        $this->assertSame(['Segundo', 'Primero', 'Tercero'], array_column($component->get('data.enlaces'), 'nombre'));
        $orderedHtml = $component->html();
        $this->assertTrue(strpos($orderedHtml, 'x-sortable-item="'.$keys[1].'"') < strpos($orderedHtml, 'x-sortable-item="'.$keys[0].'"'));

        $component->call('moveSelectedLink', $keys[1], -1);
        $this->assertSame(['Segundo', 'Primero', 'Tercero'], array_column($component->get('data.enlaces'), 'nombre'));

        $component->call('moveSelectedLink', $keys[1], 1);
        $this->assertSame(['Primero', 'Segundo', 'Tercero'], array_column($component->get('data.enlaces'), 'nombre'));

        $component->call('moveSelectedLink', $keys[1], 1);
        $this->assertSame(['Primero', 'Tercero', 'Segundo'], array_column($component->get('data.enlaces'), 'nombre'));

        $component->call('moveSelectedLink', $keys[1], 1);
        $this->assertSame(['Primero', 'Tercero', 'Segundo'], array_column($component->get('data.enlaces'), 'nombre'));

        $component->call('moveSelectedLink', $keys[1], -1);
        $component->call('moveSelectedLink', $keys[1], -1);
        $this->assertSame(['Segundo', 'Primero', 'Tercero'], array_column($component->get('data.enlaces'), 'nombre'));

        $component
            ->callFormComponentAction('pagina-enlaces-section', 'ordenarEnlaces')
            ->assertSet('isOrderingLinks', false)
            ->assertFormComponentActionHidden('enlaces', 'seleccionarEnlaceOrden');

        $this->assertStringNotContainsString('ct-link-ordering', $component->html());

        $this->assertTrue($component->instance()->form->getComponent('data.enlaces')->isCollapsible());
        $this->assertTrue($component->instance()->form->getComponent('data.enlaces')->isCollapsed());

        $component->call('create')->assertHasNoFormErrors();

        $page = Pagina::query()->where('slug', 'orden-manual')->firstOrFail();
        $this->assertSame(['Segundo', 'Primero', 'Tercero'], $page->enlaces()->pluck('nombre')->all());
    }

    public function test_create_page_can_delete_multiple_selected_links_and_collapse_items(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreatePagina::class)
            ->fillForm([
                'nombre_publico' => 'Página con enlaces',
                'slug' => 'pagina-con-enlaces',
                'enlaces' => [
                    ['tipo' => 'web', 'nombre' => 'Primero', 'url' => 'https://example.com/uno', 'activo' => true, 'copiar_texto' => false],
                    ['tipo' => 'web', 'nombre' => 'Segundo', 'url' => 'https://example.com/dos', 'activo' => true, 'copiar_texto' => false],
                    ['tipo' => 'web', 'nombre' => 'Tercero', 'url' => 'https://example.com/tres', 'activo' => true, 'copiar_texto' => false],
                ],
            ])
            ->assertFormComponentActionHidden('enlaces', 'delete')
            ->assertFormComponentActionExists('enlaces', 'collapse');

        $keys = array_keys($component->get('data.enlaces'));

        $component
            ->callFormComponentAction('pagina-enlaces-section', 'eliminarEnlaces', data: [
                'enlaces' => [$keys[0], $keys[2]],
            ])
            ->assertCount('data.enlaces', 1)
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseCount('enlaces_pagina', 1);
        $this->assertDatabaseHas('enlaces_pagina', ['nombre' => 'Segundo']);
    }

    public function test_edit_page_can_delete_one_selected_link(): void
    {
        $page = Pagina::factory()->create();
        $first = $page->enlaces()->create([
            'tipo' => 'web',
            'nombre' => 'Conservar',
            'url' => 'https://example.com/conservar',
            'posicion' => 0,
            'activo' => true,
            'copiar_texto' => false,
        ]);
        $second = $page->enlaces()->create([
            'tipo' => 'web',
            'nombre' => 'Eliminar',
            'url' => 'https://example.com/eliminar',
            'posicion' => 1,
            'activo' => true,
            'copiar_texto' => false,
        ]);
        $this->actingAs(User::factory()->create());

        Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()])
            ->callFormComponentAction('pagina-enlaces-section', 'eliminarEnlaces', data: [
                'enlaces' => ['record-'.$second->getKey()],
            ])
            ->assertCount('data.enlaces', 1)
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('enlaces_pagina', ['id' => $first->getKey()]);
        $this->assertDatabaseMissing('enlaces_pagina', ['id' => $second->getKey()]);
    }

    public function test_preview_falls_back_to_link_name_when_button_label_is_empty(): void
    {
        $user = User::factory()->create();
        $page = Pagina::factory()->create([
            'slug' => 'galeria-arte',
            'tipografia' => 'playfair-display',
        ]);

        $page->enlaces()->create([
            'tipo' => 'instagram',
            'nombre' => 'Instagram Oficial',
            'etiqueta' => null,
            'url' => 'https://instagram.com/clicktap',
            'posicion' => 0,
            'activo' => true,
        ]);

        $this->actingAs($user)
            ->get(route('paginas.preview', $page))
            ->assertOk()
            ->assertSee('fonts.googleapis.com/css2?family=Playfair+Display', false)
            ->assertSee('Instagram Oficial', false);
    }

    public function test_page_preview_compiler_compiles_mini_view_with_logo_typography_and_background(): void
    {
        $html = PagePreviewCompiler::compile([
            'nombre_publico' => 'Café Santiago',
            'descripcion' => 'Especialidad y pastelería',
            'direccion_publica' => 'Toulouse, Hualpén, Bío Bío',
            'telefono_publico' => '9 4222 4861',
            'correo_publico' => 'munos1517@gmail.com',
            'tema' => 'floral',
            'tipografia' => 'montserrat',
            'color_fondo' => '#fffdfa',
            'color_texto' => '#1a1a1a',
            'color_boton' => '#2e5b3c',
            'color_texto_boton' => '#ffffff',
            'logo_path' => 'https://clicktap.app/logo-sample.png',
            'enlaces' => [
                [
                    'tipo' => 'whatsapp',
                    'nombre' => 'WhatsApp Pedidos',
                    'etiqueta' => 'Pedir ahora',
                    'url' => 'https://wa.me/56900000000',
                    'activo' => true,
                ],
            ],
        ]);

        $this->assertStringContainsString('theme-floral', $html);
        $this->assertStringContainsString('fonts.googleapis.com/css2?family=Montserrat', $html);
        $this->assertStringContainsString("'Montserrat'", $html);
        $this->assertStringContainsString('#fffdfa', $html);
        $this->assertStringContainsString('#2e5b3c', $html);
        $this->assertStringContainsString('https://clicktap.app/logo-sample.png', $html);
        $this->assertStringContainsString('Café Santiago', $html);
        $this->assertStringContainsString('Pedir ahora', $html);
        $this->assertStringContainsString('text-align: justify', $html);
        $this->assertStringContainsString('Ubicación', $html);
        $this->assertStringContainsString('Contacto', $html);
        $this->assertStringContainsString('Toulouse, Hualpén, Bío Bío', $html);
        $this->assertStringContainsString('munos1517@gmail.com', $html);
        $this->assertStringNotContainsString('📍', $html);
    }

    public function test_unbroken_description_uses_character_justification_in_both_previews(): void
    {
        $description = str_repeat('1234567890', 20);
        $miniPreview = PagePreviewCompiler::compile([
            'nombre_publico' => 'ClickTap',
            'descripcion' => $description,
        ]);

        $this->assertStringContainsString('class="description description--unbroken"', $miniPreview);
        $this->assertStringContainsString('text-justify: inter-character', $miniPreview);

        $page = Pagina::factory()->create(['descripcion' => $description]);
        $this->actingAs(User::factory()->create())
            ->get(route('paginas.preview', $page))
            ->assertOk()
            ->assertSee('class="description description--unbroken"', false)
            ->assertSee('text-justify: inter-character', false);
    }

    public function test_page_preview_only_compiles_when_requested(): void
    {
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(CreatePagina::class)
            ->assertSet('compiledPagePreviewHtml', null)
            ->assertSee('Vista previa')
            ->assertDontSee('srcdoc=', false)
            ->fillForm([
                'nombre_publico' => 'Café Manual',
                'contenido_extra' => ['tagline' => 'Primera frase'],
            ])
            ->assertSet('compiledPagePreviewHtml', null)
            ->assertDontSee('srcdoc=', false);

        $component
            ->callFormComponentAction('vista-previa-section', 'cargarPersonalizacion')
            ->assertSee('srcdoc=', false);

        $firstPreview = $component->get('compiledPagePreviewHtml');
        $this->assertStringContainsString('Primera frase', $firstPreview);

        $component
            ->set('data.contenido_extra.tagline', 'Frase actualizada')
            ->callFormComponentAction('vista-previa-section', 'cargarPersonalizacion');

        $this->assertStringContainsString('Frase actualizada', $component->get('compiledPagePreviewHtml'));
    }

    public function test_edit_page_preview_loads_the_saved_page_only_when_requested(): void
    {
        $page = Pagina::factory()->create(['nombre_publico' => 'Local existente']);
        $this->actingAs(User::factory()->create());

        $component = Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()])
            ->assertSet('compiledPagePreviewHtml', null)
            ->assertDontSee('srcdoc=', false)
            ->callFormComponentAction('vista-previa-section', 'cargarPersonalizacion')
            ->assertSee('srcdoc=', false);

        $this->assertStringContainsString('Local existente', $component->get('compiledPagePreviewHtml'));
    }
}

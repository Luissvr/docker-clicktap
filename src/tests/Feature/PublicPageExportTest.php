<?php

namespace Tests\Feature;

use App\Data\PaginaPublica;
use App\Models\Pagina;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PublicPageExportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_export_keeps_empty_transfers_active_and_ignores_the_old_page_place_id(): void
    {
        $page = Pagina::factory()->create([
            'tema' => 'comercio', 'activo' => true,
            'contenido_extra' => ['place_id' => 'ChIJ_Obsoleto'],
        ]);
        $page->enlaces()->create([
            'tipo' => 'transferencia', 'activo' => true, 'posicion' => 0,
            'copiar_texto' => true, 'texto_copia' => null,
        ]);
        $page->enlaces()->create([
            'tipo' => 'review', 'activo' => true, 'posicion' => 1,
            'url' => 'https://search.google.com/local/writereview?placeid=ChIJ_Enlace',
        ]);
        $data = PaginaPublica::fromModel($page->load('enlaces'))->toArray();
        $this->assertCount(2, $data['links']);
        $this->assertFalse($data['links'][0]['isDisabled']);
        $this->assertSame('', $data['links'][0]['copyText']);
        $this->assertSame('https://search.google.com/local/writereview?placeid=ChIJ_Enlace', $data['links'][1]['href']);

        $page->enlaces()->where('tipo', 'review')->delete();
        $data = PaginaPublica::fromModel($page->load('enlaces'))->toArray();
        $this->assertCount(1, $data['links']);
    }

    public function test_export_only_includes_active_pages_and_public_content(): void
    {
        $active = Pagina::factory()->create([
            'slug' => 'pagina-publica',
            'nombre_publico' => 'Local público',
            'direccion_publica' => 'Una dirección',
            'telefono_publico' => '+56911111111',
            'correo_publico' => 'hola@ejemplo.cl',
            'activo' => true,
        ]);
        $active->enlaces()->create([
            'tipo' => 'transferencia',
            'etiqueta' => null,
            'url' => null,
            'posicion' => 0,
            'activo' => true,
            'copiar_texto' => true,
            'texto_copia' => 'Banco Demo, cuenta 123',
        ]);
        Pagina::factory()->create(['slug' => 'borrador', 'activo' => false]);

        $this->assertSame(0, Artisan::call('clicktap:export-public'));

        $payload = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(1, $payload['version']);
        $this->assertCount(1, $payload['pages']);
        $this->assertSame('pagina-publica', $payload['pages'][0]['slug']);
        $this->assertSame('Ubicación', $payload['pages'][0]['highlights'][0]['label']);
        $this->assertSame('Contacto', $payload['pages'][0]['highlights'][1]['label']);
        $this->assertSame("+56911111111\nhola@ejemplo.cl", $payload['pages'][0]['highlights'][1]['value']);
        $this->assertSame('+56911111111', $payload['pages'][0]['highlights'][1]['phone']);
        $this->assertSame('hola@ejemplo.cl', $payload['pages'][0]['highlights'][1]['email']);
        $this->assertSame('Copiar datos de transferencia', $payload['pages'][0]['links'][0]['label']);
        $this->assertSame('Banco Demo, cuenta 123', $payload['pages'][0]['links'][0]['copyText']);
        $this->assertArrayNotHasKey('actualizada_por', $payload['pages'][0]);
        $this->assertArrayNotHasKey('cliente', $payload['pages'][0]);
    }
}

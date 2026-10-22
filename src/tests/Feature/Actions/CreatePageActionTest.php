<?php

namespace Tests\Feature\Actions;

use App\Actions\CreatePageAction;
use App\Exceptions\InvalidPageRouteException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CreatePageActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_creates_customer_location_page_and_links_together(): void
    {
        $actor = User::factory()->create();

        $page = app(CreatePageAction::class)->handle(
            $actor,
            ['nombre' => 'Cliente Demo'],
            ['nombre_interno' => 'Local Centro'],
            'local-centro',
            ['nombre_publico' => 'Local Centro', 'descripcion' => 'Descripción vigente'],
            [
                ['tipo' => 'whatsapp', 'etiqueta' => 'WhatsApp', 'url' => 'https://wa.me/56900000000'],
            ],
        );

        $this->assertSame('local-centro', $page->slug);
        $this->assertSame('Cliente Demo', $page->local->cliente->nombre);
        $this->assertSame('Local Centro', $page->nombre_publico);
        $this->assertSame('Descripción vigente', $page->descripcion);
        $this->assertSame($actor->id, $page->actualizada_por);
        $this->assertSame('WhatsApp', $page->enlaces->first()->etiqueta);
        $this->assertDatabaseCount('clientes', 1);
        $this->assertDatabaseCount('locales', 1);
        $this->assertDatabaseCount('paginas', 1);
        $this->assertDatabaseCount('enlaces_pagina', 1);
        $this->assertDatabaseCount('versiones_pagina', 0);
        $this->assertDatabaseCount('eventos_auditoria', 0);
    }

    public function test_creates_an_inactive_demo_page_without_a_customer(): void
    {
        $actor = User::factory()->create();

        $page = app(CreatePageAction::class)->handle(
            $actor,
            [],
            ['nombre_interno' => 'Local Demo'],
            'local-demo',
            ['nombre_publico' => 'Local Demo'],
        );

        $this->assertFalse($page->activo);
        $this->assertNull($page->local->cliente_id);
        $this->assertDatabaseCount('clientes', 0);
        $this->assertDatabaseCount('locales', 1);
        $this->assertDatabaseCount('paginas', 1);
    }

    public function test_description_accepts_150_words_and_rejects_151(): void
    {
        $actor = User::factory()->create();
        $description = implode(' ', array_fill(0, 150, 'atención'));

        $page = app(CreatePageAction::class)->handle(
            $actor,
            [],
            ['nombre_interno' => 'Local Demo'],
            'descripcion-150',
            ['nombre_publico' => 'Local Demo', 'descripcion' => $description],
        );

        $this->assertSame($description, $page->descripcion);

        try {
            app(CreatePageAction::class)->handle(
                $actor,
                [],
                ['nombre_interno' => 'Segundo local'],
                'descripcion-151',
                ['nombre_publico' => 'Segundo local', 'descripcion' => $description.' adicional'],
            );
            $this->fail('Se aceptó una descripción de 151 palabras.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('pagina.descripcion', $exception->errors());
            $this->assertDatabaseCount('paginas', 1);
        }
    }

    public function test_creates_a_transfer_link_without_a_destination(): void
    {
        $actor = User::factory()->create();

        $page = app(CreatePageAction::class)->handle(
            $actor,
            [],
            ['nombre_interno' => 'Local Demo'],
            'local-demo',
            ['nombre_publico' => 'Local Demo'],
            [[
                'tipo' => 'transferencia',
                'etiqueta' => null,
                'url' => null,
                'copiar_texto' => true,
                'texto_copia' => 'Banco Demo, cuenta 123456',
            ]],
        );

        $this->assertNull($page->enlaces->first()->url);
        $this->assertSame('transferencia', $page->enlaces->first()->tipo);
        $this->assertNull($page->enlaces->first()->etiqueta);
        $this->assertTrue($page->enlaces->first()->copiar_texto);
        $this->assertSame('Banco Demo, cuenta 123456', $page->enlaces->first()->texto_copia);
    }

    public function test_rolls_back_creation_when_the_route_is_taken(): void
    {
        $actor = User::factory()->create();
        $action = app(CreatePageAction::class);
        $action->handle(
            $actor,
            ['nombre' => 'Primer cliente'],
            ['nombre_interno' => 'Primer local'],
            'ruta-ocupada',
            ['nombre_publico' => 'Primer local'],
        );

        try {
            $action->handle(
                $actor,
                ['nombre' => 'Cliente que debe revertirse'],
                ['nombre_interno' => 'Local que debe revertirse'],
                'RUTA-OCUPADA',
                ['nombre_publico' => 'Otro local'],
            );
            $this->fail('La ruta duplicada fue aceptada.');
        } catch (InvalidPageRouteException) {
            $this->assertDatabaseCount('clientes', 1);
            $this->assertDatabaseCount('locales', 1);
            $this->assertDatabaseCount('paginas', 1);
        }
    }

    public function test_inactive_administrator_cannot_create_a_page(): void
    {
        $actor = User::factory()->create(['activo' => false]);

        $this->expectException(AuthorizationException::class);

        app(CreatePageAction::class)->handle(
            $actor,
            ['nombre' => 'Cliente Demo'],
            ['nombre_interno' => 'Local Centro'],
            'local-centro',
            ['nombre_publico' => 'Local Centro'],
        );
    }
}

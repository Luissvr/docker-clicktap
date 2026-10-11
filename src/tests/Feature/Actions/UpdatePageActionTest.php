<?php

namespace Tests\Feature\Actions;

use App\Actions\CreatePageAction;
use App\Actions\UpdatePageAction;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class UpdatePageActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updates_current_content_and_replaces_links(): void
    {
        [$actor, $page] = $this->createPage();

        $saved = app(UpdatePageAction::class)->handle(
            $actor,
            $page,
            ['nombre_publico' => 'Nombre actualizado', 'descripcion' => 'Contenido más reciente'],
            [
                ['tipo' => 'instagram', 'etiqueta' => 'Instagram', 'url' => 'https://instagram.com/demo', 'posicion' => 1],
                ['tipo' => 'whatsapp', 'etiqueta' => 'WhatsApp', 'url' => 'https://wa.me/56900000000', 'posicion' => 0],
            ],
        );

        $this->assertSame('Nombre actualizado', $saved->nombre_publico);
        $this->assertSame('Contenido más reciente', $saved->descripcion);
        $this->assertSame(['WhatsApp', 'Instagram'], $saved->enlaces->pluck('etiqueta')->all());
        $this->assertSame($actor->id, $saved->actualizada_por);
        $this->assertDatabaseMissing('enlaces_pagina', ['etiqueta' => 'Sitio anterior']);
        $this->assertDatabaseCount('enlaces_pagina', 2);
    }

    public function test_duplicate_link_positions_roll_back_the_save(): void
    {
        [$actor, $page] = $this->createPage();

        try {
            app(UpdatePageAction::class)->handle(
                $actor,
                $page,
                ['nombre_publico' => 'No debe guardarse'],
                [
                    ['tipo' => 'otro', 'etiqueta' => 'Uno', 'url' => 'https://example.com/uno', 'posicion' => 0],
                    ['tipo' => 'otro', 'etiqueta' => 'Dos', 'url' => 'https://example.com/dos', 'posicion' => 0],
                ],
            );
            $this->fail('Se aceptaron dos enlaces con la misma posición.');
        } catch (ValidationException) {
            $this->assertSame('Local de prueba', $page->refresh()->nombre_publico);
            $this->assertDatabaseHas('enlaces_pagina', ['etiqueta' => 'Sitio anterior']);
            $this->assertDatabaseCount('enlaces_pagina', 1);
        }
    }

    public function test_inactive_administrator_cannot_update_a_page(): void
    {
        [, $page] = $this->createPage();
        $inactiveActor = User::factory()->create(['activo' => false]);

        $this->expectException(AuthorizationException::class);

        app(UpdatePageAction::class)->handle(
            $inactiveActor,
            $page,
            ['nombre_publico' => 'Cambio inválido'],
            [],
        );
    }

    /** @return array{User, Pagina} */
    private function createPage(): array
    {
        $actor = User::factory()->create();
        $page = app(CreatePageAction::class)->handle(
            $actor,
            ['nombre' => 'Cliente de prueba'],
            ['nombre_interno' => 'Local de prueba'],
            'local-de-prueba',
            ['nombre_publico' => 'Local de prueba'],
            [
                ['tipo' => 'web', 'etiqueta' => 'Sitio anterior', 'url' => 'https://example.com'],
            ],
        );

        return [$actor, $page];
    }
}

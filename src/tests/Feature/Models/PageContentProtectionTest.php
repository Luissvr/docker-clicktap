<?php

namespace Tests\Feature\Models;

use App\Actions\CreatePageAction;
use App\Exceptions\InvalidPageRouteException;
use App\Models\Pagina;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class PageContentProtectionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_reserved_and_legacy_alias_routes_cannot_be_used(): void
    {
        [, $page] = $this->createPage('ruta-principal');
        DB::table('alias_pagina')->insert([
            'id' => (string) Str::uuid(),
            'pagina_id' => $page->id,
            'ruta' => 'ruta-antigua',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        try {
            $this->createPage('RUTA-ANTIGUA');
            $this->fail('Una ruta histórica fue reutilizada.');
        } catch (InvalidPageRouteException) {
            $this->assertDatabaseCount('paginas', 1);
        }

        $this->expectException(InvalidPageRouteException::class);
        $this->createPage('admin');
    }

    public function test_slug_cannot_change_after_creation(): void
    {
        [, $page] = $this->createPage('ruta-estable');

        $this->expectException(LogicException::class);

        $page->slug = 'ruta-nueva';
        $page->save();
    }

    public function test_links_are_ordered_and_deleted_with_the_page(): void
    {
        [, $page] = $this->createPage('pagina-con-enlaces', [
            ['tipo' => 'instagram', 'etiqueta' => 'Segundo', 'url' => 'https://instagram.com/demo', 'posicion' => 1],
            ['tipo' => 'web', 'etiqueta' => 'Primero', 'url' => 'https://example.com', 'posicion' => 0],
        ]);

        $this->assertSame(['Primero', 'Segundo'], $page->enlaces->pluck('etiqueta')->all());

        DB::table('paginas')->where('id', $page->id)->delete();
        $this->assertDatabaseCount('enlaces_pagina', 0);
    }

    /** @param list<array<string, mixed>> $links
     * @return array{User, Pagina}
     */
    private function createPage(string $slug, array $links = []): array
    {
        $actor = User::factory()->create();
        $page = app(CreatePageAction::class)->handle(
            $actor,
            ['nombre' => 'Cliente de prueba'],
            ['nombre_interno' => 'Local de prueba'],
            $slug,
            ['nombre_publico' => 'Local de prueba'],
            $links,
        );

        return [$actor, $page];
    }
}

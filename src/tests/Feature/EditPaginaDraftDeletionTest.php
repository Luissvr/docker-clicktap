<?php

namespace Tests\Feature;

use App\Filament\Resources\PaginaResource;
use App\Filament\Resources\PaginaResource\Pages\EditPagina;
use App\Models\Pagina;
use App\Models\User;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class EditPaginaDraftDeletionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_editor_moves_an_unpublished_draft_to_trash_and_cancels_its_queued_publication(): void
    {
        config(['clicktap.publication_slugs' => null]);
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['slug' => 'digasol', 'nombre_publico' => 'Digasol', 'publicacion_estado' => 'sin_publicar']);
        $page->enlaces()->create(['tipo' => 'web', 'nombre' => 'Sitio', 'etiqueta' => 'Sitio', 'url' => 'https://example.com', 'posicion' => 0, 'activo' => true]);
        app(PublicationQueue::class)->request($page);

        Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()])
            ->assertActionVisible('deleteDraft')
            ->assertSee('Eliminar borrador')
            ->callAction('deleteDraft')
            ->assertHasNoActionErrors()
            ->assertRedirect(PaginaResource::getUrl());

        $this->assertDatabaseHas('paginas', ['id' => $page->id, 'activo' => false, 'publicacion_estado' => 'sin_publicar']);
        $this->assertNotNull($page->fresh()->papelera_en);
        $this->assertDatabaseHas('enlaces_pagina', ['pagina_id' => $page->id]);
        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
        $this->assertFalse(PaginaResource::getEloquentQuery()->whereKey($page->id)->exists());
    }

    public function test_editor_does_not_offer_draft_deletion_after_a_page_was_sent(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['publicacion_estado' => 'enviada', 'publicacion_commit' => 'abc', 'publicada_en' => now()]);

        Livewire::test(EditPagina::class, ['record' => $page->getRouteKey()])
            ->assertActionHidden('deleteDraft')
            ->assertDontSee('Eliminar borrador');

        try {
            app(PageTrash::class)->moveDraft($page->id);
            $this->fail('La página enviada se aceptó como borrador sin publicar.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertNull($page->fresh()->papelera_en);
    }

    public function test_running_publication_is_withdrawn_if_its_draft_is_removed(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['publicacion_estado' => 'sin_publicar']);
        cache()->store(config('clicktap.publication_store', 'publications'))->forever('publication-running', [
            'page_id' => $page->id,
            'type' => 'publish',
        ]);

        app(PageTrash::class)->moveDraft($page->id);

        $this->assertSame('retirada_pendiente', $page->fresh()->publicacion_estado);
        $this->assertSame(1, app(PublicationQueue::class)->status()['pendingCount']);
        $this->assertFalse(app(PageTrash::class)->canLeaveTrash($page->fresh()));
    }
}

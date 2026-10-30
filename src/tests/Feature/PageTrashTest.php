<?php

namespace Tests\Feature;

use App\Filament\Pages\PublicacionesSitio;
use App\Filament\Resources\PaginaResource;
use App\Models\Pagina;
use App\Models\User;
use App\Support\PageTrash;
use App\Support\PublicationQueue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use LogicException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PageTrashTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_published_page_moves_to_trash_and_waits_for_verified_withdrawal(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['nombre_publico' => 'Página publicada', 'publicacion_estado' => 'publicada', 'publicacion_commit' => 'abc', 'publicada_en' => now()]);

        Livewire::test(PublicacionesSitio::class)
            ->assertSee('Página publicada')
            ->call('moveToTrash', $page->id)
            ->assertHasNoErrors()
            ->set('filter', 'trash')
            ->assertSee('En papelera')
            ->assertSee('Descargar respaldo');

        $this->assertFalse($page->fresh()->activo);
        $this->assertNotNull($page->fresh()->papelera_en);
        $this->assertSame('retirada_pendiente', $page->fresh()->publicacion_estado);
        $this->assertSame('withdraw', app(PublicationQueue::class)->status()['pending']['type']);
        $this->assertFalse(PaginaResource::getEloquentQuery()->whereKey($page->id)->exists());
        $this->assertFalse(auth()->user()->can('update', $page->fresh()));
        $this->assertFalse(app(PageTrash::class)->canLeaveTrash($page->fresh()));
    }

    public function test_backup_contains_content_and_logo_before_permanent_deletion(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('logos/pagina.png', 'logo-bytes');
        $actor = User::factory()->create(['activo' => true]);
        $this->actingAs($actor);
        $page = Pagina::factory()->create([
            'slug' => 'cafe-backup',
            'logo_path' => 'logos/pagina.png',
            'publicacion_estado' => 'publicada',
            'publicacion_commit' => 'abc',
            'publicada_en' => now(),
        ]);
        $page->enlaces()->create(['tipo' => 'web', 'nombre' => 'Sitio', 'etiqueta' => 'Sitio', 'url' => 'https://example.com', 'posicion' => 0, 'activo' => true]);
        $saleId = (string) Str::uuid();
        DB::table('ventas')->insert(['id' => $saleId, 'estado' => 'borrador', 'pagina_id' => $page->id, 'registrado_por' => $actor->id, 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(PublicacionesSitio::class)->call('moveToTrash', $page->id)->assertHasNoErrors();
        $backup = app(PageTrash::class)->backup($page->fresh());
        $this->assertSame('cafe-backup', $backup['page']['slug']);
        $this->assertSame('Sitio', $backup['links'][0]['nombre']);
        $this->assertSame(base64_encode('logo-bytes'), $backup['logo']['base64']);
        Livewire::test(PublicacionesSitio::class)->call('downloadBackup', $page->id)->assertFileDownloaded('clicktap-cafe-backup-respaldo.json');
        try {
            app(PageTrash::class)->purge($page->id);
            $this->fail('La página se eliminó antes de comprobar su retirada.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }

        $page->fresh()->forceFill(['publicacion_estado' => 'retirada'])->save();
        Livewire::test(PublicacionesSitio::class)->call('deleteFromTrash', $page->id)->assertHasNoErrors();
        $this->assertDatabaseMissing('paginas', ['id' => $page->id]);
        $this->assertDatabaseMissing('enlaces_pagina', ['pagina_id' => $page->id]);
        $this->assertDatabaseHas('ventas', ['id' => $saleId, 'pagina_id' => null]);
    }

    public function test_old_trash_is_pruned_only_after_public_access_is_removed(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $this->travelTo(now()->startOfDay());
        $ready = Pagina::factory()->create(['publicacion_estado' => 'retirada', 'activo' => false, 'papelera_en' => now()->subDays(31)]);
        $pending = Pagina::factory()->create(['publicacion_estado' => 'retirada_pendiente', 'activo' => false, 'papelera_en' => now()->subDays(31)]);
        $recent = Pagina::factory()->create(['publicacion_estado' => 'retirada', 'activo' => false, 'papelera_en' => now()->subDays(29)]);

        $this->assertSame(0, Artisan::call('clicktap:prune-page-trash'));
        $this->assertDatabaseMissing('paginas', ['id' => $ready->id]);
        $this->assertDatabaseHas('paginas', ['id' => $pending->id]);
        $this->assertDatabaseHas('paginas', ['id' => $recent->id]);
    }

    public function test_never_published_page_can_be_restored_without_a_deployment(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create(['publicacion_estado' => 'sin_publicar']);

        Livewire::test(PublicacionesSitio::class)->call('moveToTrash', $page->id)->assertHasNoErrors();
        $this->assertSame('sin_publicar', $page->fresh()->publicacion_estado);
        $this->assertSame(0, app(PublicationQueue::class)->status()['pendingCount']);
        Livewire::test(PublicacionesSitio::class)->call('restoreFromTrash', $page->id)->assertHasNoErrors();
        $this->assertNull($page->fresh()->papelera_en);
        $this->assertFalse($page->fresh()->activo);
        $this->assertTrue(PaginaResource::getEloquentQuery()->whereKey($page->id)->exists());
    }

    public function test_page_in_trash_cannot_be_activated_directly(): void
    {
        $this->actingAs(User::factory()->create(['activo' => true]));
        $page = Pagina::factory()->create();
        app(PageTrash::class)->move($page->id);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Restaura la página desde la papelera');
        $page->fresh()->forceFill(['activo' => true])->save();
    }
}

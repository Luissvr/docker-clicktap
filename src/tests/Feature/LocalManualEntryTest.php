<?php

namespace Tests\Feature;

use App\Filament\Resources\LocalNegocioResource\Pages\CreateLocal;
use App\Filament\Resources\LocalNegocioResource\Pages\EditLocal;
use App\Models\Cliente;
use App\Models\LocalNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class LocalManualEntryTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_administrator_can_type_a_new_customer_and_address_without_leaving_the_local_form(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $form = Livewire::test(CreateLocal::class)
            ->fillForm([
                'nombre_interno' => 'Local Providencia',
                'direccion_operativa' => 'Av. Providencia 1234, local 5, Santiago',
            ])
            ->callFormComponentAction('cliente_id', 'createOption', data: [
                'nombre' => 'Cliente escrito manualmente',
                'nombre_contacto' => 'Ana Pérez',
                'telefono_contacto' => '+56 9 1234 5678',
                'correo_contacto' => 'ana@example.com',
            ])
            ->assertHasNoFormComponentActionErrors();

        $customer = Cliente::sole();

        $form->assertFormSet(['cliente_id' => $customer->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('locales', [
            'nombre_interno' => 'Local Providencia',
            'cliente_id' => $customer->id,
            'direccion_operativa' => 'Av. Providencia 1234, local 5, Santiago',
        ]);
        $this->assertSame($user->id, $customer->creado_por);
        $this->assertSame('ana@example.com', $customer->correo_contacto);
    }

    public function test_existing_local_can_be_assigned_a_customer_and_a_manual_address(): void
    {
        $this->actingAs(User::factory()->create());
        $local = LocalNegocio::factory()->create(['cliente_id' => null, 'direccion_operativa' => null]);
        $customer = Cliente::factory()->create();

        Livewire::test(EditLocal::class, ['record' => $local->getRouteKey()])
            ->fillForm(['cliente_id' => $customer->id, 'direccion_operativa' => 'Pasaje Los Aromos 50'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('locales', [
            'id' => $local->id,
            'cliente_id' => $customer->id,
            'direccion_operativa' => 'Pasaje Los Aromos 50',
            'revision' => 2,
        ]);
        $this->assertDatabaseCount('clientes', 1);
    }

    public function test_customer_creation_validates_manual_fields(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(CreateLocal::class)
            ->callFormComponentAction('cliente_id', 'createOption', data: [
                'nombre' => '',
                'correo_contacto' => 'correo-invalido',
            ])
            ->assertHasFormComponentActionErrors(['nombre' => 'required', 'correo_contacto' => 'email']);

        $this->assertDatabaseCount('clientes', 0);
    }

    public function test_customer_creation_respects_authorization(): void
    {
        $this->actingAs(User::factory()->create());
        $local = LocalNegocio::factory()->create(['cliente_id' => null]);
        Gate::before(fn (User $user, string $ability): ?bool => $ability === 'create' ? false : null);

        Livewire::test(EditLocal::class, ['record' => $local->getRouteKey()])
            ->assertFormComponentActionHidden('cliente_id', 'createOption')
            ->call('mountFormComponentAction', 'data.cliente_id', 'createOption')
            ->call('callMountedFormComponentAction');

        $this->assertDatabaseCount('clientes', 0);
    }
}

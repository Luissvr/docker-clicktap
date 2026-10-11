<?php

namespace Database\Factories;

use App\Models\Cliente;
use App\Models\LocalNegocio;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LocalNegocio> */
class LocalNegocioFactory extends Factory
{
    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'nombre_interno' => fake()->company(),
            'direccion_operativa' => fake()->streetAddress(),
        ];
    }
}

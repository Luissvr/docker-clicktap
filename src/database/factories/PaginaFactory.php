<?php

namespace Database\Factories;

use App\Models\LocalNegocio;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Pagina> */
class PaginaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'local_id' => LocalNegocio::factory(),
            'slug' => fake()->unique()->slug(3),
            'nombre_publico' => fake()->company(),
            'descripcion' => fake()->sentence(),
            'activo' => true,
        ];
    }
}

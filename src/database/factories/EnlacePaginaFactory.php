<?php

namespace Database\Factories;

use App\Models\EnlacePagina;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnlacePagina> */
class EnlacePaginaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'pagina_id' => Pagina::factory(),
            'tipo' => 'web',
            'etiqueta' => fake()->words(2, true),
            'url' => fake()->url(),
            'posicion' => 0,
            'activo' => true,
            'copiar_texto' => false,
            'texto_copia' => null,
        ];
    }
}

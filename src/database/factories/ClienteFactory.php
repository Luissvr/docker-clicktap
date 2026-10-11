<?php

namespace Database\Factories;

use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Cliente> */
class ClienteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'nombre' => fake()->company(),
            'nombre_contacto' => fake()->name(),
            'telefono_contacto' => fake()->phoneNumber(),
            'correo_contacto' => fake()->safeEmail(),
        ];
    }
}

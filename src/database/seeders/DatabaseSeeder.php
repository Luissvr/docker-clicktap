<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Administrador inicial del panel ClickTap
        User::firstOrCreate(
            ['email' => 'admin@clicktap.app'],
            [
                'name' => 'Administrador ClickTap',
                'password' => Hash::make('ClickTap2026!'),
                'activo' => true,
            ]
        );

        // Configuración de precios sugeridos
        DB::table('configuracion_precios')->insertOrIgnore([
            'id' => 'global',
            'precio_inicial_clp' => 12990,
            'precio_cambio_clp' => 2990,
            'actualizado_en' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreatePartner extends Command
{
    protected $signature = 'clicktap:create-partner {email} {--name=}';

    protected $description = 'Crea una cuenta individual del panel con contraseña oculta; no modifica cuentas existentes';

    public function handle(): int
    {
        if (! $this->input->isInteractive()) {
            $this->error('Ejecuta el comando en una terminal interactiva para ingresar la contraseña sin mostrarla.');

            return self::FAILURE;
        }
        $data = [
            'email' => strtolower(trim((string) $this->argument('email'))),
            'name' => $this->option('name') ?: $this->ask('Nombre del socio'),
            'password' => $this->secret('Contraseña (mínimo 12 caracteres)'),
            'password_confirmation' => $this->secret('Repite la contraseña'),
        ];
        $validator = Validator::make($data, [
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }
        User::create([
            'email' => $data['email'], 'name' => $data['name'],
            'password' => $data['password'], 'activo' => true,
        ]);
        $this->info('Cuenta creada. Puede acceder al mismo catálogo compartido.');

        return self::SUCCESS;
    }
}

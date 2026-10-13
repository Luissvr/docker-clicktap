<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PrepareSharedDatabase extends Command
{
    protected $signature = 'clicktap:prepare-shared-database {--apply : Crear y proteger el esquema privado}';

    protected $description = 'Comprueba PostgreSQL y prepara el esquema privado sin ejecutar migraciones ni importar datos';

    public function handle(): int
    {
        $connection = DB::connection();
        $schema = (string) $connection->getConfig('search_path');
        if ($connection->getDriverName() !== 'pgsql' || $schema !== 'clicktap') {
            $this->error('Configura DB_CONNECTION=pgsql y DB_SCHEMA=clicktap.');

            return self::FAILURE;
        }
        if ($connection->getConfig('sslmode') !== 'require' && $connection->getConfig('sslmode') !== 'verify-full') {
            $this->error('Configura DB_SSLMODE=require o verify-full.');

            return self::FAILURE;
        }
        try {
            $connection->select('select 1');
            if (! $this->option('apply')) {
                $this->info('PostgreSQL responde. Usa --apply para preparar clicktap; luego ejecuta migrate.');

                return self::SUCCESS;
            }
            $connection->transaction(function () use ($connection): void {
                $connection->statement('CREATE SCHEMA IF NOT EXISTS clicktap');
                $connection->statement('REVOKE ALL ON SCHEMA clicktap FROM PUBLIC, anon, authenticated');
                $connection->statement('REVOKE ALL ON ALL TABLES IN SCHEMA clicktap FROM PUBLIC, anon, authenticated');
                $connection->statement('REVOKE ALL ON ALL SEQUENCES IN SCHEMA clicktap FROM PUBLIC, anon, authenticated');
                $connection->statement('REVOKE ALL ON ALL FUNCTIONS IN SCHEMA clicktap FROM PUBLIC, anon, authenticated');
                $connection->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA clicktap REVOKE ALL ON TABLES FROM PUBLIC, anon, authenticated');
                $connection->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA clicktap REVOKE ALL ON SEQUENCES FROM PUBLIC, anon, authenticated');
                $connection->statement('ALTER DEFAULT PRIVILEGES IN SCHEMA clicktap REVOKE ALL ON FUNCTIONS FROM PUBLIC, anon, authenticated');
            });
            $this->info('Esquema clicktap preparado. No lo agregues a los esquemas expuestos por la Data API.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('No se pudo preparar PostgreSQL. Revisa conexión, SSL y permisos; no se muestran credenciales.');

            return self::FAILURE;
        }
    }
}

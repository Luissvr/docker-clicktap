<?php

namespace App\Console\Commands;

use App\Models\Pagina;
use App\Support\CatalogConnection;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class MigrateLogosToSupabase extends Command
{
    protected $signature = 'clicktap:migrate-logos {--apply : Copiar y verificar; sin esta opción solo informa}';

    protected $description = 'Copia logos locales a Supabase sin borrar originales ni sobrescribir objetos diferentes';

    public function handle(): int
    {
        $pages = Pagina::where('logo_disk', 'public')->whereNotNull('logo_path')->get();
        $this->info('Páginas con logos locales por revisar: '.$pages->count());
        if (! $this->option('apply')) {
            $this->info('Simulación: no se copiaron archivos ni se modificaron registros.');

            return self::SUCCESS;
        }
        try {
            foreach ($pages as $page) {
                $source = $page->logo_path;
                if (preg_match('~^https?://~i', $source)) {
                    $this->warn('Se conserva un logo externo; requiere revisión manual.');

                    continue;
                }
                if (! str_starts_with($source, 'logos/') || str_contains($source, '..')) {
                    throw new RuntimeException('Ruta local fuera de logos/.');
                }
                $contents = Storage::disk('public')->get($source);
                if (! is_string($contents)) {
                    throw new RuntimeException('No se pudo leer el original.');
                }
                $target = Storage::disk('supabase');
                if ($target->exists($source)) {
                    if (hash('sha256', $target->get($source)) !== hash('sha256', $contents)) {
                        throw new RuntimeException('El destino contiene otro archivo; no se sobrescribirá.');
                    }
                } else {
                    $target->put($source, $contents, ['visibility' => 'private']);
                }
                if (hash('sha256', $target->get($source)) !== hash('sha256', $contents)) {
                    throw new RuntimeException('La copia no coincide con el original.');
                }
                CatalogConnection::transaction(function () use ($page, $source): void {
                    $locked = Pagina::whereKey($page->id)->lockForUpdate()->firstOrFail();
                    if ($locked->logo_disk !== 'public' || $locked->logo_path !== $source || (int) $locked->revision !== (int) $page->revision) {
                        throw new RuntimeException('La página cambió durante la copia; reintenta tras revisarla.');
                    }
                    $locked->logo_disk = 'supabase';
                    $locked->save();
                });
            }
            $this->info('Copias verificadas. Los originales locales se conservaron.');

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Migración detenida: revisa rutas, permisos y posibles archivos distintos en destino. Los originales se conservaron; puedes reintentar.');

            return self::FAILURE;
        }
    }
}

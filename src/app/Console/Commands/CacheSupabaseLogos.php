<?php

namespace App\Console\Commands;

use App\Support\LogoCache;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('clicktap:cache-supabase-logos {--retry-missing : Volver a buscar imágenes ausentes en el último intento}')]
#[Description('Descarga una vez los logos disponibles en Supabase a la caché privada local')]
class CacheSupabaseLogos extends Command
{
    public function handle(LogoCache $cache): int
    {
        $summary = $cache->warm((bool) $this->option('retry-missing'));
        $this->info("En caché: {$summary['cached']}; descargados: {$summary['downloaded']}; ausentes en Supabase: ".count($summary['missing']).'; errores: '.count($summary['errors']).'.');
        if ($summary['missing'] !== []) {
            $this->warn('Sin archivo de origen: '.implode(', ', $summary['missing']));
        }
        if ($summary['errors'] !== []) {
            $this->error('No se pudieron consultar: '.implode(', ', $summary['errors']));
        }

        return $summary['errors'] === [] ? self::SUCCESS : self::FAILURE;
    }
}

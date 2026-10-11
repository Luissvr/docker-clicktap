<?php

use Illuminate\Foundation\Inspiring;
use App\Support\PublicPageExporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clicktap:export-public {--output=}', function (PublicPageExporter $exporter): void {
    $json = json_encode($exporter->payload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR).PHP_EOL;
    $output = $this->option('output');

    if (! is_string($output) || trim($output) === '') {
        $this->output->write($json);
        return;
    }

    File::ensureDirectoryExists(dirname($output));
    File::put($output, $json);
    $this->info('Exportadas las páginas públicas en '.$output);
})->purpose('Exporta el contrato público vigente para Astro');

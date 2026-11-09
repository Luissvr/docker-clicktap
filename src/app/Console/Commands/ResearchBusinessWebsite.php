<?php

namespace App\Console\Commands;

use App\Exceptions\BusinessResearchException;
use App\Support\Research\WebsiteResearch;
use Illuminate\Console\Command;

class ResearchBusinessWebsite extends Command
{
    protected $signature = 'clicktap:research-website
        {url : Web oficial del negocio}
        {--name= : Nombre esperado del negocio}
        {--sin-ia : Solo extracción directa de la página, sin OpenRouter}
        {--json : Imprimir el resultado como JSON}';

    protected $description = 'Prototipo: lee la web de un negocio y propone datos de contacto y redes con su fuente (no guarda nada)';

    public function handle(WebsiteResearch $research): int
    {
        try {
            $result = $research->research(
                (string) $this->argument('url'),
                $this->option('name') ?: null,
                ! $this->option('sin-ia'),
            );
        } catch (BusinessResearchException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ($this->option('json')) {
            $this->output->writeln(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return self::SUCCESS;
        }

        $this->info(($result['titulo'] ?? 'Sin título').' — '.$result['url']);
        $this->table(['Campo', 'Valor', 'Estado', 'Origen'], collect($result['campos'])->map(fn (array $field, string $name): array => [
            $name,
            str_replace("\n", ' / ', $field['valor']),
            $field['verificado'] ? 'Verificado' : 'Sugerido',
            $field['origen'] === 'ia' ? 'IA' : 'Página',
        ])->values()->all());

        if ($result['modelo'] !== null) {
            $this->line('Modelo IA: '.$result['modelo']);
        } elseif ($result['ia_error'] !== null) {
            $this->warn('IA no usada: '.$result['ia_error']);
        } elseif (! $this->option('sin-ia')) {
            $this->warn('IA no usada: falta configurar OPENROUTER_API_KEY.');
        }
        foreach ($result['descartados'] as $discarded) {
            $this->line('<fg=yellow>Descartado</> '.$discarded);
        }
        foreach ($result['pendientes'] as $pending) {
            $this->line('- '.$pending);
        }

        return self::SUCCESS;
    }
}

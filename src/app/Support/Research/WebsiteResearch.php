<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;

/**
 * Investiga un negocio a partir de su web oficial: descarga segura, extracción determinista
 * y, si está configurada, extracción con IA verificada contra la misma página.
 */
class WebsiteResearch
{
    public function __construct(
        private SafeHttpFetcher $fetcher,
        private WebsitePageReader $reader,
        private OpenRouterExtractor $extractor,
    ) {}

    /**
     * @return array{
     *     url: string,
     *     titulo: ?string,
     *     campos: array<string, array{valor: string, verificado: bool, origen: string, fuente: string}>,
     *     descartados: list<string>,
     *     pendientes: list<string>,
     *     modelo: ?string,
     *     ia_error: ?string
     * }
     */
    public function research(string $url, ?string $expectedName = null, bool $useAi = true): array
    {
        $download = $this->fetcher->get($url);
        if ($download['status'] >= 400) {
            throw new BusinessResearchException('La página respondió HTTP '.$download['status'].'.');
        }
        if ($download['content_type'] !== '' && ! str_contains(strtolower($download['content_type']), 'html')) {
            throw new BusinessResearchException('La dirección no es una página web (tipo '.$download['content_type'].').');
        }

        $page = $this->reader->read($download['body'], $download['url']);
        $fields = $this->deterministicFields($page);
        $discarded = [];
        $model = null;
        $aiError = null;

        if ($useAi && $this->extractor->configured()) {
            try {
                $extraction = $this->extractor->extract($page, $expectedName);
                $fields += $extraction['campos'];
                $discarded = $extraction['descartados'];
                $model = $extraction['modelo'];
            } catch (BusinessResearchException $exception) {
                $aiError = $exception->getMessage();
            }
        }

        $pending = ['Confirmar datos y logo con el encargado antes de publicar.'];
        foreach ($fields as $field => $data) {
            if (! $data['verificado']) {
                $pending[] = 'Revisar '.$field.' (sugerido, no comprobado literalmente).';
            }
        }

        return [
            'url' => $download['url'],
            'titulo' => $page['title'],
            'campos' => $fields,
            'descartados' => $discarded,
            'pendientes' => $pending,
            'modelo' => $model,
            'ia_error' => $aiError,
        ];
    }

    /**
     * @param  array{url: string, title: ?string, description: ?string, image: ?string, logo?: ?string, social: array<string, string>, whatsapp: list<string>, phones: list<string>, json_ld: list<array<string, mixed>>}  $page
     * @return array<string, array{valor: string, verificado: bool, origen: string, fuente: string}>
     */
    private function deterministicFields(array $page): array
    {
        $fields = [];
        $add = function (string $field, mixed $value, bool $verified) use (&$fields, $page): void {
            if (is_string($value) && trim($value) !== '' && ! isset($fields[$field])) {
                $fields[$field] = ['valor' => mb_substr(trim($value), 0, 500), 'verificado' => $verified, 'origen' => 'pagina', 'fuente' => $page['url']];
            }
        };

        foreach ($page['json_ld'] as $node) {
            $add('nombre', $node['name'] ?? null, true);
            $phoneDigits = is_string($node['telephone'] ?? null) ? $this->reader->digits($node['telephone']) : '';
            $add('telefono', $phoneDigits !== '' ? '+'.$phoneDigits : null, true);
            $add('direccion', $this->address($node['address'] ?? null), true);
            $hours = $node['openingHours'] ?? null;
            $add('horario', is_array($hours) ? implode("\n", array_filter($hours, 'is_string')) : $hours, true);
        }
        $add('whatsapp', isset($page['whatsapp'][0]) ? '+'.$page['whatsapp'][0] : null, true);
        $add('telefono', isset($page['phones'][0]) ? '+'.$page['phones'][0] : null, true);
        foreach ($page['social'] as $network => $url) {
            $add($network, $url, true);
        }
        $add('descripcion', $page['description'], false);
        $add('logo', $page['logo'] ?? $page['image'], false);

        return $fields;
    }

    private function address(mixed $address): ?string
    {
        if (is_string($address)) {
            return $address;
        }
        if (! is_array($address)) {
            return null;
        }
        $parts = array_filter([
            $address['streetAddress'] ?? null,
            $address['addressLocality'] ?? null,
            $address['addressRegion'] ?? null,
        ], 'is_string');

        return $parts === [] ? null : implode(', ', $parts);
    }
}

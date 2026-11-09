<?php

namespace App\Support\Research;

use App\Exceptions\BusinessResearchException;
use Closure;
use Illuminate\Support\Str;

/**
 * Investiga, paso a paso, los enlaces de un negocio ya elegido por el administrador.
 */
class BusinessInvestigator
{
    public const STEPS = [
        'ficha' => 'Ficha de Google (dirección, teléfono, horario, reseñas, mapa)',
        'web' => 'Página web del negocio',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'tiktok' => 'TikTok',
        'whatsapp' => 'WhatsApp',
    ];

    public function __construct(
        private WebsiteResearch $website,
        private SocialProfileSearch $socialSearch,
    ) {}

    /**
     * @return list<array{clave: string, etiqueta: string, estado: string, detalle: ?string, busqueda_manual: ?string}>
     */
    public static function initialSteps(): array
    {
        $steps = [];
        foreach (self::STEPS as $key => $label) {
            $steps[] = ['clave' => $key, 'etiqueta' => $label, 'estado' => 'pendiente', 'detalle' => null, 'busqueda_manual' => null];
        }

        return $steps;
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  Closure(array<string, mixed>): void  $save  Guarda el avance tras cada paso.
     * @return array<string, mixed>
     */
    public function investigate(array $job, Closure $save): array
    {
        $candidate = $job['elegido'];
        $job['pasos'] = self::initialSteps();
        $job['campos'] = [];
        $job['pendientes'] = ['Confirmar los datos y el logo con el encargado antes de publicar.'];
        $job['descartados'] = [];

        $run = function (string $key, Closure $callback) use (&$job, $save): void {
            $this->setStep($job, $key, 'en_curso', null);
            $save($job);
            try {
                [$state, $detail] = $callback();
            } catch (BusinessResearchException $exception) {
                [$state, $detail] = ['error', $exception->getMessage()];
            }
            $this->setStep($job, $key, $state, $detail);
            $save($job);
        };

        $run('ficha', function () use (&$job, $candidate): array {
            return $this->fromGoogleListing($job, $candidate);
        });
        $run('web', function () use (&$job, $candidate): array {
            return $this->fromWebsite($job, $candidate);
        });
        foreach (['instagram', 'facebook', 'tiktok'] as $network) {
            $run($network, function () use (&$job, $candidate, $network): array {
                return $this->fromSocialNetwork($job, $candidate, $network);
            });
        }
        $run('whatsapp', function () use (&$job): array {
            return $this->fromWhatsapp($job);
        });

        $job['estado'] = 'lista';

        return $job;
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $candidate
     * @return array{0: string, 1: ?string}
     */
    private function fromGoogleListing(array &$job, array $candidate): array
    {
        $source = $candidate['maps_url'] ?? 'https://www.google.com/maps';
        $this->setField($job, 'nombre', (string) $candidate['name'], true, 'google', $source);
        $this->setField($job, 'direccion', (string) ($candidate['address'] ?? ''), true, 'google', $source);
        $this->setField($job, 'rubro', (string) ($candidate['category'] ?? ''), true, 'google', $source);
        $this->setField($job, 'telefono', $this->phone($candidate['phone'] ?? null), true, 'google', $source);
        $this->setField($job, 'horario', $this->hours($candidate['opening_hours'] ?? []), true, 'google', $source);
        $this->setField($job, 'resenas', (string) $candidate['place_id'], true, 'google', $source);
        $this->setField($job, 'ubicacion', (string) $source, true, 'google', $source);

        $website = $candidate['website'] ?? null;
        if (is_string($website)) {
            $type = $candidate['website_type'] ?? 'web';
            $this->setField($job, $type, $type === 'web' ? $this->origin($website) : $this->normalizeSocial($website), true, 'google', $source);
        }

        $found = count($job['campos']);

        return $found > 0 ? ['encontrado', $found.' datos de la ficha'] : ['no_encontrado', 'La ficha no trae datos adicionales'];
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $candidate
     * @return array{0: string, 1: ?string}
     */
    private function fromWebsite(array &$job, array $candidate): array
    {
        if (($candidate['website_type'] ?? null) !== 'web' || ! is_string($candidate['website'] ?? null)) {
            return ['omitido', 'El negocio no tiene página web en su ficha de Google'];
        }

        $result = $this->website->research($candidate['website'], (string) $candidate['name']);
        $added = 0;
        foreach ($result['campos'] as $field => $data) {
            if (in_array($field, ['logo', 'nombre', 'direccion'], true) || isset($job['campos'][$field])) {
                continue;
            }
            $job['campos'][$field] = $data;
            $added++;
        }
        $job['descartados'] = [...$job['descartados'], ...$result['descartados']];
        if ($result['ia_error'] !== null) {
            $job['pendientes'][] = 'La IA no se pudo usar al leer la web: '.$result['ia_error'];
        }

        return $added > 0
            ? ['encontrado', $added.' datos nuevos leídos de '.parse_url($result['url'], PHP_URL_HOST)]
            : ['no_encontrado', 'La web no aportó datos nuevos'];
    }

    /**
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $candidate
     * @return array{0: string, 1: ?string}
     */
    private function fromSocialNetwork(array &$job, array $candidate, string $network): array
    {
        $label = self::STEPS[$network];
        if (isset($job['campos'][$network])) {
            return ['encontrado', 'Obtenido de '.($job['campos'][$network]['origen'] === 'google' ? 'la ficha de Google' : 'la web del negocio')];
        }

        $manual = 'https://www.google.com/search?'.http_build_query([
            'q' => '"'.$candidate['name'].'" '.Str::before((string) ($candidate['address'] ?? ''), ',').' '.$network,
        ]);
        $this->setStepManualSearch($job, $network, $manual);

        if ($this->socialSearch->enabled()) {
            $found = $this->webSearchProfiles($job, $candidate);
            if (isset($found[$network])) {
                $this->setField($job, $network, $found[$network]['url'], false, 'busqueda_web', $found[$network]['url']);

                return ['encontrado', 'Sugerido por Brave Search: «'.$found[$network]['titulo'].'». Confirma que es el mismo negocio'];
            }
            if (isset($job['busqueda_web_error'])) {
                return ['error', $job['busqueda_web_error']];
            }
        }

        return ['no_encontrado', 'No apareció '.$label.' en la ficha ni en la web'
            .($this->socialSearch->enabled() ? ' ni en Brave Search' : ' (la búsqueda web de redes no está configurada)')];
    }

    /**
     * Una sola ronda de búsqueda en Brave para las redes que aún faltan; el resultado se reutiliza en cada paso.
     *
     * @param  array<string, mixed>  $job
     * @param  array<string, mixed>  $candidate
     * @return array<string, array{url: string, titulo: string}>
     */
    private function webSearchProfiles(array &$job, array $candidate): array
    {
        if (array_key_exists('busqueda_web', $job)) {
            return $job['busqueda_web'];
        }

        $missing = array_values(array_filter(['instagram', 'facebook', 'tiktok'], fn (string $network): bool => ! isset($job['campos'][$network])));
        try {
            $job['busqueda_web'] = $this->socialSearch->find((string) $candidate['name'], (string) $job['ciudad'], $missing);
        } catch (BusinessResearchException $exception) {
            $job['busqueda_web'] = [];
            $job['busqueda_web_error'] = $exception->getMessage();
        }

        return $job['busqueda_web'];
    }

    /**
     * @param  array<string, mixed>  $job
     * @return array{0: string, 1: ?string}
     */
    private function fromWhatsapp(array &$job): array
    {
        if (isset($job['campos']['whatsapp'])) {
            return ['encontrado', 'Enlace de WhatsApp leído de la web del negocio'];
        }

        $phone = $job['campos']['telefono']['valor'] ?? null;
        if (is_string($phone) && str_starts_with($phone, '+569')) {
            $this->setField($job, 'whatsapp', $phone, false, 'telefono_movil', $job['campos']['telefono']['fuente']);

            return ['encontrado', 'Sugerido: el teléfono es móvil y podría tener WhatsApp; confírmalo con el encargado'];
        }

        return ['no_encontrado', 'No hay un número móvil ni un enlace de WhatsApp publicados'];
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function setField(array &$job, string $field, string $value, bool $verified, string $origin, string $source): void
    {
        $value = trim($value);
        if ($value === '' || isset($job['campos'][$field])) {
            return;
        }
        $job['campos'][$field] = ['valor' => mb_substr($value, 0, 1000), 'verificado' => $verified, 'origen' => $origin, 'fuente' => $source];
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function setStep(array &$job, string $key, string $state, ?string $detail): void
    {
        foreach ($job['pasos'] as &$step) {
            if ($step['clave'] === $key) {
                $step['estado'] = $state;
                $step['detalle'] = $detail;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function setStepManualSearch(array &$job, string $key, string $url): void
    {
        foreach ($job['pasos'] as &$step) {
            if ($step['clave'] === $key) {
                $step['busqueda_manual'] = $url;
            }
        }
    }

    private function phone(mixed $phone): string
    {
        $digits = preg_replace('/[^\d+]/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '+')) {
            return $digits;
        }

        return strlen($digits) === 9 ? '+56'.$digits : $digits;
    }

    /**
     * @param  array<string, string>  $hours
     */
    private function hours(array $hours): string
    {
        return (new OpeningHoursFormatter)->format($hours);
    }

    private function origin(string $url): string
    {
        $parts = parse_url($url);

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '');
    }

    private function normalizeSocial(string $url): string
    {
        $host = (string) parse_url($url, PHP_URL_HOST);
        $path = rtrim((string) parse_url($url, PHP_URL_PATH), '/');

        return 'https://'.preg_replace('/^(www\.|m\.|web\.)/', '', strtolower($host)).$path;
    }
}

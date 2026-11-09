<?php

namespace App\Filament\Resources\PaginaResource\Pages\Concerns;

use App\Support\LinkTypeHelper;
use App\Support\Research\BusinessResearchQueue;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;

/**
 * Busca un negocio por nombre, deja elegir el correcto y vuelca al formulario lo que el worker encuentre.
 */
trait ResearchesBusiness
{
    public const RESEARCH_FIELD_LABELS = [
        'nombre' => 'Nombre del local',
        'rubro' => 'Frase bajo el nombre (rubro)',
        'direccion' => 'Dirección',
        'telefono' => 'Teléfono',
        'horario' => 'Horario',
        'descripcion' => 'Descripción',
        'resenas' => 'Reseñas de Google (Place ID)',
        'ubicacion' => 'Enlace de ubicación (Google Maps)',
        'whatsapp' => 'Enlace de WhatsApp',
        'instagram' => 'Enlace de Instagram',
        'facebook' => 'Enlace de Facebook',
        'tiktok' => 'Enlace de TikTok',
        'web' => 'Enlace a la página web',
    ];

    private const RESEARCH_LINK_LABELS = [
        'review' => 'Déjanos una reseña',
        'location' => 'Cómo llegar',
        'whatsapp' => 'Escríbenos por WhatsApp',
        'instagram' => 'Síguenos en Instagram',
        'facebook' => 'Visita nuestro Facebook',
        'tiktok' => 'Síguenos en TikTok',
        'web' => 'Visita nuestra web',
    ];

    private const RESEARCH_HOURLY_LIMIT = 20;

    public string $researchName = '';

    public string $researchCity = '';

    #[Locked]
    public ?string $researchJobId = null;

    /** @var array<string, bool> */
    public array $researchSelected = [];

    /** @var array<string, bool> */
    #[Locked]
    public array $researchFilled = [];

    #[Locked]
    public bool $researchSelectionReady = false;

    public ?string $researchError = null;

    public function mountResearchesBusiness(): void
    {
        $this->researchCity = (string) config('clicktap.research.default_city', '');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function researchJob(): ?array
    {
        if ($this->researchJobId === null) {
            return null;
        }
        $job = app(BusinessResearchQueue::class)->find($this->researchJobId);

        return $job !== null && $job['user_id'] === (string) auth()->id() ? $job : null;
    }

    public function researchWorkerAvailable(): bool
    {
        return app(BusinessResearchQueue::class)->status()['workerAvailable'];
    }

    public function startResearch(): void
    {
        $this->researchError = null;
        $name = trim($this->researchName);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 120) {
            $this->researchError = 'Escribe el nombre del negocio (entre 3 y 120 caracteres).';

            return;
        }

        $rateKey = 'business-research:'.auth()->id();
        if (RateLimiter::tooManyAttempts($rateKey, self::RESEARCH_HOURLY_LIMIT)) {
            $this->researchError = 'Alcanzaste el límite de '.self::RESEARCH_HOURLY_LIMIT.' búsquedas por hora. Inténtalo más tarde.';

            return;
        }
        RateLimiter::hit($rateKey, 3600);

        $this->resetResearchState();
        $job = app(BusinessResearchQueue::class)->start((string) auth()->id(), $name, trim($this->researchCity));
        $this->researchJobId = $job['id'];
    }

    public function chooseResearchCandidate(int $index): void
    {
        $job = $this->researchJob();
        if ($job === null || app(BusinessResearchQueue::class)->choose($job['id'], $index) === null) {
            $this->researchError = 'Esa búsqueda ya no está disponible. Inténtalo de nuevo.';
        }
    }

    public function refreshResearch(): void
    {
        $job = $this->researchJob();
        if ($job === null || $job['estado'] !== 'lista' || $this->researchSelectionReady) {
            return;
        }

        foreach ($job['campos'] as $field => $data) {
            if (! array_key_exists($field, self::RESEARCH_FIELD_LABELS)) {
                continue;
            }
            $this->researchFilled[$field] = $this->researchFieldHasValue($field);
            $this->researchSelected[$field] = $data['verificado'] && ! $this->researchFilled[$field];
        }
        $this->researchSelectionReady = true;
    }

    public function cancelResearch(): void
    {
        if ($this->researchJobId !== null) {
            app(BusinessResearchQueue::class)->cancel($this->researchJobId);
        }
        $this->discardResearch();
    }

    public function discardResearch(): void
    {
        $this->resetResearchState();
        $this->researchJobId = null;
    }

    public function applyResearch(): void
    {
        $job = $this->researchJob();
        if ($job === null || $job['estado'] !== 'lista') {
            return;
        }

        $applied = 0;
        foreach ($job['campos'] as $field => $data) {
            if (! array_key_exists($field, self::RESEARCH_FIELD_LABELS) || ! ($this->researchSelected[$field] ?? false)) {
                continue;
            }
            $value = trim((string) $data['valor']);
            if ($value === '') {
                continue;
            }

            match ($field) {
                'nombre' => $this->applyResearchName($value),
                'rubro' => data_set($this->data, 'contenido_extra.tagline', Str::limit($value, 255, '')),
                'direccion' => data_set($this->data, 'direccion_publica', Str::limit($value, 255, '')),
                'telefono' => data_set($this->data, 'telefono_publico', Str::limit($value, 50, '')),
                'horario' => data_set($this->data, 'contenido_extra.schedule', Str::limit($value, 1000, '')),
                'descripcion' => data_set($this->data, 'descripcion', Str::words($value, 150, '')),
                'resenas' => $this->applyResearchReview($value),
                'ubicacion' => $this->applyResearchLink('location', $value),
                'whatsapp' => $this->applyResearchLink('whatsapp', 'https://wa.me/'.preg_replace('/\D/', '', $value)),
                'instagram', 'facebook', 'tiktok', 'web' => $this->applyResearchLink($field, $value),
                default => null,
            };
            $applied++;
        }

        $this->expandedLinkKey = null;
        $this->discardResearch();

        Notification::make()
            ->success()
            ->title($applied > 0 ? 'Datos aplicados al formulario' : 'No había datos seleccionados')
            ->body($applied > 0 ? 'Revísalos y guarda: la página se crea como borrador.' : null)
            ->send();
    }

    private function resetResearchState(): void
    {
        $this->researchSelected = [];
        $this->researchFilled = [];
        $this->researchSelectionReady = false;
        $this->researchError = null;
    }

    private function applyResearchName(string $name): void
    {
        $name = Str::limit($name, 255, '');
        data_set($this->data, 'nombre_publico', $name);
        if (blank($this->data['slug'] ?? null)) {
            data_set($this->data, 'slug', Str::slug($name));
        }
    }

    private function applyResearchReview(string $placeId): void
    {
        if (preg_match('/^[A-Za-z0-9_-]+$/', $placeId) !== 1) {
            return;
        }
        data_set($this->data, 'contenido_extra.place_id', $placeId);
        $this->applyResearchLink('review', (string) LinkTypeHelper::reviewUrlFromPlaceId($placeId), $placeId);
    }

    private function applyResearchLink(string $type, string $url, ?string $placeId = null): void
    {
        foreach ($this->data['enlaces'] ?? [] as $key => $link) {
            if (($link['tipo'] ?? null) !== $type) {
                continue;
            }
            if (blank($link['url'] ?? null) || $type === 'review') {
                $this->data['enlaces'][$key]['url'] = $url;
                if ($placeId !== null) {
                    $this->data['enlaces'][$key]['place_id'] = $placeId;
                }

                return;
            }
            if (strcasecmp(rtrim((string) $link['url'], '/'), rtrim($url, '/')) === 0) {
                return;
            }
        }

        $key = $this->addLinkOfType($type);
        if ($key !== null) {
            $this->data['enlaces'][$key]['url'] = $url;
            $this->data['enlaces'][$key]['etiqueta'] = self::RESEARCH_LINK_LABELS[$type] ?? $this->data['enlaces'][$key]['etiqueta'];
        }
    }

    private function researchFieldHasValue(string $field): bool
    {
        $linkTypes = ['ubicacion' => 'location', 'resenas' => 'review', 'whatsapp' => 'whatsapp', 'instagram' => 'instagram',
            'facebook' => 'facebook', 'tiktok' => 'tiktok', 'web' => 'web'];
        if (isset($linkTypes[$field])) {
            foreach ($this->data['enlaces'] ?? [] as $link) {
                if (($link['tipo'] ?? null) === $linkTypes[$field] && filled($link['url'] ?? null)) {
                    return true;
                }
            }

            return false;
        }

        $path = match ($field) {
            'nombre' => 'nombre_publico',
            'rubro' => 'contenido_extra.tagline',
            'direccion' => 'direccion_publica',
            'telefono' => 'telefono_publico',
            'horario' => 'contenido_extra.schedule',
            'descripcion' => 'descripcion',
            default => $field,
        };

        return filled(data_get($this->data, $path));
    }
}

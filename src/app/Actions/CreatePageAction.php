<?php

namespace App\Actions;

use App\Models\Cliente;
use App\Models\LocalNegocio;
use App\Models\Pagina;
use App\Models\User;
use App\Support\CatalogConnection;
use App\Support\PageRoute;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class CreatePageAction
{
    /**
     * @param  array<string, mixed>  $customerData
     * @param  array<string, mixed>  $locationData
     * @param  array<string, mixed>  $pageData
     * @param  list<array<string, mixed>>  $links
     */
    public function handle(
        User $actor,
        array $customerData,
        array $locationData,
        string $slug,
        array $pageData,
        array $links = [],
    ): Pagina {
        Gate::forUser($actor)->authorize('create', Pagina::class);

        foreach ($links as $position => &$link) {
            $link['posicion'] ??= $position;
            $link['activo'] ??= true;
            $link['copiar_texto'] ??= false;
        }
        unset($link);

        $pageData['activo'] ??= false;

        $rules = self::rules();
        if ($customerData === []) {
            $rules['cliente.nombre'] = ['nullable'];
        }

        $validated = Validator::make([
            'cliente' => $customerData,
            'local' => $locationData,
            'pagina' => $pageData,
            'enlaces' => $links,
        ], $rules)->validate();

        return CatalogConnection::transaction(function () use ($actor, $validated, $slug): Pagina {
            $clienteData = $validated['cliente'] ?? [];
            $cliente = isset($clienteData['nombre'])
                ? Cliente::query()->create(Arr::only($clienteData, [
                    'nombre', 'nombre_contacto', 'telefono_contacto', 'correo_contacto', 'notas_internas',
                ]))
                : null;

            $localData = Arr::only($validated['local'], [
                'nombre_interno', 'direccion_operativa', 'notas_internas',
            ]);
            $localData['cliente_id'] = $cliente?->getKey();
            $local = LocalNegocio::query()->create($localData);

            $pagina = new Pagina(Arr::only($validated['pagina'], self::pageFields()));
            $pagina->slug = PageRoute::clean($slug);
            $pagina->actualizada_por = $actor->getKey();
            $local->pagina()->save($pagina);

            foreach ($validated['enlaces'] as $link) {
                $pagina->enlaces()->create($link);
            }

            return $pagina->load('local.cliente', 'enlaces');
        });
    }

    /** @return list<string> */
    public static function pageFields(): array
    {
        return [
            'nombre_publico', 'descripcion', 'direccion_publica', 'telefono_publico',
            'correo_publico', 'logo_path', 'tema', 'color_fondo', 'color_texto',
            'color_boton', 'color_texto_boton', 'activo',
        ];
    }

    /** @return array<string, list<string>> */
    public static function rules(): array
    {
        return [
            'cliente.nombre' => ['required', 'string', 'max:255'],
            'cliente.nombre_contacto' => ['nullable', 'string', 'max:255'],
            'cliente.telefono_contacto' => ['nullable', 'string', 'max:50'],
            'cliente.correo_contacto' => ['nullable', 'email', 'max:255'],
            'cliente.notas_internas' => ['nullable', 'string'],
            'local.nombre_interno' => ['required', 'string', 'max:255'],
            'local.direccion_operativa' => ['nullable', 'string', 'max:255'],
            'local.notas_internas' => ['nullable', 'string'],
            'pagina.nombre_publico' => ['required', 'string', 'max:255'],
            'pagina.descripcion' => ['nullable', 'string', 'max:1000'],
            'pagina.direccion_publica' => ['nullable', 'string', 'max:255'],
            'pagina.telefono_publico' => ['nullable', 'string', 'max:50'],
            'pagina.correo_publico' => ['nullable', 'email', 'max:255'],
            'pagina.logo_path' => ['nullable', 'string', 'max:2048'],
            'pagina.tema' => ['nullable', 'string', 'max:50'],
            'pagina.color_fondo' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pagina.color_texto' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pagina.color_boton' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pagina.color_texto_boton' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'pagina.activo' => ['nullable', 'boolean'],
            'enlaces' => ['array'],
            'enlaces.*.tipo' => ['required', 'string', 'max:50'],
            'enlaces.*.etiqueta' => ['nullable', 'string', 'max:255'],
            'enlaces.*.url' => ['nullable', 'url:http,https', 'max:2048'],
            'enlaces.*.posicion' => ['required', 'integer', 'min:0', 'distinct'],
            'enlaces.*.activo' => ['required', 'boolean'],
            'enlaces.*.copiar_texto' => ['required', 'boolean'],
            'enlaces.*.texto_copia' => ['nullable', 'string', 'max:5000'],
        ];
    }
}

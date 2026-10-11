<?php

namespace App\Actions;

use App\Models\Pagina;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class UpdatePageAction
{
    /**
     * @param  array<string, mixed>  $content
     * @param  list<array<string, mixed>>  $links
     */
    public function handle(User $actor, Pagina $pagina, array $content, array $links): Pagina
    {
        foreach ($links as $position => &$link) {
            $link['posicion'] ??= $position;
            $link['activo'] ??= true;
            $link['copiar_texto'] ??= false;
        }
        unset($link);

        $pageRules = [];
        foreach (CreatePageAction::rules() as $field => $fieldRules) {
            if (str_starts_with($field, 'pagina.') || str_starts_with($field, 'enlaces')) {
                $pageRules[$field] = $fieldRules;
            }
        }

        $validated = Validator::make([
            'pagina' => $content,
            'enlaces' => $links,
        ], $pageRules)->validate();

        return DB::transaction(function () use ($actor, $pagina, $validated): Pagina {
            $pagina = Pagina::query()->whereKey($pagina->getKey())->lockForUpdate()->firstOrFail();
            Gate::forUser($actor)->authorize('update', $pagina);

            $pagina->fill(Arr::only($validated['pagina'], CreatePageAction::pageFields()));
            $pagina->actualizada_por = $actor->getKey();
            $pagina->save();

            $pagina->enlaces()->delete();
            foreach ($validated['enlaces'] as $link) {
                $pagina->enlaces()->create($link);
            }

            return $pagina->load('enlaces');
        });
    }
}

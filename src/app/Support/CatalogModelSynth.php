<?php

namespace App\Support;

use App\Models\Cliente;
use App\Models\EnlacePagina;
use App\Models\LocalNegocio;
use App\Models\Pagina;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Features\SupportModels\ModelSynth;

class CatalogModelSynth extends ModelSynth
{
    public static $key = 'catalog_model';

    public static function match($target): bool
    {
        return CatalogConnection::enabled() && ($target instanceof Cliente || $target instanceof LocalNegocio
            || $target instanceof Pagina || $target instanceof EnlacePagina);
    }

    public function hydrate($data, $meta): Model
    {
        $class = Relation::getMorphedModel($meta['class']) ?? $meta['class'];
        $model = new $class;
        abort_unless(self::match($model), 403);

        return array_key_exists('key', $meta)
            ? $model->newQueryForRestoration($meta['key'])->firstOrFail()
            : $model;
    }
}

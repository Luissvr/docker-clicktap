<?php

namespace App\Models;

use App\Support\UsesCatalogConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AlbumPagina extends Model
{
    use HasUuids, UsesCatalogConnection;

    protected $table = 'albumes_paginas';

    protected $fillable = ['nombre', 'fecha_ruta', 'sector'];

    protected function casts(): array
    {
        return ['fecha_ruta' => 'date'];
    }

    public function paginas(): BelongsToMany
    {
        return $this->belongsToMany(Pagina::class, 'album_pagina', 'album_id', 'pagina_id');
    }
}

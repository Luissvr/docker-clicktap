<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlacePagina extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'enlaces_pagina';

    protected $fillable = [
        'tipo',
        'etiqueta',
        'url',
        'posicion',
        'activo',
        'copiar_texto',
        'texto_copia',
    ];

    protected function casts(): array
    {
        return [
            'posicion' => 'integer',
            'activo' => 'boolean',
            'copiar_texto' => 'boolean',
        ];
    }

    public function pagina(): BelongsTo
    {
        return $this->belongsTo(Pagina::class, 'pagina_id');
    }
}

<?php

namespace App\Models;

use App\Support\MenuFile;
use App\Support\UsesCatalogConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnlacePagina extends Model
{
    use HasFactory, HasUuids;
    use UsesCatalogConnection;

    protected $table = 'enlaces_pagina';

    protected $fillable = [
        'tipo',
        'banco_transferencia',
        'nombre',
        'etiqueta',
        'url',
        'archivo_path',
        'posicion',
        'activo',
        'copiar_texto',
        'texto_copia',
    ];

    protected static function booted(): void
    {
        static::saving(function (EnlacePagina $enlace): void {
            if ($enlace->tipo !== 'menu') {
                $enlace->archivo_path = null;
            } else {
                $enlace->url = null;
                $enlace->copiar_texto = false;
            }
        });

        static::updated(function (EnlacePagina $enlace): void {
            if ($enlace->wasChanged('archivo_path')) {
                MenuFile::delete($enlace->getOriginal('archivo_path'));
            }
        });

        static::deleted(function (EnlacePagina $enlace): void {
            MenuFile::delete($enlace->archivo_path);
        });
    }

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

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cliente extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'clientes';

    protected $fillable = [
        'nombre',
        'nombre_contacto',
        'telefono_contacto',
        'correo_contacto',
        'notas_internas',
    ];

    protected function casts(): array
    {
        return [
            'archivado_en' => 'datetime',
        ];
    }

    public function locales(): HasMany
    {
        return $this->hasMany(LocalNegocio::class, 'cliente_id');
    }
}

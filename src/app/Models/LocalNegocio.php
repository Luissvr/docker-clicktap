<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class LocalNegocio extends Model
{
    use \App\Support\TracksCatalogChanges, HasFactory, HasUuids;

    protected $table = 'locales';

    protected $fillable = [
        'cliente_id',
        'nombre_interno',
        'direccion_operativa',
        'notas_internas',
    ];

    protected function casts(): array
    {
        return [
            'archivado_en' => 'datetime',
        ];
    }

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function pagina(): HasOne
    {
        return $this->hasOne(Pagina::class, 'local_id');
    }
}

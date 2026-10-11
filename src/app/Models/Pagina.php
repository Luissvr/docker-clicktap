<?php

namespace App\Models;

use App\Support\PageRoute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Pagina extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'paginas';

    protected $fillable = [
        'local_id',
        'slug',
        'nombre_publico',
        'descripcion',
        'direccion_publica',
        'telefono_publico',
        'correo_publico',
        'logo_path',
        'tema',
        'color_fondo',
        'color_texto',
        'color_boton',
        'color_texto_boton',
        'activo',
        'actualizada_por',
        'publicacion_estado',
        'publicacion_commit',
        'publicacion_error',
        'publicada_en',
    ];

    protected static function booted(): void
    {
        static::creating(function (Pagina $pagina): void {
            $pagina->slug = PageRoute::clean($pagina->slug);
            PageRoute::assertAvailable($pagina->slug);
        });

        static::updating(function (Pagina $pagina): void {
            if ($pagina->isDirty('slug')) {
                throw new LogicException('El slug de una página no se puede modificar.');
            }
        });
    }

    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'publicada_en' => 'datetime',
        ];
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(LocalNegocio::class, 'local_id');
    }

    public function enlaces(): HasMany
    {
        return $this->hasMany(EnlacePagina::class, 'pagina_id')->orderBy('posicion');
    }

    public function actualizadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizada_por');
    }
}

<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, HasUuids, Notifiable;

    public function getConnectionName(): ?string
    {
        if ($this->connection === 'catalog') {
            return config('database.default');
        }

        return $this->connection ?? config('auth.database_connection', config('database.default'));
    }

    protected $attributes = [
        'activo' => true,
    ];

    protected $fillable = [
        'name',
        'email',
        'password',
        'activo',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'activo' => 'boolean',
        ];
    }

    public function paginasActualizadas(): HasMany
    {
        return $this->hasMany(Pagina::class, 'actualizada_por');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->activo;
    }
}

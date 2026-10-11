<?php

namespace App\Policies;

use App\Models\EnlacePagina;
use App\Models\User;

class EnlacePaginaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, EnlacePagina $enlace): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function update(User $user, EnlacePagina $enlace): bool
    {
        return $user->activo;
    }

    public function delete(User $user, EnlacePagina $enlace): bool
    {
        return $user->activo;
    }
}

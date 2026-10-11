<?php

namespace App\Policies;

use App\Models\LocalNegocio;
use App\Models\User;

class LocalNegocioPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, LocalNegocio $localNegocio): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function update(User $user, LocalNegocio $localNegocio): bool
    {
        return $user->activo;
    }

    public function delete(User $user, LocalNegocio $localNegocio): bool
    {
        return false;
    }
}

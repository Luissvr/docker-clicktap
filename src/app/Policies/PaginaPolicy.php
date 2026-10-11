<?php

namespace App\Policies;

use App\Models\Pagina;
use App\Models\User;

class PaginaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Pagina $pagina): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function update(User $user, Pagina $pagina): bool
    {
        return $user->activo;
    }

    public function delete(User $user, Pagina $pagina): bool
    {
        return false;
    }
}

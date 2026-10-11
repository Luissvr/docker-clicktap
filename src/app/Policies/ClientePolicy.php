<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Models\User;

class ClientePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->activo;
    }

    public function view(User $user, Cliente $cliente): bool
    {
        return $user->activo;
    }

    public function create(User $user): bool
    {
        return $user->activo;
    }

    public function update(User $user, Cliente $cliente): bool
    {
        return $user->activo;
    }

    public function delete(User $user, Cliente $cliente): bool
    {
        return false;
    }
}

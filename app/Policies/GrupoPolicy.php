<?php

namespace App\Policies;

use App\Models\Grupo;
use App\Models\User;

class GrupoPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Grupo $grupo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Grupo $grupo): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Grupo $grupo): bool
    {
        return $user->isAdmin();
    }
}

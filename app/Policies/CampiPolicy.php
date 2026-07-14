<?php

namespace App\Policies;

use App\Models\Campi;
use App\Models\User;

class CampiPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Campi $campi): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Campi $campi): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Campi $campi): bool
    {
        return $user->isAdmin();
    }
}

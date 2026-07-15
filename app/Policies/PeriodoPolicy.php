<?php

namespace App\Policies;

use App\Models\Periodo;
use App\Models\User;

class PeriodoPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Periodo $periodo): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Periodo $periodo): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Periodo $periodo): bool
    {
        return $user->isAdmin();
    }
}

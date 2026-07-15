<?php

namespace App\Policies;

use App\Models\Recurso;
use App\Models\User;

class RecursoPolicy
{
    public function viewAny(?User $user): bool
    {
        return $user !== null;
    }

    public function view(?User $user, Recurso $recurso): bool
    {
        return $user !== null;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Recurso $recurso): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Recurso $recurso): bool
    {
        return $user->isAdmin();
    }

    public function verAgenda(User $user, Recurso $recurso): bool
    {
        if ($user->isAdmin()) return true;
        return strtolower(trim($user->email)) === strtolower(trim($recurso->responsavel_email));
    }
}

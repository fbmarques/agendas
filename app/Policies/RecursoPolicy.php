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
        return $user->isAdmin() || $recurso->temGerente($user);
    }

    public function delete(User $user, Recurso $recurso): bool
    {
        return $user->isAdmin();
    }

    public function gerenciarGerentes(User $user, Recurso $recurso): bool
    {
        return $user->isAdmin();
    }

    public function verAgenda(User $user, Recurso $recurso): bool
    {
        if ($user->isAdmin()) return true;
        if ($recurso->temGerente($user)) return true;
        if ($recurso->responsavel_email
            && strtolower(trim($user->email)) === strtolower(trim($recurso->responsavel_email))) {
            return true;
        }
        return false;
    }
}

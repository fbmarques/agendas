<?php

namespace App\Policies;

use App\Models\LocalIndisponibilidade;
use App\Models\User;

class LocalIndisponibilidadePolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, LocalIndisponibilidade $li): bool
    {
        return true;
    }

    public function create(User $user, ?LocalIndisponibilidade $li = null): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, LocalIndisponibilidade $li): bool
    {
        if ($user->isAdmin()) return true;
        $local = $li->local;
        return $local ? $local->temGerente($user) : false;
    }

    public function delete(User $user, LocalIndisponibilidade $li): bool
    {
        return $this->update($user, $li);
    }
}

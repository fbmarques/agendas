<?php

namespace App\Policies;

use App\Models\Local;
use App\Models\User;

class LocalPolicy
{
    public function viewAny(?User $user): bool
    {
        return true;
    }

    public function view(?User $user, Local $local): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Local $local): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Local $local): bool
    {
        return $user->isAdmin();
    }
}

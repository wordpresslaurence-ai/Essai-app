<?php

namespace App\Policies;

use App\Models\Opportunite;
use App\Models\User;

/**
 * Autorisations sur les opportunités (usage solo : tous droits, extensible).
 */
class OpportunitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Opportunite $opportunite): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Opportunite $opportunite): bool
    {
        return true;
    }

    public function delete(User $user, Opportunite $opportunite): bool
    {
        return true;
    }
}

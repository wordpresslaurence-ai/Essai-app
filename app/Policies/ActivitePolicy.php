<?php

namespace App\Policies;

use App\Models\Activite;
use App\Models\User;

/**
 * Autorisations sur les activités (usage solo : tous droits, extensible).
 */
class ActivitePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Activite $activite): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Activite $activite): bool
    {
        return true;
    }

    public function delete(User $user, Activite $activite): bool
    {
        return true;
    }
}

<?php

namespace App\Policies;

use App\Models\Etiquette;
use App\Models\User;

/**
 * Autorisations sur les étiquettes (cf. plan §7, constitution art. 6.4).
 * En usage solo, tout utilisateur authentifié a l'ensemble des droits.
 */
class EtiquettePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Etiquette $etiquette): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Etiquette $etiquette): bool
    {
        return true;
    }

    public function delete(User $user, Etiquette $etiquette): bool
    {
        return true;
    }
}

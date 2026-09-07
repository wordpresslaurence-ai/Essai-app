<?php

namespace App\Policies;

use App\Models\Contact;
use App\Models\User;

/**
 * Autorisations sur les contacts (cf. plan §7, constitution art. 6.4).
 *
 * En usage solo, tout utilisateur authentifié a l'ensemble des droits. Cette
 * classe encapsule cette règle afin de pouvoir la restreindre plus tard
 * (plusieurs utilisateurs, rôles) sans toucher au reste du code.
 */
class ContactPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Contact $contact): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Contact $contact): bool
    {
        return true;
    }

    public function delete(User $user, Contact $contact): bool
    {
        return true;
    }
}

<?php

namespace App\Livewire\Reglages;

use App\Models\Parametre;
use App\Models\ReservationRecue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Réglage de la réception des réservations du site (brique 013) :
 * adresse secrète à coller dans FluentBooking et journal des appels reçus.
 */
#[Layout('layouts.app')]
class ReservationsSite extends Component
{
    public ?int $detailId = null;

    /** Crée l'adresse, ou la remplace (l'ancienne cesse alors de fonctionner). */
    public function generer(): void
    {
        Parametre::instance()->update(['webhook_jeton' => Str::random(40)]);
        session()->flash('message', 'Nouvelle adresse créée. Colle-la dans FluentBooking.');
    }

    public function basculerDetail(int $id): void
    {
        $this->detailId = $this->detailId === $id ? null : $id;
    }

    public function render(): View
    {
        $jeton = Parametre::instance()->webhook_jeton;

        return view('livewire.reglages.reservations-site', [
            'adresse' => filled($jeton) ? route('webhooks.reservations', $jeton) : null,
            'recues' => ReservationRecue::with(['contact', 'seance'])->orderByDesc('id')->limit(30)->get(),
        ]);
    }
}

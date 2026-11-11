<?php

namespace App\Livewire\Reglages;

use App\Models\Activite;
use App\Models\Inscription;
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

    /**
     * Supprime une réservation reçue ET ce qu'elle avait créé : l'inscription à la
     * session, ou le rendez-vous, ainsi que la note d'historique. Le contact est
     * conservé (il se supprime depuis sa fiche si besoin).
     */
    public function supprimer(int $id): void
    {
        $ligne = ReservationRecue::with('seance')->find($id);
        if (! $ligne) {
            return;
        }

        if ($ligne->contact_id) {
            if ($ligne->seance && $ligne->statut === ReservationRecue::STATUT_INSCRIT) {
                $inscription = $ligne->seance->inscriptions()
                    ->where('contact_id', $ligne->contact_id)
                    ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
                    ->first();

                if ($inscription) {
                    $etaitConfirme = $inscription->estConfirme();
                    $inscription->delete();
                    if ($etaitConfirme) {
                        $ligne->seance->promouvoirSuivant();
                    }
                }
            }

            // Activités créées par cette réservation (même instant, mêmes intitulés).
            Activite::where('contact_id', $ligne->contact_id)
                ->whereBetween('created_at', [$ligne->created_at->copy()->subSeconds(15), $ligne->created_at->copy()->addSeconds(15)])
                ->where(fn ($q) => $q
                    ->where('titre', 'like', 'Rendez-vous réservé : %')
                    ->orWhere('titre', 'like', 'Inscription via le site : %')
                    ->orWhere('titre', 'like', 'Inscription annulée depuis le site : %')
                    ->orWhere('titre', 'like', 'Réservation annulée : %'))
                ->delete();
        }

        if ($this->detailId === $ligne->id) {
            $this->detailId = null;
        }

        $ligne->delete();
        session()->flash('message', 'Réservation supprimée. Le contact est conservé.');
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

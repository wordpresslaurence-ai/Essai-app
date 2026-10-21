<?php

namespace App\Services\Relances;

use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Opportunite;
use App\Models\Parametre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Calcule les contacts « indécis » à réveiller (brique 011), sans IA :
 *  - leads chauds restés sans suite ;
 *  - contacts qui s'étaient intéressés à une session passée (attente / annulé)
 *    sans place confirmée à venir.
 */
class CalculIndecis
{
    private int $jours;

    public function __construct(?int $jours = null)
    {
        $this->jours = $jours ?? Parametre::instance()->relance_delai_jours ?? 5;
    }

    /**
     * Leads « chaud » ou « intérêt élevé », actifs, sans inscription confirmée ni
     * opportunité gagnée, et silencieux depuis plus de N jours.
     *
     * @return Collection<int, Contact>
     */
    public function leadsChaudsSansSuite(?int $poleId = null): Collection
    {
        $limite = now()->subDays($this->jours);

        return Contact::query()
            ->actifs()
            ->whereIn('temperature', ['chaud', 'eleve'])
            ->where('created_at', '<', $limite)
            ->whereDoesntHave('inscriptions', fn (Builder $q) => $q->where('statut', Inscription::STATUT_CONFIRME))
            ->whereDoesntHave('opportunites', fn (Builder $q) => $q->where('etape', Opportunite::ETAPE_GAGNE))
            ->whereDoesntHave('emails', fn (Builder $q) => $q->where('date_email', '>=', $limite))
            ->when($poleId, fn (Builder $q) => $q->whereHas('categorie', fn (Builder $c) => $c->where('pole_id', $poleId)))
            ->with('entreprise')
            ->latest()
            ->get();
    }

    /**
     * Contacts actifs ayant une inscription en attente ou annulée à une séance
     * passée, sans place confirmée à une séance à venir.
     *
     * @return Collection<int, Contact>
     */
    public function interessesSessionPassee(?int $poleId = null): Collection
    {
        return Contact::query()
            ->actifs()
            ->whereHas('inscriptions', fn (Builder $q) => $q
                ->whereIn('statut', [Inscription::STATUT_ATTENTE, Inscription::STATUT_ANNULE])
                ->whereHas('seance', fn (Builder $s) => $s->passees()))
            ->whereDoesntHave('inscriptions', fn (Builder $q) => $q
                ->where('statut', Inscription::STATUT_CONFIRME)
                ->whereHas('seance', fn (Builder $s) => $s->aVenir()))
            ->when($poleId, fn (Builder $q) => $q->whereHas('categorie', fn (Builder $c) => $c->where('pole_id', $poleId)))
            ->with('entreprise')
            ->latest()
            ->get();
    }
}

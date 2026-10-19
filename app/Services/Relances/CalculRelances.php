<?php

namespace App\Services\Relances;

use App\Models\Contact;
use App\Models\Email;
use App\Models\Parametre;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calcule, à partir des e-mails synchronisés, les contacts à suivre :
 *  - « réponses dues » : le dernier message est ENTRANT (le client attend une réponse) ;
 *  - « à relancer »     : le dernier message est SORTANT et date de plus de N jours.
 * Logique locale, sans IA ni réseau.
 */
class CalculRelances
{
    /** Délai (jours) au-delà duquel un message sortant sans réponse devient « à relancer ». */
    private int $delaiJours;

    public function __construct(?int $delaiJours = null)
    {
        $this->delaiJours = $delaiJours ?? Parametre::instance()->relance_delai_jours ?? 5;
    }

    /**
     * Le client a écrit en dernier : une réponse est attendue (prioritaire).
     *
     * @return Collection<int, array{contact: Contact, dernier: Email}>
     */
    public function reponsesDues(): Collection
    {
        return $this->lignes()
            ->filter(fn ($l) => $l['dernier']->estEntrant())
            ->values();
    }

    /**
     * Laurence a écrit en dernier et le délai est dépassé : à relancer.
     *
     * @return Collection<int, array{contact: Contact, dernier: Email}>
     */
    public function aRelancer(): Collection
    {
        $limite = Carbon::now()->subDays($this->delaiJours);

        return $this->lignes()
            ->filter(fn ($l) => ! $l['dernier']->estEntrant()
                && $l['dernier']->date_email !== null
                && $l['dernier']->date_email->lessThan($limite))
            ->values();
    }

    /**
     * Dernier e-mail par contact, du plus ancien échange au plus récent.
     *
     * @return Collection<int, array{contact: Contact, dernier: Email}>
     */
    private function lignes(): Collection
    {
        return Contact::query()
            ->has('emails')
            ->with(['emails' => fn ($q) => $q->orderByDesc('date_email')])
            ->get()
            ->map(fn (Contact $c) => ['contact' => $c, 'dernier' => $c->emails->first()])
            ->sortBy(fn ($l) => $l['dernier']->date_email)
            ->values();
    }
}

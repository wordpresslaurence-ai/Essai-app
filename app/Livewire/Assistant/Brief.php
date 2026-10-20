<?php

namespace App\Livewire\Assistant;

use App\Models\Activite;
use App\Models\Contact;
use App\Services\Email\SynchronisationEmails;
use App\Services\IA\AssistantIA;
use App\Services\Relances\CalculRelances;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Brief du matin : réponses dues, à relancer (calcul local), et suggestions
 * proactives (IA, à la demande pour maîtriser le coût). Bouton de synchronisation.
 */
#[Layout('layouts.app')]
class Brief extends Component
{
    /** @var list<string> */
    public array $suggestions = [];

    public bool $suggestionsChargees = false;

    public ?string $suggestionsErreur = null;

    /** Lance la synchronisation des e-mails. */
    public function synchroniser(): void
    {
        try {
            $resultat = app(SynchronisationEmails::class)->executer();
            session()->flash('message', $resultat->resume());
        } catch (Throwable $e) {
            session()->flash('erreur', $e->getMessage());
        }
    }

    /** Demande à l'assistant des suggestions pour la journée (un seul appel). */
    public function genererSuggestions(): void
    {
        $this->reset('suggestionsErreur');
        $calc = new CalculRelances;

        $contexte = [
            'reponses_dues' => $calc->reponsesDues()
                ->map(fn ($l) => $l['contact']->nomComplet())->all(),
            'a_relancer' => $calc->aRelancer()
                ->map(fn ($l) => $l['contact']->nomComplet())->all(),
        ];

        try {
            $this->suggestions = app(AssistantIA::class)->suggestions($contexte);
            $this->suggestionsChargees = true;
        } catch (Throwable $e) {
            $this->suggestionsErreur = $e->getMessage();
        }
    }

    /** Préfixe de titre selon l'intention. */
    private function prefixe(string $intention): string
    {
        return $intention === 'relancer' ? 'Relancer ' : 'Répondre à ';
    }

    /**
     * Crée une tâche de suivi pour un contact, sans doublon : on ne recrée pas
     * une tâche non terminée de même intention (« Répondre à … » / « Relancer … »).
     */
    public function creerActivite(int $contactId, string $intention): void
    {
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }

        $prefixe = $this->prefixe($intention);
        $titre = $prefixe.$contact->nomComplet();

        $existe = Activite::where('contact_id', $contactId)
            ->whereNull('terminee_at')
            ->where('titre', 'like', $prefixe.'%')
            ->exists();

        if ($existe) {
            session()->flash('message', 'Une activité de ce type existe déjà pour ce contact.');

            return;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $contactId,
            'echeance' => now(),
        ]);

        session()->flash('message', 'Activité créée : '.$titre);
    }

    public function render(): View
    {
        $calc = new CalculRelances;

        // Contacts qui ont déjà une tâche non terminée de chaque intention.
        $dejaRepondre = Activite::whereNull('terminee_at')
            ->where('titre', 'like', 'Répondre à %')
            ->pluck('contact_id')->filter()->all();
        $dejaRelancer = Activite::whereNull('terminee_at')
            ->where('titre', 'like', 'Relancer %')
            ->pluck('contact_id')->filter()->all();

        return view('livewire.assistant.brief', [
            'reponsesDues' => $calc->reponsesDues(),
            'aRelancer' => $calc->aRelancer(),
            'dejaRepondre' => $dejaRepondre,
            'dejaRelancer' => $dejaRelancer,
        ]);
    }
}

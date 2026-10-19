<?php

namespace App\Livewire\Assistant;

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

    public function render(): View
    {
        $calc = new CalculRelances;

        return view('livewire.assistant.brief', [
            'reponsesDues' => $calc->reponsesDues(),
            'aRelancer' => $calc->aRelancer(),
        ]);
    }
}

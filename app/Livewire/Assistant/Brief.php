<?php

namespace App\Livewire\Assistant;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\Pole;
use App\Services\Email\SynchronisationEmails;
use App\Services\IA\AssistantIA;
use App\Services\Relances\CalculIndecis;
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
    /** Pôle sur lequel filtrer les listes (null/vide = tous les pôles). */
    public ?string $poleFiltre = null;

    /** @var list<string> */
    public array $suggestions = [];

    public bool $suggestionsChargees = false;

    public ?string $suggestionsErreur = null;

    /** Échéance appliquée aux tâches « Répondre » / « Relancer » créées depuis le brief. */
    public ?string $echeanceReponse = null;

    public ?string $echeanceRelance = null;

    /** Détail de la dernière synchro (mails rattachés + prospects à créer). */
    public array $detailSync = [];

    public function mount(): void
    {
        $this->echeanceReponse = now()->toDateString();
        $this->echeanceRelance = now()->toDateString();

        // Les tâches « Répondre »/« Relancer » dont le motif a disparu se clôturent seules.
        $this->cloturerTachesResolues();
    }

    /** Lance la synchronisation des e-mails. */
    public function synchroniser(): void
    {
        try {
            $resultat = app(SynchronisationEmails::class)->executer();
            session()->flash('message', $resultat->resume());
            $this->detailSync = [
                'imports' => $resultat->imports,
                'prospects' => $resultat->prospects,
            ];
            $this->cloturerTachesResolues();
        } catch (Throwable $e) {
            session()->flash('erreur', $e->getMessage());
            $this->detailSync = [];
        }
    }

    /**
     * Clôture automatiquement les tâches « Répondre à … » / « Relancer … » non
     * terminées dont le contact n'est plus, respectivement, en « réponse due » ou
     * « à relancer » (parce que tu as répondu / la relance est partie).
     */
    private function cloturerTachesResolues(): void
    {
        $calc = new CalculRelances;

        $dues = $calc->reponsesDues()->map(fn ($l) => $l['contact']->id)->all();
        $relances = $calc->aRelancer()->map(fn ($l) => $l['contact']->id)->all();

        Activite::whereNull('terminee_at')
            ->whereNotNull('contact_id')
            ->where('titre', 'like', 'Répondre à %')
            ->whereNotIn('contact_id', $dues)
            ->update(['terminee_at' => now()]);

        Activite::whereNull('terminee_at')
            ->whereNotNull('contact_id')
            ->where('titre', 'like', 'Relancer %')
            ->whereNotIn('contact_id', $relances)
            ->update(['terminee_at' => now()]);
    }

    /** Demande à l'assistant des suggestions pour la journée (un seul appel). */
    public function genererSuggestions(): void
    {
        $this->reset('suggestionsErreur');
        $calc = new CalculRelances(null, $this->poleIdFiltre());

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

        $echeance = $intention === 'relancer' ? $this->echeanceRelance : $this->echeanceReponse;

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $contactId,
            'echeance' => filled($echeance) ? $echeance : now()->toDateString(),
        ]);

        session()->flash('message', 'Activité créée : '.$titre);
    }

    /** Crée une tâche de relance pour une opportunité qui stagne (sans doublon). */
    public function creerTacheOpportunite(int $opportuniteId): void
    {
        $opp = Opportunite::find($opportuniteId);
        if (! $opp) {
            return;
        }

        $titre = 'Relancer l’opportunité : '.$opp->titre;

        $existe = Activite::where('opportunite_id', $opp->id)
            ->whereNull('terminee_at')
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            session()->flash('message', 'Une relance existe déjà pour cette opportunité.');

            return;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $opp->contact_id,
            'opportunite_id' => $opp->id,
            'echeance' => now()->toDateString(),
        ]);

        session()->flash('message', 'Relance créée : '.$titre);
    }

    /** Identifiant de pôle à filtrer (int), ou null si « tous les pôles ». */
    private function poleIdFiltre(): ?int
    {
        return filled($this->poleFiltre) ? (int) $this->poleFiltre : null;
    }

    public function render(): View
    {
        $pole = $this->poleIdFiltre();
        $calc = new CalculRelances(null, $pole);
        $indecis = new CalculIndecis;

        // Contacts qui ont déjà une tâche non terminée de chaque intention.
        $dejaRepondre = Activite::whereNull('terminee_at')
            ->where('titre', 'like', 'Répondre à %')
            ->pluck('contact_id')->filter()->all();
        $dejaRelancer = Activite::whereNull('terminee_at')
            ->where('titre', 'like', 'Relancer %')
            ->pluck('contact_id')->filter()->all();

        // Opportunités déjà relancées (tâche ouverte liée).
        $dejaOpp = Activite::whereNull('terminee_at')
            ->whereNotNull('opportunite_id')
            ->where('titre', 'like', 'Relancer l’opportunité%')
            ->pluck('opportunite_id')->filter()->all();

        $stagnantes = Opportunite::stagnantes()
            ->with('contact')
            ->when($pole, fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $pole)))
            ->orderBy('etape_changee_le')
            ->get();

        return view('livewire.assistant.brief', [
            'reponsesDues' => $calc->reponsesDues(),
            'aRelancer' => $calc->aRelancer(),
            'dejaRepondre' => $dejaRepondre,
            'dejaRelancer' => $dejaRelancer,
            'poles' => Pole::orderBy('ordre')->orderBy('nom')->get(),
            'stagnantes' => $stagnantes,
            'dejaOpp' => $dejaOpp,
            'indecisChauds' => $indecis->leadsChaudsSansSuite($pole),
            'indecisSessions' => $indecis->interessesSessionPassee($pole),
        ]);
    }
}

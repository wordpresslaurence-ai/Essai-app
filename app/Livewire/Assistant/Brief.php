<?php

namespace App\Livewire\Assistant;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Email;
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

        // Suivi après séance : une tâche par participant dès le lendemain.
        app(\App\Services\Sessions\SuiviApresSeance::class)->creerTaches(auth()->user()?->name);
        app(\App\Services\Sessions\RappelsCycle::class)->creerTaches();

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
        // Une relance reste justifiée pour un e-mail sans réponse, mais aussi pour un
        // « indécis » (lead chaud sans suite, intéressé d'une session passée).
        $indecis = new CalculIndecis;
        // (tableaux simples : une collection Eloquent vide ne sait pas fusionner des identifiants)
        $relances = array_values(array_unique(array_merge(
            $calc->aRelancer()->map(fn ($l) => $l['contact']->id)->all(),
            $indecis->leadsChaudsSansSuite()->modelKeys(),
            $indecis->interessesSessionPassee()->modelKeys(),
        )));

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

    /**
     * « Conversation terminée » : sort le contact des listes « réponse due »,
     * « à relancer » et « indécis », et clôture ses tâches correspondantes. Le suivi reprend seul
     * dès qu'un e-mail est reçu de lui ou envoyé vers lui.
     */
    public function terminerConversation(int $contactId): void
    {
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }

        $contact->mettreSuiviEnPause();
        $this->cloturerTachesResolues();

        session()->flash('message', 'Conversation avec '.$contact->nomComplet().' terminée : plus de relance tant qu’aucun nouvel e-mail n’est échangé.');
    }

    /** Demande à l'assistant un plan du jour (un seul appel, contexte complet). */
    public function genererSuggestions(): void
    {
        $this->reset('suggestionsErreur');
        $pole = $this->poleIdFiltre();
        $calc = new CalculRelances(null, $pole);
        $indecis = new CalculIndecis;

        // On donne à l'IA tout le tableau de bord pour qu'elle priorise et croise
        // les signaux (agenda + pipeline + leads + sessions), pas seulement 2 listes.
        $contexte = [
            'date_du_jour' => now()->toDateString(),
            'reponses_dues' => $calc->reponsesDues()->map(fn ($l) => $l['contact']->nomComplet())->all(),
            'a_relancer' => $calc->aRelancer()->map(fn ($l) => $l['contact']->nomComplet())->all(),
            'taches_du_jour_ou_en_retard' => $this->contexteTaches($pole),
            'opportunites_stagnantes' => $this->contexteOpportunites($pole),
            'leads_chauds_sans_suite' => $indecis->leadsChaudsSansSuite($pole)->map(fn ($c) => $c->nomComplet())->values()->all(),
            'interesses_session_passee' => $indecis->interessesSessionPassee($pole)->map(fn ($c) => $c->nomComplet())->values()->all(),
            'prospects_a_creer' => Activite::whereNull('terminee_at')
                ->where('titre', 'like', 'Créer le prospect : %')->count(),
            'sessions_a_venir_avec_places' => $this->contexteSessions($pole),
        ];

        try {
            $this->suggestions = app(AssistantIA::class)->suggestions($contexte);
            $this->suggestionsChargees = true;
        } catch (Throwable $e) {
            $this->suggestionsErreur = $e->getMessage();
        }
    }

    /**
     * Tâches à faire dont l'échéance est aujourd'hui ou passée (les plus urgentes
     * d'abord), éventuellement filtrées par pôle via le contact.
     *
     * @return list<array<string, mixed>>
     */
    private function contexteTaches(?int $pole): array
    {
        return Activite::aFaire()
            ->whereNotNull('echeance')
            ->whereDate('echeance', '<=', now()->toDateString())
            ->with('contact')
            ->when($pole, fn ($q) => $q->whereHas('contact.categorie', fn ($c) => $c->where('pole_id', $pole)))
            ->orderBy('echeance')
            ->limit(30)
            ->get()
            ->map(fn (Activite $a) => [
                'titre' => $a->titre,
                'type' => $a->libelleType(),
                'echeance' => $a->echeance?->toDateString(),
                'en_retard' => $a->enRetard(),
                'contact' => $a->contact?->nomComplet(),
            ])->all();
    }

    /**
     * Opportunités qui stagnent (étape inchangée depuis longtemps).
     *
     * @return list<array<string, mixed>>
     */
    private function contexteOpportunites(?int $pole): array
    {
        return Opportunite::stagnantes()
            ->with('contact')
            ->when($pole, fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $pole)))
            ->orderBy('etape_changee_le')
            ->limit(20)
            ->get()
            ->map(function (Opportunite $o) {
                $ref = $o->etape_changee_le ?? $o->updated_at;

                return [
                    'titre' => $o->titre,
                    'contact' => $o->contact?->nomComplet(),
                    'etape' => $o->libelleEtape(),
                    'depuis_jours' => $ref ? (int) $ref->diffInDays(now()) : null,
                ];
            })->all();
    }

    /**
     * Sessions à venir (datées) ayant encore des places ou une liste d'attente.
     *
     * @return list<array<string, mixed>>
     */
    private function contexteSessions(?int $pole): array
    {
        return \App\Models\Seance::aVenir()
            ->whereNotNull('date_debut')
            ->with(['inscriptions', 'categorie'])
            ->when($pole, fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $pole)))
            ->orderBy('date_debut')
            ->limit(15)
            ->get()
            ->map(fn (\App\Models\Seance $s) => [
                'titre' => $s->titre,
                'date' => $s->date_debut?->toDateString(),
                'places_libres' => $s->placesRestantes(),
                'en_attente' => $s->enAttente()->count(),
            ])
            ->filter(fn ($s) => $s['places_libres'] === null || $s['places_libres'] > 0 || $s['en_attente'] > 0)
            ->values()->all();
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

    /**
     * Résumé « depuis hier » : chiffres clés pour l'ouverture du brief.
     *
     * @return array{mailsRecents:int, reponses:int, relances:int, prospects:int, activites:int}
     */
    public function digest(?CalculRelances $calc = null, int $stagnantes = 0): array
    {
        $calc ??= new CalculRelances(null, $this->poleIdFiltre());

        return [
            'mailsRecents' => Email::where('direction', Email::DIRECTION_ENTRANT)
                ->where('date_email', '>=', now()->subDay()->startOfDay())
                ->count(),
            'reponses' => $calc->reponsesDues()->count(),
            'relances' => $calc->aRelancer()->count() + $stagnantes,
            'prospects' => Activite::whereNull('terminee_at')
                ->where('titre', 'like', 'Créer le prospect : %')
                ->count(),
            'activites' => Activite::aFaire()
                ->whereNotNull('echeance')
                ->whereDate('echeance', '<=', now()->toDateString())
                ->count(),
        ];
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
            'digest' => $this->digest($calc, $stagnantes->count()),
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

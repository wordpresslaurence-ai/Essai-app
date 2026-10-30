<?php

namespace App\Livewire\Contacts;

use App\Models\Activite;
use App\Models\Analyse;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Services\IA\AssistantIA;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('layouts.app')]
class FicheDetail extends Component
{
    public Contact $contact;

    public ?string $analyseErreur = null;

    // Prospection
    public string $angleProspection = '';

    public ?string $prospectionObjet = null;

    public ?string $prospectionCorps = null;

    public ?string $prospectionErreur = null;

    // Réponse à un mail reçu
    public ?string $reponseObjet = null;

    public ?string $reponseCorps = null;

    public ?string $reponseErreur = null;

    /** Catégorie dont l'offre sera présentée en prospection (par défaut celle du contact). */
    public ?string $offreCategorieId = null;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
        $this->offreCategorieId = $contact->categorie_id ? (string) $contact->categorie_id : null;
    }

    /** Lance l'« Analyse & relances » du contact via l'assistant et l'enregistre. */
    public function analyser(): void
    {
        $this->reset('analyseErreur');

        try {
            $r = app(AssistantIA::class)->analyser($this->contact);

            Analyse::create([
                'contact_id' => $this->contact->id,
                'resume' => $r->resume,
                'verdict' => $r->verdict,
                'delai_conseille' => $r->delaiConseille,
                'brouillon' => $r->brouillon,
                'modele' => $r->modele,
                'intention' => $r->intention,
                'temperature_conseillee' => $r->temperatureConseillee,
                'etape_conseillee' => $r->etapeConseillee,
                'infos_cles' => $r->infosCles ?: null,
                'promesses' => $r->promesses ?: null,
                'rdv' => $r->rdv ?: null,
                'faite_le' => now(),
            ]);
        } catch (Throwable $e) {
            $this->analyseErreur = $e->getMessage();
        }
    }

    /** Dernière analyse enregistrée pour ce contact (ou null). */
    private function derniere(): ?Analyse
    {
        return $this->contact->analyses()->latest('faite_le')->first();
    }

    /** Applique au contact la température conseillée par la dernière analyse. */
    public function appliquerTemperature(): void
    {
        $t = $this->derniere()?->temperature_conseillee;

        if ($t && array_key_exists($t, Contact::TEMPERATURES)) {
            $this->contact->update(['temperature' => $t]);
            $this->contact->refresh();
            session()->flash('message', 'Température mise à jour : '.(Contact::TEMPERATURES[$t][0] ?? $t).'.');
        } else {
            session()->flash('erreur', 'Aucune température conseillée exploitable.');
        }
    }

    /** Fait avancer l'opportunité ouverte la plus récente vers l'étape conseillée. */
    public function avancerPipeline(): void
    {
        $etape = $this->derniere()?->etape_conseillee;

        if (! $etape || ! array_key_exists($etape, Opportunite::libellesEtapes())) {
            session()->flash('erreur', 'Aucune étape conseillée exploitable.');

            return;
        }

        $opp = $this->contact->opportunites()
            ->whereNotIn('etape', [Opportunite::ETAPE_GAGNE, Opportunite::ETAPE_PERDU])
            ->latest()
            ->first();

        if (! $opp) {
            session()->flash('erreur', 'Aucune opportunité ouverte pour ce contact (crée-la d’abord dans le Pipeline).');

            return;
        }

        $opp->changerEtape($etape);
        session()->flash('message', 'Opportunité déplacée vers « '.Opportunite::libellesEtapes()[$etape].' ».');
    }

    /** Crée une opportunité pour ce contact à l'étape conseillée par l'analyse. */
    public function creerOpportunite(): void
    {
        $etape = $this->derniere()?->etape_conseillee;

        if (! $etape || ! array_key_exists($etape, Opportunite::libellesEtapes())) {
            $etape = Opportunite::ETAPE_NOUVEAU;
        }

        $opp = Opportunite::create([
            'titre' => 'Opportunité — '.$this->contact->nomComplet(),
            'contact_id' => $this->contact->id,
            'categorie_id' => $this->contact->categorie_id,
            'etape' => $etape,
        ]);

        session()->flash('message', 'Opportunité créée (« '.Opportunite::libellesEtapes()[$opp->etape].' »). Ajuste le titre et le montant dans le Pipeline.');
    }

    /** Crée une tâche de rappel depuis une promesse détectée (sans doublon). */
    public function creerRappel(int $index): void
    {
        $promesses = $this->derniere()?->promesses ?? [];
        $promesse = $promesses[$index] ?? null;

        if (! is_array($promesse) || blank($promesse['texte'] ?? null)) {
            session()->flash('erreur', 'Promesse introuvable.');

            return;
        }

        $titre = 'Rappel : '.\Illuminate\Support\Str::limit((string) $promesse['texte'], 120);

        $existe = Activite::where('contact_id', $this->contact->id)
            ->whereNull('terminee_at')
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            session()->flash('message', 'Ce rappel existe déjà.');

            return;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $this->contact->id,
            'echeance' => filled($promesse['echeance'] ?? null) ? $promesse['echeance'] : null,
            'notes' => (string) $promesse['texte'],
        ]);

        session()->flash('message', 'Rappel créé.');
    }

    /** Crée une activité « rendez-vous » depuis le RDV détecté (sans doublon). */
    public function creerRdv(): void
    {
        $rdv = $this->derniere()?->rdv ?? [];
        $date = $rdv['date'] ?? null;

        if (blank($date)) {
            session()->flash('erreur', 'Aucune date de rendez-vous détectée.');

            return;
        }

        $titre = 'Rendez-vous avec '.$this->contact->nomComplet();

        $existe = Activite::where('contact_id', $this->contact->id)
            ->whereNull('terminee_at')
            ->where('type', Activite::TYPE_RDV)
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            session()->flash('message', 'Un rendez-vous est déjà prévu pour ce contact.');

            return;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_RDV,
            'contact_id' => $this->contact->id,
            'echeance' => $date,
            'notes' => filled($rdv['details'] ?? null) ? (string) $rdv['details'] : null,
        ]);

        session()->flash('message', 'Rendez-vous créé.');
    }

    /** Crée une tâche « proposer un rendez-vous » (Laurence propose le créneau). */
    public function creerTacheProposerRdv(): void
    {
        $rdv = $this->derniere()?->rdv ?? [];

        if (empty($rdv['demande'] ?? false) && blank($rdv['date'] ?? null)) {
            session()->flash('erreur', 'Aucune demande de rendez-vous détectée.');

            return;
        }

        $titre = 'Proposer un rendez-vous à '.$this->contact->nomComplet();

        $existe = Activite::where('contact_id', $this->contact->id)
            ->whereNull('terminee_at')
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            session()->flash('message', 'Une tâche « proposer un RDV » existe déjà.');

            return;
        }

        $notes = array_filter([
            filled($rdv['date'] ?? null) ? 'Date évoquée : '.$rdv['date'] : null,
            filled($rdv['details'] ?? null) ? (string) $rdv['details'] : null,
        ]);

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $this->contact->id,
            'echeance' => now()->toDateString(),
            'notes' => $notes !== [] ? implode("\n", $notes) : null,
        ]);

        session()->flash('message', 'Tâche créée : proposer un rendez-vous.');
    }

    /**
     * Échéance proposée pour la relance, déduite du délai conseillé par
     * l'analyse (« sous 24 h », « demain »…). Null s'il n'y a pas de verdict.
     */
    public function echeanceRelanceProposee(): ?\Illuminate\Support\Carbon
    {
        $a = $this->derniere();

        return ($a && filled($a->verdict)) ? $this->dateDepuisDelai($a->delai_conseille) : null;
    }

    /**
     * Crée une tâche « Relancer … » à partir de l'analyse : le verdict sert de
     * description et le délai conseillé d'échéance. Dédupliquée par titre.
     */
    public function creerTacheRelance(): void
    {
        $a = $this->derniere();

        if (! $a || blank($a->verdict)) {
            session()->flash('erreur', 'Aucune analyse à transformer en tâche.');

            return;
        }

        $titre = 'Relancer '.$this->contact->nomComplet();

        $existe = Activite::where('contact_id', $this->contact->id)
            ->whereNull('terminee_at')
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            session()->flash('message', 'Une tâche de relance existe déjà pour ce contact.');

            return;
        }

        $notes = (string) $a->verdict;
        if (filled($a->delai_conseille)) {
            $notes .= "\n\nDélai conseillé : ".$a->delai_conseille;
        }
        if (filled($a->brouillon)) {
            $notes .= "\n\n--- Brouillon de mail proposé ---\n".$a->brouillon;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'contact_id' => $this->contact->id,
            'echeance' => $this->dateDepuisDelai($a->delai_conseille)->toDateString(),
            'notes' => $notes,
        ]);

        session()->flash('message', 'Tâche de relance créée.');
    }

    /**
     * Traduit un délai conseillé en français en date d'échéance. Un délai présent
     * mais non reconnu, ou absent, donne « aujourd'hui » (une relance s'agit vite).
     */
    private function dateDepuisDelai(?string $delai): \Illuminate\Support\Carbon
    {
        $base = now()->startOfDay();
        $t = \Illuminate\Support\Str::ascii(\Illuminate\Support\Str::lower((string) $delai));

        if ($t === '' || str_contains($t, 'aujourd') || str_contains($t, 'ce jour')
            || str_contains($t, 'immediat') || str_contains($t, 'urgent')) {
            return $base;
        }

        if (str_contains($t, 'demain')) {
            return $base->addDay();
        }

        // « sous 24 h » : <= 24 h = aujourd'hui, sinon arrondi en jours.
        if (preg_match('/(\d+)\s*(h|heure)/', $t, $m) === 1) {
            $h = (int) $m[1];

            return $h <= 24 ? $base : $base->addDays((int) ceil($h / 24));
        }

        if (preg_match('/(\d+)\s*semaine/', $t, $m) === 1) {
            return $base->addWeeks(max(1, (int) $m[1]));
        }

        if (str_contains($t, 'semaine')) {
            return $base->addWeek();
        }

        if (preg_match('/(\d+)\s*(jour|j\b)/', $t, $m) === 1) {
            return $base->addDays(max(1, (int) $m[1]));
        }

        return $base;
    }

    /** Supprime un e-mail de la fiche (uniquement parmi ceux de ce contact). */
    public function supprimerEmail(int $emailId): void
    {
        $this->contact->emails()->whereKey($emailId)->delete();
        $this->contact->refresh();
        session()->flash('message', 'E-mail supprimé de la fiche.');
    }

    /** Vide tout l'historique e-mails de ce contact. */
    public function viderEmails(): void
    {
        $n = $this->contact->emails()->delete();
        $this->contact->refresh();
        session()->flash('message', $n.' e-mail(s) supprimé(s) de la fiche.');
    }

    /**
     * Rédige un brouillon de mail de prospection via l'assistant et garde une
     * trace sous forme de tâche « à envoyer » (dédupliquée par contact).
     */
    public function redigerProspection(): void
    {
        $this->reset('prospectionErreur', 'prospectionObjet', 'prospectionCorps');

        try {
            // Angle saisi à la volée, sinon l'« angle de premier contact » de la fiche.
            $angle = $this->angleProspection ?: $this->contact->angle_contact;
            $categorieId = filled($this->offreCategorieId) ? (int) $this->offreCategorieId : null;
            $r = app(AssistantIA::class)->redigerProspection($this->contact, $angle ?: null, $categorieId);

            $this->prospectionObjet = $r->objet;
            $this->prospectionCorps = $r->corps;

            $titre = 'Envoyer le mail de prospection à '.$this->contact->nomComplet();
            $notes = 'Objet : '.$r->objet."\n\n".$r->corps;

            $tache = Activite::where('contact_id', $this->contact->id)
                ->whereNull('terminee_at')
                ->where('titre', 'like', 'Envoyer le mail de prospection%')
                ->first();

            if ($tache) {
                $tache->update(['titre' => $titre, 'notes' => $notes, 'type' => Activite::TYPE_TACHE]);
            } else {
                Activite::create([
                    'titre' => $titre,
                    'type' => Activite::TYPE_TACHE,
                    'contact_id' => $this->contact->id,
                    'notes' => $notes,
                ]);
            }
        } catch (Throwable $e) {
            $this->prospectionErreur = $e->getMessage();
        }
    }

    /** Propose un brouillon de réponse au dernier e-mail reçu de ce contact. */
    public function redigerReponse(): void
    {
        $this->reset('reponseErreur', 'reponseObjet', 'reponseCorps');

        try {
            $r = app(AssistantIA::class)->redigerReponse($this->contact);
            $this->reponseObjet = $r->objet;
            $this->reponseCorps = $r->corps;
        } catch (Throwable $e) {
            $this->reponseErreur = $e->getMessage();
        }
    }

    public function archiver(): void
    {
        $this->contact->archiver();
        session()->flash('message', 'Contact archivé.');
        $this->contact->refresh();
    }

    public function desarchiver(): void
    {
        $this->contact->desarchiver();
        session()->flash('message', 'Contact désarchivé.');
        $this->contact->refresh();
    }

    public function supprimer()
    {
        // Les personnes rattachées sont détachées automatiquement
        // (contrainte nullOnDelete de la migration), jamais supprimées (EF-04).
        $this->contact->delete();
        session()->flash('message', 'Contact supprimé.');

        return $this->redirect(route('contacts.index'), navigate: true);
    }

    public function render(): View
    {
        $this->contact->load([
            'entreprise',
            'personnes',
            'etiquettes',
            'categorie.pole',
            'opportunites' => fn ($q) => $q->orderByDesc('created_at'),
            'activites' => fn ($q) => $q->with('opportunite')->orderByRaw('echeance is null')->orderBy('echeance'),
            'emails' => fn ($q) => $q->orderByDesc('date_email')->orderByDesc('id'),
            'inscriptions' => fn ($q) => $q->with('seance')->where('statut', '!=', 'annule')->latest(),
        ]);

        // Offres disponibles pour la prospection : catégories ayant une offre ou des liens.
        $offres = \App\Models\Categorie::with('pole')
            ->where(fn ($q) => $q->whereNotNull('offre')->orWhereNotNull('liens'))
            ->get()
            ->filter(fn ($c) => filled($c->offre) || ! empty($c->liens))
            ->sortBy(fn ($c) => $c->libelleComplet())
            ->values();

        return view('livewire.contacts.fiche-detail', [
            'derniereAnalyse' => $this->contact->analyses()->latest('faite_le')->first(),
            'offresProspection' => $offres,
        ]);
    }
}

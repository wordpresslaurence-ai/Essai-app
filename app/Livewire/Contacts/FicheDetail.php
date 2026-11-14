<?php

namespace App\Livewire\Contacts;

use App\Models\Activite;
use App\Models\Analyse;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Services\IA\AssistantIA;
use App\Services\Whatsapp\LecteurDiscussion;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.app')]
class FicheDetail extends Component
{
    use WithFileUploads;

    public Contact $contact;

    public ?string $analyseErreur = null;

    // Échange WhatsApp collé à la main
    public bool $whatsappOuvert = false;

    public string $whatsappTexte = '';

    /** Discussion exportée depuis WhatsApp (.txt, ou .zip qui la contient). */
    public ?TemporaryUploadedFile $whatsappFichier = null;

    // Telegram : réponse en cours de rédaction
    public string $telegramReponse = '';

    public ?string $telegramErreur = null;

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

    /** Affiche tous les e-mails (sinon seulement les 6 plus récents). */
    public bool $tousMails = false;

    /** E-mail ouvert dans la modale « mail complet » (ou null). */
    public ?int $mailOuvertId = null;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
        $this->offreCategorieId = $contact->categorie_id ? (string) $contact->categorie_id : null;

        // Arrivée depuis le radar « sites web » : angle et offre déjà choisis.
        $angle = request()->query('angle');
        if (is_string($angle) && trim($angle) !== '') {
            $this->angleProspection = mb_substr(trim($angle), 0, 1500);
        }
        $offre = request()->query('offre');
        if (is_string($offre) && ctype_digit($offre) && \App\Models\Categorie::whereKey((int) $offre)->exists()) {
            $this->offreCategorieId = $offre;
        }
    }

    /** Déplie la liste complète des e-mails de la fiche. */
    public function voirTousMails(): void
    {
        $this->tousMails = true;
    }

    /** Ouvre un e-mail dans la modale (vérifie qu'il appartient au contact). */
    public function ouvrirMail(int $emailId): void
    {
        $this->mailOuvertId = $this->contact->emails()->whereKey($emailId)->exists() ? $emailId : null;
    }

    public function fermerMail(): void
    {
        $this->mailOuvertId = null;
    }

    /** Met en pause ou reprend le suivi des relances (« conversation terminée »). */
    public function basculerSuivi(): void
    {
        if ($this->contact->suiviEnPause()) {
            $this->contact->reprendreSuivi();
            session()->flash('message', 'Suivi des relances repris.');
        } else {
            $this->contact->mettreSuiviEnPause();
            session()->flash('message', 'Conversation terminée : plus de relance tant qu’aucun nouvel e-mail n’est échangé.');
        }
    }

    /**
     * Fichier déposé : son texte remplit la zone de saisie, que l'on peut relire
     * avant d'enregistrer. Un .zip (export iPhone) est ouvert pour en tirer le .txt.
     */
    public function updatedWhatsappFichier(): void
    {
        $this->validate([
            'whatsappFichier' => ['file', 'mimes:txt,zip', 'max:5120'],
        ], [
            'whatsappFichier.mimes' => 'Dépose le fichier .txt (ou .zip) exporté depuis WhatsApp.',
            'whatsappFichier.max' => 'Ce fichier dépasse 5 Mo : exporte la discussion « sans médias ».',
        ]);

        $chemin = $this->whatsappFichier->getRealPath();
        $contenu = null;

        if (strtolower($this->whatsappFichier->getClientOriginalExtension()) === 'zip') {
            if (class_exists(\ZipArchive::class)) {
                $zip = new \ZipArchive;
                if ($zip->open($chemin) === true) {
                    for ($i = 0; $i < $zip->numFiles; $i++) {
                        if (str_ends_with(strtolower((string) $zip->getNameIndex($i)), '.txt')) {
                            $contenu = (string) $zip->getFromIndex($i);
                            break;
                        }
                    }
                    $zip->close();
                }
            }
        } else {
            $contenu = (string) file_get_contents($chemin);
        }

        $this->reset('whatsappFichier');

        if ($contenu === null || trim($contenu) === '') {
            $this->addError('whatsappFichier', 'Aucune discussion trouvée dans ce fichier. Ouvre-le et colle son texte ci-dessous.');

            return;
        }

        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }

        $this->whatsappTexte = trim($contenu);
    }

    /**
     * Garde dans l'historique un échange WhatsApp collé à la main ou exporté (le
     * CRM ne lit pas WhatsApp). L'assistant en tient compte dans ses analyses.
     *
     * Une discussion exportée est reconnue : ses messages sont rangés proprement
     * et seuls ceux qui ne sont pas déjà dans l'historique sont ajoutés. Tout
     * autre texte (résumé, copie partielle) est gardé tel quel.
     */
    public function noterWhatsapp(): void
    {
        $donnees = $this->validate([
            'whatsappTexte' => ['required', 'string', 'max:2000000'],
        ], [
            'whatsappTexte.required' => 'Colle la conversation, dépose le fichier exporté, ou résume l’échange.',
            'whatsappTexte.max' => 'Cette discussion est trop longue : exporte-la « sans médias » ou colle seulement la partie récente.',
        ]);

        $lecteur = new LecteurDiscussion;
        $messages = $lecteur->lire($donnees['whatsappTexte']);

        // Texte libre : comportement d'origine.
        if (count($messages) < 2) {
            $this->creerNoteWhatsapp(
                Activite::TITRE_WHATSAPP.' du '.now()->format('d/m/Y'),
                mb_substr(trim($donnees['whatsappTexte']), 0, 20000),
            );
            $this->finNoteWhatsapp('Échange WhatsApp noté dans l’historique.');

            return;
        }

        // Discussion reconnue : on écarte ce qui est déjà noté sur cette fiche.
        $connues = [];
        $anciennes = Activite::where('contact_id', $this->contact->id)
            ->where('titre', 'like', Activite::TITRE_WHATSAPP.'%')
            ->whereNotNull('notes')
            ->pluck('notes');
        foreach ($anciennes as $note) {
            foreach ($lecteur->lire((string) $note) as $ancien) {
                $connues[LecteurDiscussion::cle($ancien)] = true;
            }
        }

        $nouveaux = [];
        foreach ($messages as $message) {
            $cle = LecteurDiscussion::cle($message);
            if (! isset($connues[$cle])) {
                $connues[$cle] = true;
                $nouveaux[] = $message;
            }
        }

        if ($nouveaux === []) {
            $this->finNoteWhatsapp('Rien de nouveau : ces '.count($messages).' messages sont déjà dans l’historique.');

            return;
        }

        foreach (LecteurDiscussion::blocs($nouveaux) as $bloc) {
            $debut = $bloc[0]['date'];
            $fin = $bloc[count($bloc) - 1]['date'];

            $this->creerNoteWhatsapp(
                Activite::TITRE_WHATSAPP.' du '.$debut.($fin !== $debut ? ' au '.$fin : ''),
                implode("\n", array_map([LecteurDiscussion::class, 'formater'], $bloc)),
            );
        }

        $deja = count($messages) - count($nouveaux);
        $this->finNoteWhatsapp(count($nouveaux).' message(s) WhatsApp ajouté(s) à l’historique'.($deja > 0 ? ' ('.$deja.' déjà présent(s), ignoré(s))' : '').'.');
    }

    private function creerNoteWhatsapp(string $titre, string $notes): void
    {
        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_NOTE,
            'contact_id' => $this->contact->id,
            'terminee_at' => now(),
            'notes' => $notes,
        ]);
    }

    private function finNoteWhatsapp(string $message): void
    {
        $this->reset('whatsappTexte', 'whatsappOuvert', 'whatsappFichier');
        $this->contact->refresh();
        session()->flash('message', $message);
    }

    // --- Telegram (brique 018) -----------------------------------------------

    /** Conversation Telegram en texte, pour l'assistant (les 20 derniers messages). */
    private function echangeTelegram(): string
    {
        return $this->contact->messagesTelegram()->latest('id')->limit(20)->get()->reverse()
            ->map(fn (\App\Models\MessageTelegram $m) => '['.$m->created_at->format('d/m/Y H:i').'] '
                .($m->estEntrant() ? $this->contact->nomComplet() : 'Laurence').' : '.$m->apercu())
            ->implode("\n");
    }

    /** L'assistant propose une réponse ; rien n'est envoyé. */
    public function proposerReponseTelegram(): void
    {
        $this->reset('telegramErreur');

        try {
            $this->telegramReponse = app(AssistantIA::class)->redigerReponseTelegram($this->contact, $this->echangeTelegram());
        } catch (Throwable $e) {
            $this->telegramErreur = $e->getMessage();
        }
    }

    /** Envoie la réponse par le robot, au clic. Les messages reçus sont alors considérés comme traités. */
    public function envoyerTelegram(): void
    {
        $this->reset('telegramErreur');

        if (! $this->contact->telegramRelie()) {
            return;
        }

        $this->validate(
            ['telegramReponse' => ['required', 'string', 'max:4000']],
            ['telegramReponse.required' => 'Écris ta réponse avant de l’envoyer.'],
        );

        try {
            $id = app(\App\Services\Telegram\ClientTelegram::class)->envoyer($this->contact->telegram_id, trim($this->telegramReponse));
        } catch (Throwable $e) {
            $this->telegramErreur = $e->getMessage();

            return;
        }

        \App\Models\MessageTelegram::create([
            'contact_id' => $this->contact->id,
            'chat_id' => $this->contact->telegram_id,
            'sens' => \App\Models\MessageTelegram::SORTANT,
            'texte' => trim($this->telegramReponse),
            'telegram_message_id' => $id,
        ]);
        $this->marquerTelegramTraite();

        $this->reset('telegramReponse');
        session()->flash('message', 'Réponse envoyée sur Telegram.');
    }

    /** Les messages reçus n'appellent pas (ou plus) de réponse. */
    public function marquerTelegramTraite(): void
    {
        $this->contact->messagesTelegram()
            ->where('sens', \App\Models\MessageTelegram::ENTRANT)
            ->whereNull('traite_le')
            ->update(['traite_le' => now()]);
    }

    /** Détache le compte Telegram de cette fiche (les messages sont conservés). */
    public function delierTelegram(): void
    {
        $this->contact->forceFill(['telegram_id' => null, 'telegram_nom' => null, 'telegram_jeton_lien' => null])->save();
    }

    /** Met le contact « à la une » (ou l'en retire) — affiché sur le tableau de bord. */
    public function basculerALaUne(): void
    {
        $this->contact->forceFill(['a_la_une' => ! $this->contact->a_la_une])->save();
        session()->flash('message', $this->contact->a_la_une ? 'Contact mis à la une.' : 'Contact retiré de la une.');
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
        if ($this->mailOuvertId === $emailId) {
            $this->mailOuvertId = null;
        }
        session()->flash('message', 'E-mail supprimé de la fiche.');
    }

    /** Vide tout l'historique e-mails de ce contact. */
    public function viderEmails(): void
    {
        $n = $this->contact->emails()->delete();
        $this->contact->refresh();
        $this->mailOuvertId = null;
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
            'lienTelegram' => $this->contact->lienTelegram(),
            'messagesTelegram' => $this->contact->messagesTelegram()->latest('id')->limit(30)->get()->reverse()->values(),
            'telegramEnAttente' => $this->contact->messagesTelegram()
                ->where('sens', \App\Models\MessageTelegram::ENTRANT)->whereNull('traite_le')->count(),
        ]);
    }
}

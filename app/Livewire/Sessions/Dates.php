<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\PresenceDate;
use App\Models\Seance;
use App\Models\SeanceDate;
use App\Services\Sessions\RappelsCycle;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Dates d'une session (brique 017) : pour chaque date, le lien de visio, le lien
 * de l'enregistrement, les présences, le rappel de la veille et l'envoi de
 * l'enregistrement aux absents. Le CRM n'envoie rien lui-même.
 */
#[Layout('layouts.app')]
class Dates extends Component
{
    public Seance $seance;

    /** Date dont le panneau de message est ouvert. */
    public ?int $dateId = null;

    /** rappel (à tous les inscrits) | enregistrement (aux absents de la date). */
    public string $mode = 'rappel';

    public string $message = '';

    /** @var array<int, int> Inscriptions pour lesquelles le rappel a été ouvert. */
    public array $ouverts = [];

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;

        // Arrivée depuis la tâche « Rappel aux inscrits ».
        $date = request()->integer('date');
        if ($date && in_array(request()->query('mode'), ['rappel', 'enregistrement'], true)) {
            $this->ouvrir($date, request()->query('mode'));
        }
    }

    protected function date(int $id): ?SeanceDate
    {
        return $this->seance->dates()->find($id);
    }

    /** @return Collection<int, Inscription> */
    protected function inscrits(): Collection
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['contact.entreprise', 'presencesDate'])
            ->get()
            ->sortBy(fn (Inscription $i) => $i->contact->cleTri())
            ->values();
    }

    /** Enregistre le lien de visio ou de l'enregistrement d'une date. */
    public function majLien(int $dateId, string $champ, string $valeur): void
    {
        $date = $this->date($dateId);
        if (! $date || ! in_array($champ, ['lien_visio', 'lien_enregistrement'], true)) {
            return;
        }

        $valeur = trim($valeur);
        if ($valeur !== '' && ! filter_var($valeur, FILTER_VALIDATE_URL)) {
            session()->flash('erreur', 'Ce lien n’est pas une adresse complète : il doit commencer par https://');

            return;
        }

        $date->update([$champ => $valeur !== '' ? mb_substr($valeur, 0, 500) : null]);
    }

    /** Note la présence à une date ; cliquer à nouveau sur le même choix l'efface. */
    public function noterPresence(int $dateId, int $inscriptionId, string $presence): void
    {
        $date = $this->date($dateId);
        $inscription = $this->inscrits()->firstWhere('id', $inscriptionId);
        if (! $date || ! $inscription || ! in_array($presence, [Inscription::PRESENT, Inscription::ABSENT], true)) {
            return;
        }

        $ligne = PresenceDate::firstOrNew(['seance_date_id' => $date->id, 'inscription_id' => $inscription->id]);
        $ligne->presence = $ligne->presence === $presence ? null : $presence;
        $ligne->save();

        $inscription->recalculerPresence();
    }

    public function toutPresent(int $dateId): void
    {
        $date = $this->date($dateId);
        if (! $date) {
            return;
        }

        foreach ($this->inscrits() as $inscription) {
            $ligne = PresenceDate::firstOrNew(['seance_date_id' => $date->id, 'inscription_id' => $inscription->id]);
            if ($ligne->presence === null) {
                $ligne->presence = Inscription::PRESENT;
                $ligne->save();
                $inscription->recalculerPresence();
            }
        }
    }

    /** Ouvre le panneau de message d'une date, avec le texte du modèle. */
    public function ouvrir(int $dateId, string $mode): void
    {
        if (! $this->date($dateId) || ! in_array($mode, ['rappel', 'enregistrement'], true)) {
            return;
        }

        $modele = $mode === 'rappel'
            ? ModeleMessage::parDebut('Rappel', ModeleMessage::NOM_RAPPEL_VEILLE)
            : ModeleMessage::parDebut('Enregistrement', ModeleMessage::NOM_ENREGISTREMENT);

        $this->dateId = $dateId;
        $this->mode = $mode;
        $this->message = (string) $modele->corps;
        $this->ouverts = [];
    }

    public function fermer(): void
    {
        $this->reset(['dateId', 'message', 'ouverts']);
    }

    /** Texte du message pour une personne, avec les valeurs de la date concernée. */
    protected function texte(Inscription $inscription, SeanceDate $date): string
    {
        return ModeleMessage::remplacer($this->message, array_merge(
            ModeleMessage::valeurs($inscription->contact, $this->seance, auth()->user()?->name),
            [
                'date' => $date->date_debut->translatedFormat('l j F Y'),
                'heure' => ModeleMessage::heureLisible($date->date_debut),
                'lien_visio' => (string) $date->lien_visio,
                'lien_enregistrement' => (string) $date->lien_enregistrement,
            ],
        ));
    }

    /**
     * Appelé au clic sur « Ouvrir WhatsApp » ou « Ouvrir ma messagerie ». Pour un
     * enregistrement, l'envoi est daté et gardé dans l'historique du contact.
     */
    public function marquer(int $inscriptionId): void
    {
        $date = $this->dateId ? $this->date($this->dateId) : null;
        $inscription = $this->inscrits()->firstWhere('id', $inscriptionId);
        if (! $date || ! $inscription || trim($this->message) === '') {
            return;
        }

        if ($this->mode === 'rappel') {
            $this->ouverts = array_values(array_unique([...$this->ouverts, $inscription->id]));

            // Le rappel est en cours d'envoi : la tâche de la veille se clôture.
            Activite::whereNull('terminee_at')->where('titre', RappelsCycle::titreTache($date->load('seance')))
                ->update(['terminee_at' => now()]);

            return;
        }

        $ligne = PresenceDate::firstOrNew(['seance_date_id' => $date->id, 'inscription_id' => $inscription->id]);
        if ($ligne->enregistrement_envoye_le) {
            return;
        }
        $ligne->enregistrement_envoye_le = now();
        $ligne->save();

        Activite::create([
            'titre' => 'Enregistrement envoyé : '.$this->seance->titre,
            'type' => Activite::TYPE_NOTE,
            'contact_id' => $inscription->contact_id,
            'terminee_at' => now(),
            'notes' => $this->texte($inscription, $date),
        ]);
    }

    public function render(): View
    {
        $this->seance->load('lieuContact');
        $dates = $this->seance->dates()->get();
        $inscrits = $this->inscrits();

        // Présence par date et par inscription.
        $presences = [];
        foreach ($inscrits as $inscription) {
            foreach ($inscription->presencesDate as $p) {
                $presences[$p->seance_date_id][$inscription->id] = $p;
            }
        }

        // Destinataires du panneau ouvert.
        $lignes = collect();
        $dateOuverte = $this->dateId ? $dates->firstWhere('id', $this->dateId) : null;
        if ($dateOuverte) {
            $lignes = $inscrits
                ->filter(fn (Inscription $i) => $this->mode === 'rappel'
                    || ($presences[$dateOuverte->id][$i->id]->presence ?? null) === Inscription::ABSENT)
                ->map(function (Inscription $i) use ($dateOuverte, $presences) {
                    $texte = $this->texte($i, $dateOuverte);
                    $numero = $i->contact->numeroWhatsapp();

                    return [
                        'inscription' => $i,
                        'texte' => $texte,
                        'whatsapp' => $numero ? 'https://wa.me/'.$numero.'?text='.rawurlencode($texte) : null,
                        'mail' => filled($i->contact->email)
                            ? 'mailto:'.$i->contact->email.'?subject='.rawurlencode($this->seance->titre).'&body='.rawurlencode($texte)
                            : null,
                        'fait' => $this->mode === 'rappel'
                            ? in_array($i->id, $this->ouverts, true)
                            : (bool) ($presences[$dateOuverte->id][$i->id]->enregistrement_envoye_le ?? null),
                    ];
                })->values();
        }

        return view('livewire.sessions.dates', [
            'dates' => $dates,
            'inscrits' => $inscrits,
            'presences' => $presences,
            'dateOuverte' => $dateOuverte,
            'lignes' => $lignes,
        ]);
    }
}

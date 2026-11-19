<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\MessageTelegram;
use App\Models\ModeleMessage;
use App\Models\Seance;
use App\Services\Telegram\AccesTelegram;
use App\Services\Telegram\ClientTelegram;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Accès au canal Telegram d'un trimestre (brique 019) : lien personnel par
 * abonné payé, message de bienvenue, renouvellement avant la fin, retrait.
 */
#[Layout('layouts.app')]
class Acces extends Component
{
    public Seance $seance;

    public ?string $erreur = null;

    /** Inscription dont le message de bienvenue est ouvert. */
    public ?int $ouverteId = null;

    public string $corps = '';

    /** Panneau « renouvellement » ouvert. */
    public bool $renouvellement = false;

    public string $messageRenouvellement = '';

    /** @var array<int, int> Inscriptions pour lesquelles le message de renouvellement a été ouvert. */
    public array $ouverts = [];

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;

        if (request()->query('mode') === 'renouvellement') {
            $this->ouvrirRenouvellement();
        }
    }

    /** @return Collection<int, Inscription> */
    protected function inscriptions(): Collection
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.categorie', 'seance.canalTelegram', 'seance.lieuContact', 'contact.entreprise', 'paiements'])
            ->get()
            ->sortBy(fn (Inscription $i) => $i->contact->cleTri())
            ->values();
    }

    protected function inscription(int $id): ?Inscription
    {
        return $this->inscriptions()->firstWhere('id', $id);
    }

    public function creerLien(int $id): void
    {
        $this->reset('erreur');
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        try {
            app(AccesTelegram::class)->creerLien($inscription);
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();
        }
    }

    /** Crée d'un coup les liens de tous les abonnés payés qui n'en ont pas encore. */
    public function creerLiensPayes(): void
    {
        $this->reset('erreur');
        $faits = 0;

        foreach ($this->inscriptions() as $inscription) {
            if (filled($inscription->acces_lien) && ! $inscription->acces_retire_le) {
                continue;
            }
            if ($inscription->etatPaiement() === 'impaye') {
                continue;
            }

            try {
                app(AccesTelegram::class)->creerLien($inscription);
                $faits++;
            } catch (Throwable $e) {
                $this->erreur = $e->getMessage();
                break;
            }
        }

        if ($faits > 0) {
            session()->flash('message', $faits.' lien(s) d’accès créé(s).');
        }
    }

    public function preparerBienvenue(int $id): void
    {
        $this->reset('erreur');
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        try {
            $lien = app(AccesTelegram::class)->creerLien($inscription);
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        $texte = ModeleMessage::parDebut('Accès', ModeleMessage::NOM_ACCES)
            ->rendre($inscription->contact, $this->seance, auth()->user()?->name, null, ['lien_acces' => $lien]);

        $this->fermerRenouvellement();
        $this->ouverteId = $inscription->id;
        $this->corps = $texte['corps'];
    }

    public function fermer(): void
    {
        $this->reset(['ouverteId', 'corps']);
    }

    /** Envoi par le robot, quand la personne a déjà relié son compte Telegram. */
    public function envoyerParRobot(): void
    {
        $this->reset('erreur');
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription || ! $inscription->contact->telegramRelie() || trim($this->corps) === '') {
            return;
        }

        try {
            $id = app(ClientTelegram::class)->envoyer($inscription->contact->telegram_id, trim($this->corps));
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();

            return;
        }

        MessageTelegram::create([
            'contact_id' => $inscription->contact_id,
            'chat_id' => $inscription->contact->telegram_id,
            'sens' => MessageTelegram::SORTANT,
            'texte' => trim($this->corps),
            'telegram_message_id' => $id,
        ]);

        $this->marquerEnvoye();
    }

    /** Le message de bienvenue est parti : date et trace sur la fiche. */
    public function marquerEnvoye(): void
    {
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription) {
            return;
        }

        $inscription->update(['acces_envoye_le' => now()]);

        Activite::create([
            'titre' => 'Accès Telegram envoyé : '.$this->seance->titre,
            'type' => Activite::TYPE_NOTE,
            'contact_id' => $inscription->contact_id,
            'terminee_at' => now(),
            'notes' => trim($this->corps),
        ]);

        session()->flash('message', 'Accès noté comme envoyé à '.$inscription->contact->nomComplet().'.');
        $this->fermer();
    }

    /** Retire l'accès tout de suite (départ anticipé, erreur…). */
    public function retirerAcces(int $id): void
    {
        $this->reset('erreur');
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        try {
            app(AccesTelegram::class)->retirer($inscription);
            session()->flash('message', 'Accès retiré pour '.$inscription->contact->nomComplet().'.');
        } catch (Throwable $e) {
            $this->erreur = $e->getMessage();
        }
    }

    // --- Renouvellement ------------------------------------------------------

    public function ouvrirRenouvellement(): void
    {
        $this->fermer();
        $this->renouvellement = true;
        $this->messageRenouvellement = (string) ModeleMessage::parDebut('Renouvellement', ModeleMessage::NOM_RENOUVELLEMENT)->corps;
        $this->ouverts = [];
    }

    public function fermerRenouvellement(): void
    {
        $this->reset(['renouvellement', 'messageRenouvellement', 'ouverts']);
    }

    public function marquerRenouvellement(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription || trim($this->messageRenouvellement) === '') {
            return;
        }

        if (! in_array($inscription->id, $this->ouverts, true)) {
            $this->ouverts[] = $inscription->id;

            Activite::create([
                'titre' => 'Proposition de renouvellement : '.$this->seance->titre,
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $inscription->contact_id,
                'terminee_at' => now(),
                'notes' => $this->texteRenouvellement($inscription),
            ]);
        }

        if ($this->seance->fin_acces) {
            Activite::whereNull('terminee_at')->where('titre', AccesTelegram::titreTache($this->seance))
                ->update(['terminee_at' => now()]);
        }
    }

    protected function texteRenouvellement(Inscription $inscription): string
    {
        return ModeleMessage::remplacer($this->messageRenouvellement, ModeleMessage::valeurs(
            $inscription->contact,
            $this->seance,
            auth()->user()?->name,
            AccesTelegram::prochaineSession($this->seance),
        ));
    }

    public function render(): View
    {
        $this->seance->load(['canalTelegram', 'lieuContact']);
        $inscriptions = $this->inscriptions();

        $lignesRenouvellement = collect();
        if ($this->renouvellement) {
            $lignesRenouvellement = AccesTelegram::aRenouveler($this->seance, $inscriptions)
                ->map(function (Inscription $i) {
                    $texte = $this->texteRenouvellement($i);
                    $numero = $i->contact->numeroWhatsapp();

                    return [
                        'inscription' => $i,
                        'texte' => $texte,
                        'whatsapp' => $numero ? 'https://wa.me/'.$numero.'?text='.rawurlencode($texte) : null,
                        'mail' => filled($i->contact->email)
                            ? 'mailto:'.$i->contact->email.'?subject='.rawurlencode($this->seance->titre).'&body='.rawurlencode($texte)
                            : null,
                        'fait' => in_array($i->id, $this->ouverts, true),
                    ];
                });
        }

        return view('livewire.sessions.acces', [
            'inscriptions' => $inscriptions,
            'bilans' => $inscriptions->mapWithKeys(fn (Inscription $i) => [$i->id => \App\Services\Telegram\Assiduite::bilan($i)]),
            'canal' => $this->seance->canalTelegram,
            'prochaine' => AccesTelegram::prochaineSession($this->seance),
            'lignesRenouvellement' => $lignesRenouvellement,
            'compteurs' => [
                'payes' => $inscriptions->filter(fn ($i) => $i->etatPaiement() !== 'impaye')->count(),
                'liens' => $inscriptions->filter(fn ($i) => filled($i->acces_lien) && ! $i->acces_retire_le)->count(),
                'rejoints' => $inscriptions->filter(fn ($i) => $i->etatAcces() === 'rejoint')->count(),
                'retires' => $inscriptions->filter(fn ($i) => $i->etatAcces() === 'retire')->count(),
            ],
        ]);
    }
}

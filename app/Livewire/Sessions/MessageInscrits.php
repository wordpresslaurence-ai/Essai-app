<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Message WhatsApp aux inscrits d'une session, envoyé à la chaîne : un texte
 * commun (avec variables), personnalisé pour chacun, ouvert dans WhatsApp
 * personne après personne. Le CRM n'envoie rien lui-même.
 */
#[Layout('layouts.app')]
class MessageInscrits extends Component
{
    public Seance $seance;

    public string $message = '';

    public ?string $modeleId = null;

    /** Inclure aussi la liste d'attente. */
    public bool $avecAttente = false;

    /** @var array<int, int> Contacts pour lesquels le message a été ouvert/envoyé. */
    public array $envoyes = [];

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
    }

    /** Charger un modèle remplace le texte en cours. */
    public function updatedModeleId(): void
    {
        $modele = $this->modeleId ? ModeleMessage::find($this->modeleId) : null;
        if ($modele) {
            $this->message = $modele->corps;
            $this->envoyes = [];
        }
    }

    /** Modifier le texte remet les envois à zéro : ce n'est plus le même message. */
    public function updatedMessage(): void
    {
        $this->envoyes = [];
    }

    /** @return Collection<int, Inscription> */
    protected function inscrits(): Collection
    {
        $statuts = $this->avecAttente
            ? [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE]
            : [Inscription::STATUT_CONFIRME];

        return $this->seance->inscriptions()
            ->whereIn('statut', $statuts)
            ->with('contact.entreprise')
            ->get()
            ->sortBy(fn (Inscription $i) => mb_strtolower($i->contact->nomComplet()))
            ->values();
    }

    /** Texte personnalisé pour un contact. */
    protected function texte(Inscription $inscription): string
    {
        return ModeleMessage::remplacer(
            $this->message,
            ModeleMessage::valeurs($inscription->contact, $this->seance, auth()->user()?->name),
        );
    }

    /**
     * Appelé au clic sur « Ouvrir WhatsApp » : note l'envoi et garde le texte
     * dans l'historique du contact (une seule fois par message).
     */
    public function marquerEnvoye(int $contactId): void
    {
        $inscription = $this->inscrits()->firstWhere('contact_id', $contactId);
        if (! $inscription || trim($this->message) === '' || in_array($contactId, array_map('intval', $this->envoyes), true)) {
            return;
        }

        Activite::create([
            'titre' => 'Message WhatsApp : '.$this->seance->titre,
            'type' => Activite::TYPE_NOTE,
            'contact_id' => $contactId,
            'terminee_at' => now(),
            'notes' => $this->texte($inscription),
        ]);

        $this->envoyes[] = $contactId;
    }

    public function recommencer(): void
    {
        $this->envoyes = [];
    }

    public function render(): View
    {
        $this->seance->load('lieuContact');

        $lignes = $this->inscrits()->map(function (Inscription $i) {
            $numero = $i->contact->numeroWhatsapp();
            $texte = $this->texte($i);

            return [
                'contact' => $i->contact,
                'attente' => $i->estEnAttente(),
                'numero' => $numero,
                'texte' => $texte,
                'lien' => $numero && trim($this->message) !== ''
                    ? 'https://wa.me/'.$numero.'?text='.rawurlencode($texte)
                    : null,
                'envoye' => in_array((int) $i->contact_id, array_map('intval', $this->envoyes), true),
            ];
        });

        return view('livewire.sessions.message-inscrits', [
            'lignes' => $lignes,
            'suivante' => $lignes->first(fn ($l) => $l['lien'] && ! $l['envoye']),
            'nbJoignables' => $lignes->filter(fn ($l) => $l['numero'])->count(),
            'nbEnvoyes' => $lignes->filter(fn ($l) => $l['envoye'])->count(),
            'modeles' => ModeleMessage::where('canal', ModeleMessage::CANAL_MESSAGE)->orderBy('nom')->get(),
        ]);
    }
}

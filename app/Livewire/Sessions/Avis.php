<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\Parametre;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Demande d'avis après un stage (brique 015) : un message prêt pour chaque
 * participant présent, puis l'avis reçu est gardé avec l'accord de publication.
 * Le CRM n'envoie rien lui-même.
 */
#[Layout('layouts.app')]
class Avis extends Component
{
    public Seance $seance;

    public ?int $ouverteId = null;

    /** demande (message à envoyer) | avis (noter l'avis reçu). */
    public string $panneau = 'demande';

    public string $brouillonObjet = '';

    public string $brouillonCorps = '';

    public string $avisTexte = '';

    public bool $avisPubliable = false;

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
    }

    protected function inscription(int $id): ?Inscription
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.lieuContact', 'contact.entreprise'])
            ->find($id);
    }

    /**
     * Les participants à qui demander un avis : les confirmés, sauf ceux notés absents.
     *
     * @return Collection<int, Inscription>
     */
    protected function inscriptions(): Collection
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->where(fn ($q) => $q->whereNull('presence')->orWhere('presence', '!=', Inscription::ABSENT))
            ->with(['seance.lieuContact', 'contact.entreprise'])
            ->get()
            ->sortBy(fn (Inscription $i) => $i->contact->cleTri())
            ->values();
    }

    public function preparer(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $texte = ModeleMessage::parDebut('Avis', ModeleMessage::NOM_AVIS)->rendre(
            $inscription->contact, $inscription->seance, auth()->user()?->name, null,
            ['lien_avis' => (string) Parametre::instance()->lien_avis],
        );

        $this->ouverteId = $inscription->id;
        $this->panneau = 'demande';
        $this->brouillonObjet = $texte['objet'];
        $this->brouillonCorps = $texte['corps'];
    }

    public function fermer(): void
    {
        $this->reset(['ouverteId', 'panneau', 'brouillonObjet', 'brouillonCorps', 'avisTexte', 'avisPubliable']);
        $this->resetErrorBag();
    }

    /** La demande est partie : date, trace sur la fiche, personne suivante. */
    public function marquerEnvoye(): void
    {
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription) {
            return;
        }

        $inscription->update(['avis_demande_le' => now()]);

        Activite::create([
            'titre' => 'Demande d’avis envoyée : '.$this->seance->titre,
            'type' => Activite::TYPE_EMAIL,
            'contact_id' => $inscription->contact_id,
            'terminee_at' => now(),
            'notes' => trim((filled($this->brouillonObjet) ? 'Objet : '.$this->brouillonObjet."\n\n" : '').$this->brouillonCorps),
        ]);

        session()->flash('message', 'Demande notée pour '.$inscription->contact->nomComplet().'.');

        $suivante = $this->inscriptions()
            ->first(fn (Inscription $i) => $i->avis_demande_le === null && $i->avis_recu_le === null);
        $suivante ? $this->preparer($suivante->id) : $this->fermer();
    }

    /** Ouvre le panneau pour coller l'avis reçu. */
    public function noter(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $this->ouverteId = $inscription->id;
        $this->panneau = 'avis';
        $this->avisTexte = (string) $inscription->avis_texte;
        $this->avisPubliable = (bool) $inscription->avis_publiable;
        $this->resetErrorBag();
    }

    public function enregistrerAvis(): void
    {
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription) {
            return;
        }

        $this->validate(
            ['avisTexte' => ['required', 'string', 'max:5000']],
            ['avisTexte.required' => 'Colle ou écris l’avis reçu.'],
        );

        $premier = $inscription->avis_recu_le === null;

        $inscription->update([
            'avis_texte' => trim($this->avisTexte),
            'avis_publiable' => $this->avisPubliable,
            'avis_recu_le' => $inscription->avis_recu_le ?? now(),
        ]);

        if ($premier) {
            Activite::create([
                'titre' => 'Avis reçu : '.$this->seance->titre,
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $inscription->contact_id,
                'terminee_at' => now(),
                'notes' => trim($this->avisTexte)."\n\n".($this->avisPubliable ? 'Accord de publication : oui.' : 'Accord de publication : pas encore.'),
            ]);
        }

        session()->flash('message', 'Avis de '.$inscription->contact->nomComplet().' enregistré.');
        $this->fermer();
    }

    public function supprimerAvis(int $id): void
    {
        $this->inscription($id)?->update(['avis_texte' => null, 'avis_publiable' => false, 'avis_recu_le' => null]);
    }

    public function render(): View
    {
        $inscriptions = $this->inscriptions();

        return view('livewire.sessions.avis', [
            'inscriptions' => $inscriptions,
            'lienAvis' => Parametre::instance()->lien_avis,
            'compteurs' => [
                'a_demander' => $inscriptions->filter(fn (Inscription $i) => ! $i->avis_demande_le && ! $i->avis_recu_le)->count(),
                'demandes' => $inscriptions->filter(fn (Inscription $i) => $i->avis_demande_le && ! $i->avis_recu_le)->count(),
                'recus' => $inscriptions->whereNotNull('avis_recu_le')->count(),
                'publiables' => $inscriptions->filter(fn (Inscription $i) => $i->avis_recu_le && $i->avis_publiable)->count(),
            ],
        ]);
    }
}

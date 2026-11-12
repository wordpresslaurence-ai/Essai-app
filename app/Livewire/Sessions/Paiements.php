<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\Paiement;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Suivi des paiements d'une session (brique 015) : qui a payé, combien, ce qu'il
 * reste, et un rappel prêt à envoyer. Le CRM note les versements, il n'encaisse rien.
 */
#[Layout('layouts.app')]
class Paiements extends Component
{
    public Seance $seance;

    /** Inscription dont le formulaire « noter un paiement » est ouvert. */
    public ?int $ouverteId = null;

    public string $montant = '';

    public string $paye_le = '';

    public string $mode = 'virement';

    public string $note = '';

    /** Inscription dont le rappel de paiement est ouvert. */
    public ?int $rappelId = null;

    public string $rappelObjet = '';

    public string $rappelCorps = '';

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
    }

    protected function inscription(int $id): ?Inscription
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.categorie', 'seance.lieuContact', 'contact.entreprise', 'paiements'])
            ->find($id);
    }

    /** @return Collection<int, Inscription> */
    protected function inscriptions(): Collection
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.categorie', 'contact', 'paiements'])
            ->get()
            ->sortBy(fn (Inscription $i) => $i->contact->cleTri())
            ->values();
    }

    private static function nombre(string $valeur): ?float
    {
        $valeur = str_replace([' ', '€', ','], ['', '', '.'], trim($valeur));

        return is_numeric($valeur) ? round((float) $valeur, 2) : null;
    }

    /** Montant propre à une personne ; vide = retour au prix de la session. */
    public function definirMontant(int $id, string $valeur): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $nombre = self::nombre($valeur);
        if (trim($valeur) !== '' && ($nombre === null || $nombre < 0)) {
            session()->flash('message', 'Montant non reconnu : écris un nombre, par exemple 285 ou 142,50.');

            return;
        }

        $inscription->update(['montant_du' => $nombre]);
    }

    public function ouvrir(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $reste = $inscription->resteDu();

        $this->fermerRappel();
        $this->ouverteId = $inscription->id;
        $this->montant = $reste ? rtrim(rtrim(number_format($reste, 2, ',', ''), '0'), ',') : '';
        $this->paye_le = now()->toDateString();
        $this->mode = 'virement';
        $this->note = '';
        $this->resetErrorBag();
    }

    public function fermer(): void
    {
        $this->reset(['ouverteId', 'montant', 'note']);
        $this->resetErrorBag();
    }

    public function enregistrerPaiement(): void
    {
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription) {
            return;
        }

        $this->montant = (string) (self::nombre($this->montant) ?? $this->montant);

        $donnees = $this->validate([
            'montant' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'paye_le' => ['required', 'date'],
            'mode' => ['required', Rule::in(array_keys(Paiement::MODES))],
            'note' => ['nullable', 'string', 'max:255'],
        ], [
            'montant.required' => 'Indique le montant reçu.',
            'montant.numeric' => 'Écris un nombre, par exemple 285 ou 142,50.',
            'montant.gt' => 'Le montant doit être supérieur à zéro.',
        ]);

        $inscription->paiements()->create([
            'montant' => (float) $donnees['montant'],
            'paye_le' => $donnees['paye_le'],
            'mode' => $donnees['mode'],
            'note' => filled($donnees['note']) ? trim($donnees['note']) : null,
        ]);

        session()->flash('message', 'Paiement noté pour '.$inscription->contact->nomComplet().'.');
        $this->fermer();
    }

    /** En un clic : le solde restant est reçu aujourd'hui, par virement. */
    public function solder(int $id): void
    {
        $inscription = $this->inscription($id);
        $reste = $inscription?->resteDu();
        if (! $inscription || ! $reste) {
            return;
        }

        $inscription->paiements()->create(['montant' => $reste, 'paye_le' => now()->toDateString(), 'mode' => 'virement']);
        session()->flash('message', $inscription->contact->nomComplet().' : '.Paiement::euros($reste).' noté(s) comme reçu(s).');
    }

    public function supprimerPaiement(int $paiementId): void
    {
        Paiement::whereKey($paiementId)
            ->whereHas('inscription', fn ($q) => $q->where('seance_id', $this->seance->id))
            ->delete();
    }

    // --- Rappel de paiement --------------------------------------------------

    public function preparerRappel(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $du = $inscription->montantDu();
        $texte = ModeleMessage::parDebut('Paiement', ModeleMessage::NOM_RAPPEL_PAIEMENT)->rendre(
            $inscription->contact, $inscription->seance, auth()->user()?->name, null, [
                'montant' => $du !== null ? Paiement::euros($du) : '',
                'deja_paye' => Paiement::euros($inscription->totalPaye()),
                'reste' => $inscription->resteDu() !== null ? Paiement::euros($inscription->resteDu()) : '',
            ]);

        $this->fermer();
        $this->rappelId = $inscription->id;
        $this->rappelObjet = $texte['objet'];
        $this->rappelCorps = $texte['corps'];
    }

    public function fermerRappel(): void
    {
        $this->reset(['rappelId', 'rappelObjet', 'rappelCorps']);
    }

    /** Le rappel est parti : on en garde la trace sur la fiche du contact. */
    public function rappelEnvoye(): void
    {
        $inscription = $this->rappelId ? $this->inscription($this->rappelId) : null;
        if (! $inscription) {
            return;
        }

        Activite::create([
            'titre' => 'Rappel de paiement envoyé : '.$this->seance->titre,
            'type' => Activite::TYPE_EMAIL,
            'contact_id' => $inscription->contact_id,
            'terminee_at' => now(),
            'notes' => trim((filled($this->rappelObjet) ? 'Objet : '.$this->rappelObjet."\n\n" : '').$this->rappelCorps),
        ]);

        session()->flash('message', 'Rappel noté pour '.$inscription->contact->nomComplet().'.');
        $this->fermerRappel();
    }

    public function render(): View
    {
        $inscriptions = $this->inscriptions();

        $attendu = (float) $inscriptions->sum(fn (Inscription $i) => $i->montantDu() ?? 0);
        $recu = (float) $inscriptions->sum(fn (Inscription $i) => $i->totalPaye());

        return view('livewire.sessions.paiements', [
            'inscriptions' => $inscriptions,
            'prix' => $this->seance->prixApplique(),
            'totaux' => [
                'attendu' => $attendu,
                'recu' => $recu,
                'reste' => (float) $inscriptions->sum(fn (Inscription $i) => $i->resteDu() ?? 0),
                'soldes' => $inscriptions->filter(fn (Inscription $i) => $i->etatPaiement() === 'solde')->count(),
            ],
        ]);
    }
}

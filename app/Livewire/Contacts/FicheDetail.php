<?php

namespace App\Livewire\Contacts;

use App\Models\Activite;
use App\Models\Analyse;
use App\Models\Contact;
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

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
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
                'faite_le' => now(),
            ]);
        } catch (Throwable $e) {
            $this->analyseErreur = $e->getMessage();
        }
    }

    /**
     * Rédige un brouillon de mail de prospection via l'assistant et garde une
     * trace sous forme de tâche « à envoyer » (dédupliquée par contact).
     */
    public function redigerProspection(): void
    {
        $this->reset('prospectionErreur', 'prospectionObjet', 'prospectionCorps');

        try {
            $r = app(AssistantIA::class)->redigerProspection($this->contact, $this->angleProspection ?: null);

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
            'emails' => fn ($q) => $q->orderByDesc('date_email'),
        ]);

        return view('livewire.contacts.fiche-detail', [
            'derniereAnalyse' => $this->contact->analyses()->latest('faite_le')->first(),
        ]);
    }
}

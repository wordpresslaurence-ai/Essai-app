<?php

namespace App\Livewire\Pipeline;

use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Opportunite $opportunite = null;

    public string $titre = '';

    public ?string $contact_id = null;

    public ?string $categorie_id = null;

    public ?string $montant = null;

    public ?string $date_creation = null;

    public string $etape = Opportunite::ETAPE_NOUVEAU;

    public ?string $notes = null;

    public function mount(?Opportunite $opportunite = null): void
    {
        if ($opportunite && $opportunite->exists) {
            $this->opportunite = $opportunite;
            $this->titre = $opportunite->titre;
            $this->contact_id = $opportunite->contact_id ? (string) $opportunite->contact_id : null;
            $this->categorie_id = $opportunite->categorie_id ? (string) $opportunite->categorie_id : null;
            $this->montant = $opportunite->montant !== null ? (string) $opportunite->montant : null;
            $this->date_creation = $opportunite->date_creation?->format('Y-m-d');
            $this->etape = $opportunite->etape;
            $this->notes = $opportunite->notes;

            return;
        }

        // Création : date au jour même par défaut.
        $this->date_creation = now()->toDateString();

        // Depuis la fiche d'un contact (…/pipeline/creer?contact=ID) : pré-remplit
        // le titre « Opportunité — <nom> », rattache le contact, sa catégorie et le
        // prix de référence de celle-ci.
        $contactId = request()->integer('contact');

        if ($contactId) {
            $contact = Contact::find($contactId);

            if ($contact) {
                $this->contact_id = (string) $contact->id;
                $this->titre = 'Opportunité — '.$contact->nomComplet();

                if ($contact->categorie_id) {
                    $this->categorie_id = (string) $contact->categorie_id;
                    $this->prefillMontantDepuisCategorie();
                }
            }
        }
    }

    /**
     * RF-5 : à la sélection d'un contact, pré-remplit la catégorie avec celle du
     * contact (sans écraser un choix déjà fait).
     */
    public function updatedContactId(?string $valeur): void
    {
        if (blank($valeur) || filled($this->categorie_id)) {
            return;
        }

        $categorieContact = Contact::whereKey($valeur)->value('categorie_id');

        if ($categorieContact) {
            $this->categorie_id = (string) $categorieContact;
            $this->prefillMontantDepuisCategorie();
        }
    }

    /** À la sélection d'une catégorie, pré-remplit le montant avec son prix de référence. */
    public function updatedCategorieId(): void
    {
        $this->prefillMontantDepuisCategorie();
    }

    /**
     * Reporte le prix de référence de la catégorie choisie dans le montant,
     * sans écraser un montant déjà saisi.
     */
    protected function prefillMontantDepuisCategorie(): void
    {
        if (blank($this->categorie_id) || filled($this->montant)) {
            return;
        }

        // On passe par le modèle pour bénéficier du cast decimal:2 (ex. « 1200.00 »).
        $prix = Categorie::whereKey($this->categorie_id)->first()?->prix;

        if ($prix !== null) {
            $this->montant = (string) $prix;
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
            'categorie_id' => ['nullable', 'exists:categories,id'],
            'montant' => ['nullable', 'numeric', 'min:0'],
            'date_creation' => ['nullable', 'date'],
            'etape' => ['required', Rule::in(array_keys(Opportunite::libellesEtapes()))],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        if ($this->opportunite) {
            // changerEtape gère la date de clôture si l'étape change.
            if ($this->opportunite->etape !== $donnees['etape']) {
                $this->opportunite->changerEtape($donnees['etape']);
            }
            $this->opportunite->update($donnees);
            $message = 'Opportunité modifiée.';
        } else {
            $op = Opportunite::make($donnees);
            $op->save();
            if (in_array($op->etape, [Opportunite::ETAPE_GAGNE, Opportunite::ETAPE_PERDU], true)) {
                $op->changerEtape($op->etape);
            }
            $message = 'Opportunité créée.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('pipeline.index'), navigate: true);
    }

    public function supprimer()
    {
        $this->opportunite?->delete();
        session()->flash('message', 'Opportunité supprimée.');

        return $this->redirect(route('pipeline.index'), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.pipeline.formulaire', [
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
            'libelles' => Opportunite::libellesEtapes(),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

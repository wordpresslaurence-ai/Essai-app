<?php

namespace App\Livewire\Sessions;

use App\Models\Contact;
use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Seance $seance = null;

    /** Titre de la session copiée, quand on duplique (simple rappel à l'écran). */
    public ?string $dupliqueeDe = null;

    public string $titre = '';

    public ?string $categorie_id = null;

    public ?string $date_debut = null;

    public ?string $lieu = null;

    /** Contact (salle/lieu) rattaché, optionnel. */
    public ?string $lieu_contact_id = null;

    /** Recherche pour la liste déroulante du contact du lieu. */
    public string $rechercheContact = '';

    /** Lien d'inscription public (repris dans les invitations). */
    public ?string $lien_inscription = null;

    /** Lien d'invitation au groupe / à la communauté WhatsApp. */
    public ?string $lien_groupe = null;

    /** Rôle dans le parcours formation : decouverte | formation | (vide). */
    public ?string $role_parcours = null;

    public ?string $capacite = null;

    public ?string $notes = null;

    public function mount(?Seance $seance = null): void
    {
        if ($seance && $seance->exists) {
            $this->seance = $seance;
            $this->titre = $seance->titre;
            $this->categorie_id = $seance->categorie_id ? (string) $seance->categorie_id : null;
            $this->date_debut = $seance->date_debut?->format('Y-m-d\TH:i');
            $this->lieu = $seance->lieu;
            $this->lieu_contact_id = $seance->lieu_contact_id ? (string) $seance->lieu_contact_id : null;
            $this->lien_inscription = $seance->lien_inscription;
            $this->lien_groupe = $seance->lien_groupe;
            $this->role_parcours = $seance->role_parcours;
            $this->capacite = $seance->capacite !== null ? (string) $seance->capacite : null;
            $this->notes = $seance->notes;

            return;
        }

        // Duplication (?dupliquer=ID) : on pré-remplit une NOUVELLE session à partir
        // d'une existante. Rien n'est créé tant que le formulaire n'est pas enregistré.
        $modele = Seance::find(request()->integer('dupliquer'));
        if ($modele) {
            $this->dupliqueeDe = $modele->titre;
            $this->titre = $modele->titre;
            $this->categorie_id = $modele->categorie_id ? (string) $modele->categorie_id : null;
            $this->lieu = $modele->lieu;
            $this->lieu_contact_id = $modele->lieu_contact_id ? (string) $modele->lieu_contact_id : null;
            $this->lien_inscription = $modele->lien_inscription;
            $this->role_parcours = $modele->role_parcours;
            $this->capacite = $modele->capacite !== null ? (string) $modele->capacite : null;
            $this->notes = $modele->notes;
            // Volontairement non repris : la date (à choisir), le lien du groupe WhatsApp
            // (propre à chaque groupe), les inscrits et les invitations.
        }
    }

    protected function rules(): array
    {
        return [
            'titre' => ['required', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'exists:categories,id'],
            'date_debut' => ['nullable', 'date'],
            'lieu' => ['nullable', 'string', 'max:255'],
            'lieu_contact_id' => ['nullable', 'exists:contacts,id'],
            'lien_inscription' => ['nullable', 'url', 'max:500'],
            'lien_groupe' => ['nullable', 'url', 'max:500'],
            'role_parcours' => ['nullable', \Illuminate\Validation\Rule::in(array_keys(Seance::ROLES_PARCOURS))],
            'capacite' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function enregistrer()
    {
        // « Aucun » (chaîne vide) => null, pour passer la règle exists (nullable).
        $this->lieu_contact_id = filled($this->lieu_contact_id) ? $this->lieu_contact_id : null;

        $this->lien_inscription = filled($this->lien_inscription) ? trim($this->lien_inscription) : null;
        $this->lien_groupe = filled($this->lien_groupe) ? trim($this->lien_groupe) : null;
        $this->role_parcours = filled($this->role_parcours) ? $this->role_parcours : null;

        $donnees = $this->validate();
        $donnees['capacite'] = $donnees['capacite'] !== null && $donnees['capacite'] !== ''
            ? (int) $donnees['capacite'] : null;

        if ($this->seance) {
            $this->seance->update($donnees);
            $cible = $this->seance;
            $message = 'Session modifiée.';
        } else {
            $cible = Seance::create($donnees);
            $message = 'Session créée.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('sessions.fiche', $cible), navigate: true);
    }

    public function supprimer()
    {
        $this->seance?->delete();
        session()->flash('message', 'Session supprimée.');

        return $this->redirect(route('sessions.index'), navigate: true);
    }

    public function render(): View
    {
        // Liste filtrée pour le choix du contact du lieu ; on garde toujours
        // le contact déjà sélectionné, même hors filtre.
        $contacts = Contact::actifs()
            ->when($this->rechercheContact !== '', fn ($q) => $q->recherche($this->rechercheContact))
            ->get(['id', 'nom', 'prenom', 'type', 'adresse_ville'])
            ->sortBy(fn (Contact $c) => $c->cleTri())
            ->values();

        if ($this->lieu_contact_id && ! $contacts->contains('id', (int) $this->lieu_contact_id)) {
            $selectionne = Contact::find($this->lieu_contact_id, ['id', 'nom', 'prenom', 'type', 'adresse_ville']);
            if ($selectionne) {
                $contacts->prepend($selectionne);
            }
        }

        return view('livewire.sessions.formulaire', [
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
            'contacts' => $contacts,
        ]);
    }
}

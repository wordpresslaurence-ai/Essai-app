<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Rules\NumeroEntrepriseBe;
use App\Rules\NumeroTvaBe;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?Contact $contact = null;

    // Champs du formulaire
    public string $type = Contact::TYPE_PERSONNE;

    public string $nom = '';

    public ?string $prenom = null;

    public ?string $fonction = null;

    public ?string $email = null;

    public ?string $telephone = null;

    public ?string $entreprise_id = null;

    public ?string $site_web = null;

    public ?string $numero_entreprise = null;

    public ?string $numero_tva = null;

    public ?string $secteur = null;

    public ?string $adresse_rue = null;

    public ?string $adresse_code_postal = null;

    public ?string $adresse_ville = null;

    public string $adresse_pays = 'BE';

    public ?string $notes = null;

    /** Pré-remplit le formulaire en mode édition. */
    public function mount(?Contact $contact = null): void
    {
        if ($contact && $contact->exists) {
            $this->contact = $contact;
            $this->fill($contact->only([
                'type', 'nom', 'prenom', 'fonction', 'email', 'telephone',
                'entreprise_id', 'site_web', 'numero_entreprise', 'numero_tva',
                'secteur', 'adresse_rue', 'adresse_code_postal', 'adresse_ville',
                'adresse_pays', 'notes',
            ]));
        }
    }

    protected function rules(): array
    {
        return [
            'type' => ['required', Rule::in([Contact::TYPE_PERSONNE, Contact::TYPE_ENTREPRISE])],
            'nom' => ['required', 'string', 'max:255'],
            'prenom' => ['nullable', 'string', 'max:255'],
            'fonction' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'telephone' => ['nullable', 'string', 'max:50'],
            'entreprise_id' => ['nullable', 'exists:contacts,id'],
            'site_web' => ['nullable', 'url', 'max:255'],
            'numero_entreprise' => ['nullable', new NumeroEntrepriseBe],
            'numero_tva' => ['nullable', new NumeroTvaBe],
            'secteur' => ['nullable', 'string', 'max:255'],
            'adresse_rue' => ['nullable', 'string', 'max:255'],
            'adresse_code_postal' => ['nullable', 'string', 'max:20'],
            'adresse_ville' => ['nullable', 'string', 'max:255'],
            'adresse_pays' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ];
    }

    /**
     * Doublons potentiels, signalés sans bloquer (EF-13) : même e-mail,
     * ou même nom pour une personne de la même entreprise.
     */
    public function getDoublonsProperty()
    {
        if (blank($this->email) && blank($this->nom)) {
            return collect();
        }

        return Contact::query()
            ->when($this->contact, fn ($q) => $q->whereKeyNot($this->contact->id))
            ->where(function ($q) {
                if (filled($this->email)) {
                    $q->orWhere('email', $this->email);
                }
                if (filled($this->nom) && filled($this->entreprise_id)) {
                    $q->orWhere(fn ($sub) => $sub
                        ->where('nom', $this->nom)
                        ->where('entreprise_id', $this->entreprise_id));
                }
            })
            ->limit(5)
            ->get();
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        // Les champs propres à un type sont vidés pour l'autre type.
        if ($this->type === Contact::TYPE_ENTREPRISE) {
            $donnees['prenom'] = null;
            $donnees['fonction'] = null;
            $donnees['entreprise_id'] = null;
        } else {
            $donnees['site_web'] = null;
            $donnees['numero_entreprise'] = null;
            $donnees['numero_tva'] = null;
            $donnees['secteur'] = null;
        }

        if ($this->contact) {
            $this->contact->update($donnees);
            $message = 'Contact modifié.';
            $cible = $this->contact;
        } else {
            $cible = Contact::create($donnees);
            $message = 'Contact créé.';
        }

        session()->flash('message', $message);

        return $this->redirect(route('contacts.fiche', $cible), navigate: true);
    }

    public function render(): View
    {
        $entreprises = Contact::entreprises()
            ->actifs()
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return view('livewire.contacts.formulaire', [
            'entreprises' => $entreprises,
            'doublons' => $this->doublons,
        ]);
    }
}

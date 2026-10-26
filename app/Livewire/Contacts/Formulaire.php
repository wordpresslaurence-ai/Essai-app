<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Etiquette;
use App\Models\Pole;
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

    public ?string $region = null;

    public ?string $linkedin = null;

    public ?string $instagram = null;

    public ?string $facebook = null;

    public ?string $langues = null;

    public ?string $discipline = null;

    public ?string $discipline_precision = null;

    public ?string $public_precision = null;

    public ?string $formats_proposes = null;

    public ?string $fait_observe = null;

    public ?string $angle_contact = null;

    public ?string $role_commercial = null;

    public ?string $priorite_prospection = null;

    public ?string $interet_exprime = null;

    public ?string $besoin_exprime = null;

    public ?string $url_sources = null;

    public ?string $date_verification = null;

    public bool $ne_plus_contacter = false;

    public ?string $experience_yoga = null;

    public ?string $origine = null;

    public ?string $origine_precision = null;

    public ?string $url_profil = null;

    public ?string $temperature = null;

    public ?string $categorie_id = null;

    public ?string $adresse_rue = null;

    public ?string $adresse_code_postal = null;

    public ?string $adresse_ville = null;

    public string $adresse_pays = 'BE';

    public ?string $notes = null;

    /** @var array<int> Étiquettes cochées pour ce contact. */
    public array $etiquettesSelection = [];

    /** Pré-remplit le formulaire en mode édition. */
    public function mount(?Contact $contact = null): void
    {
        if ($contact && $contact->exists) {
            $this->contact = $contact;
            $this->fill($contact->only([
                'type', 'nom', 'prenom', 'fonction', 'email', 'telephone',
                'entreprise_id', 'site_web', 'numero_entreprise', 'numero_tva',
                'secteur', 'region', 'linkedin', 'instagram', 'facebook', 'langues',
                'discipline', 'discipline_precision', 'public_precision', 'formats_proposes',
                'fait_observe', 'angle_contact', 'role_commercial', 'priorite_prospection',
                'interet_exprime', 'besoin_exprime', 'url_sources',
                'experience_yoga', 'origine', 'origine_precision', 'url_profil', 'temperature',
                'adresse_rue', 'adresse_code_postal', 'adresse_ville', 'adresse_pays', 'notes',
            ]));
            $this->date_verification = $contact->date_verification?->format('Y-m-d');
            $this->ne_plus_contacter = (bool) $contact->ne_plus_contacter;
            $this->categorie_id = $contact->categorie_id ? (string) $contact->categorie_id : null;
            $this->etiquettesSelection = $contact->etiquettes->pluck('id')->all();
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
            'region' => ['nullable', 'string', 'max:255'],
            'linkedin' => ['nullable', 'string', 'max:255'],
            'instagram' => ['nullable', 'string', 'max:255'],
            'facebook' => ['nullable', 'string', 'max:255'],
            'langues' => ['nullable', 'string', 'max:255'],
            'discipline' => ['nullable', 'string', 'max:255'],
            'discipline_precision' => ['nullable', 'string', 'max:2000'],
            'public_precision' => ['nullable', 'string', 'max:2000'],
            'formats_proposes' => ['nullable', 'string', 'max:2000'],
            'fait_observe' => ['nullable', 'string', 'max:2000'],
            'angle_contact' => ['nullable', 'string', 'max:2000'],
            'role_commercial' => ['nullable', 'string', 'max:255'],
            'priorite_prospection' => ['nullable', Rule::in(array_keys(Contact::PRIORITES))],
            'interet_exprime' => ['nullable', 'string', 'max:2000'],
            'besoin_exprime' => ['nullable', 'string', 'max:2000'],
            'url_sources' => ['nullable', 'string', 'max:2000'],
            'date_verification' => ['nullable', 'date'],
            'ne_plus_contacter' => ['boolean'],
            'experience_yoga' => ['nullable', Rule::in(array_keys(Contact::EXPERIENCES_YOGA))],
            'origine' => ['nullable', Rule::in(array_keys(Contact::ORIGINES))],
            'origine_precision' => ['nullable', 'string', 'max:255'],
            'url_profil' => ['nullable', 'string', 'max:255'],
            'temperature' => ['nullable', Rule::in(array_keys(Contact::TEMPERATURES))],
            'categorie_id' => ['nullable', 'exists:categories,id'],
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
        $email = trim((string) $this->email);
        $nom = trim((string) $this->nom);

        // Critères de doublon réellement applicables : même e-mail, ou même nom
        // dans la même entreprise. Sans critère, on ne cherche RIEN (sinon un
        // WHERE vide ramènerait tous les contacts → faux doublons).
        $critereEmail = $email !== '';
        $critereNom = $nom !== '' && filled($this->entreprise_id);

        if (! $critereEmail && ! $critereNom) {
            return collect();
        }

        return Contact::query()
            ->when($this->contact, fn ($q) => $q->whereKeyNot($this->contact->id))
            ->where(function ($q) use ($email, $nom, $critereEmail, $critereNom) {
                if ($critereEmail) {
                    $q->orWhereRaw('LOWER(email) = ?', [mb_strtolower($email)]);
                }
                if ($critereNom) {
                    $q->orWhere(fn ($sub) => $sub
                        ->where('nom', $nom)
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

        // Synchronise les étiquettes cochées avec le contact.
        $cible->etiquettes()->sync($this->etiquettesSelection);

        session()->flash('message', $message);

        return $this->redirect(route('contacts.fiche', $cible), navigate: true);
    }

    public function render(): View
    {
        $entreprises = Contact::typeEntreprise()
            ->actifs()
            ->orderBy('nom')
            ->get(['id', 'nom']);

        return view('livewire.contacts.formulaire', [
            'entreprises' => $entreprises,
            'doublons' => $this->doublons,
            'toutesEtiquettes' => Etiquette::orderBy('nom')->get(),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

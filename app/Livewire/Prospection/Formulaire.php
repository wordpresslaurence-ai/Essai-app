<?php

namespace App\Livewire\Prospection;

use App\Models\Opportunite;
use App\Models\Pole;
use App\Models\RechercheProspection;
use App\Services\Prospection\GenerateurBrief;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Formulaire extends Component
{
    public ?RechercheProspection $recherche = null;

    public string $nom = '';

    public ?string $categorie_id = null;

    /** @var array<int, string> */
    public array $sources = ['web'];

    public int $nombre = 10;

    public ?string $role = null;

    public ?string $personnes_recherchees = null;

    public ?string $zones = null;

    public ?string $langues = null;

    public ?string $criteres_obligatoires = null;

    public ?string $criteres_souhaitables = null;

    public ?string $profils_privilegier = null;

    public ?string $profils_eviter = null;

    public ?string $contact_requis = null;

    public ?string $exclusions = null;

    public bool $enrichissement = false;

    public ?string $instructions = null;

    public function mount(?RechercheProspection $recherche = null): void
    {
        if ($recherche && $recherche->exists) {
            $this->recherche = $recherche;
            $this->fill($recherche->only([
                'nom', 'nombre', 'role', 'personnes_recherchees', 'zones', 'langues',
                'criteres_obligatoires', 'criteres_souhaitables', 'profils_privilegier',
                'profils_eviter', 'contact_requis', 'exclusions', 'instructions',
            ]));
            $this->categorie_id = $recherche->categorie_id ? (string) $recherche->categorie_id : null;
            $this->sources = $recherche->sources ?: ['web'];
            $this->enrichissement = (bool) $recherche->enrichissement;
        }
    }

    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_id' => ['nullable', 'exists:categories,id'],
            'sources' => ['array'],
            'sources.*' => [Rule::in(array_keys(RechercheProspection::SOURCES))],
            'nombre' => ['required', 'integer', 'min:1', 'max:100'],
            'role' => ['nullable', Rule::in(array_keys(Opportunite::ROLES))],
            'personnes_recherchees' => ['nullable', 'string', 'max:2000'],
            'zones' => ['nullable', 'string', 'max:2000'],
            'langues' => ['nullable', 'string', 'max:255'],
            'criteres_obligatoires' => ['nullable', 'string', 'max:2000'],
            'criteres_souhaitables' => ['nullable', 'string', 'max:2000'],
            'profils_privilegier' => ['nullable', 'string', 'max:2000'],
            'profils_eviter' => ['nullable', 'string', 'max:2000'],
            'contact_requis' => ['nullable', 'string', 'max:255'],
            'exclusions' => ['nullable', 'string', 'max:2000'],
            'enrichissement' => ['boolean'],
            'instructions' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function enregistrer()
    {
        $donnees = $this->validate();

        // Copie figée de la description de l'offre (la catégorie) au moment de la campagne.
        $categorie = $this->categorie_id
            ? \App\Models\Categorie::find($this->categorie_id)
            : null;
        $donnees['offre_copie'] = $categorie?->offre;
        $donnees['categorie_id'] = $categorie?->id;

        if ($this->recherche) {
            $this->recherche->update($donnees);
            session()->flash('message', 'Recherche enregistrée.');

            return $this->redirect(route('prospection.modifier', $this->recherche), navigate: true);
        }

        $recherche = RechercheProspection::create($donnees);
        session()->flash('message', 'Recherche créée. Voici le brief à coller dans ChatGPT.');

        return $this->redirect(route('prospection.modifier', $recherche), navigate: true);
    }

    public function render(): View
    {
        return view('livewire.prospection.formulaire', [
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
            'rolesDispo' => Opportunite::ROLES,
            'sourcesDispo' => RechercheProspection::SOURCES,
            'brief' => $this->recherche ? app(GenerateurBrief::class)->generer($this->recherche) : null,
        ]);
    }
}

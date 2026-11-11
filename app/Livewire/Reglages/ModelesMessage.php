<?php

namespace App\Livewire\Reglages;

use App\Models\ModeleMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Gestion des modèles de messages (invitations, relances…) — brique 012.
 */
#[Layout('layouts.app')]
class ModelesMessage extends Component
{
    public ?int $editId = null;

    public string $nom = '';

    public string $canal = ModeleMessage::CANAL_EMAIL;

    public ?string $objet = null;

    public string $corps = '';

    protected function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'canal' => ['required', Rule::in(array_keys(ModeleMessage::CANAUX))],
            'objet' => ['nullable', 'string', 'max:255'],
            'corps' => ['required', 'string', 'max:10000'],
        ];
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate();

        // Un message court n'a pas d'objet.
        if ($donnees['canal'] !== ModeleMessage::CANAL_EMAIL || blank($donnees['objet'])) {
            $donnees['objet'] = null;
        }

        if ($this->editId) {
            ModeleMessage::findOrFail($this->editId)->update($donnees);
            session()->flash('message', 'Modèle modifié.');
        } else {
            ModeleMessage::create($donnees);
            session()->flash('message', 'Modèle ajouté.');
        }

        $this->annuler();
    }

    public function editer(ModeleMessage $modele): void
    {
        $this->resetValidation();
        $this->editId = $modele->id;
        $this->nom = $modele->nom;
        $this->canal = $modele->canal;
        $this->objet = $modele->objet;
        $this->corps = $modele->corps;
    }

    public function annuler(): void
    {
        $this->reset(['editId', 'nom', 'canal', 'objet', 'corps']);
        $this->resetValidation();
    }

    public function supprimer(ModeleMessage $modele): void
    {
        $modele->delete();

        if ($this->editId === $modele->id) {
            $this->annuler();
        }

        session()->flash('message', 'Modèle supprimé.');
    }

    /** Crée les modèles de départ qui n'existent pas encore (repérés par leur nom). */
    public function ajouterModelesDeDepart(): void
    {
        $crees = 0;
        foreach (ModeleMessage::modelesDeDepart() as $modele) {
            if (! ModeleMessage::where('nom', $modele['nom'])->exists()) {
                ModeleMessage::create($modele);
                $crees++;
            }
        }

        session()->flash('message', $crees > 0
            ? $crees.' modèle(s) de départ ajouté(s). Adapte-les à ta façon d’écrire.'
            : 'Les modèles de départ existent déjà.');
    }

    public function render(): View
    {
        return view('livewire.reglages.modeles-message', [
            'modeles' => ModeleMessage::orderBy('nom')->get(),
        ]);
    }
}

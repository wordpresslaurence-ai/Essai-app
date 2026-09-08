<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Etiquette;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Liste extends Component
{
    use WithPagination;

    /** Terme de recherche (EF-09). */
    #[Url]
    public string $recherche = '';

    /** Filtre par type : '', 'personne' ou 'entreprise' (EF-10). */
    #[Url]
    public string $type = '';

    /** Filtre par étiquette (id) (EF-10). */
    #[Url]
    public string $etiquette = '';

    /** Filtre par statut : 'actifs' (défaut) ou 'archives' (EF-10). */
    #[Url]
    public string $statut = 'actifs';

    /** Filtre lead : '' (tous), 'leads' (leads uniquement) ou une température précise. */
    #[Url]
    public string $temperature = '';

    /** Colonne de tri. */
    #[Url]
    public string $tri = 'nom';

    /** Sens de tri. */
    #[Url]
    public string $sens = 'asc';

    /** Réinitialise la pagination dès qu'un filtre change. */
    public function updating($name): void
    {
        if (in_array($name, ['recherche', 'type', 'etiquette', 'statut', 'temperature'], true)) {
            $this->resetPage();
        }
    }

    /** Change la colonne de tri (ou inverse le sens si déjà active). */
    public function trierPar(string $colonne): void
    {
        if ($this->tri === $colonne) {
            $this->sens = $this->sens === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tri = $colonne;
            $this->sens = 'asc';
        }
    }

    public function reinitialiserFiltres(): void
    {
        $this->reset(['recherche', 'type', 'etiquette', 'statut']);
        $this->resetPage();
    }

    public function render(): View
    {
        $triAutorise = in_array($this->tri, ['nom', 'updated_at'], true) ? $this->tri : 'nom';
        $sensAutorise = $this->sens === 'desc' ? 'desc' : 'asc';

        $contacts = Contact::query()
            ->with(['entreprise', 'etiquettes'])
            ->recherche($this->recherche)
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->etiquette !== '', fn ($q) => $q->whereHas(
                'etiquettes',
                fn ($e) => $e->where('etiquettes.id', $this->etiquette)
            ))
            ->when(
                $this->statut === 'archives',
                fn ($q) => $q->archives(),
                fn ($q) => $q->actifs()
            )
            ->when($this->temperature === 'leads', fn ($q) => $q->leads())
            ->when(
                $this->temperature !== '' && $this->temperature !== 'leads',
                fn ($q) => $q->where('temperature', $this->temperature)
            )
            ->orderBy($triAutorise, $sensAutorise)
            ->paginate(15);

        return view('livewire.contacts.liste', [
            'contacts' => $contacts,
            'etiquettes' => Etiquette::orderBy('nom')->get(),
        ]);
    }
}

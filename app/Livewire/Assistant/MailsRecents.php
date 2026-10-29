<?php

namespace App\Livewire\Assistant;

use App\Models\Email;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vue « Mails récents » : tous les e-mails synchronisés, tous contacts confondus,
 * du plus récent au plus ancien. Évite de chercher fiche par fiche.
 */
#[Layout('layouts.app')]
class MailsRecents extends Component
{
    use WithPagination;

    /** Filtre par sens : '' (tous), 'entrant' ou 'sortant'. */
    #[Url]
    public string $sens = '';

    #[Url]
    public string $recherche = '';

    public function updating($name): void
    {
        if (in_array($name, ['sens', 'recherche'], true)) {
            $this->resetPage();
        }
    }

    public function render(): View
    {
        $mails = Email::with('contact')
            ->when($this->sens !== '', fn ($q) => $q->where('direction', $this->sens))
            ->when($this->recherche !== '', fn ($q) => $q->where(function ($s) {
                $motif = '%'.$this->recherche.'%';
                $s->where('objet', 'like', $motif)
                    ->orWhereHas('contact', fn ($c) => $c->where('nom', 'like', $motif)->orWhere('prenom', 'like', $motif));
            }))
            ->orderByDesc('date_email')
            ->paginate(20);

        return view('livewire.assistant.mails-recents', ['mails' => $mails]);
    }
}

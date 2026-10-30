<?php

namespace App\Livewire\Assistant;

use App\Models\Email;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
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

    /** Nombre de mois de mails conservés par le bouton de nettoyage. */
    public const MOIS_CONSERVATION = 12;

    /** Filtre par sens : '' (tous), 'entrant' ou 'sortant'. */
    #[Url]
    public string $sens = '';

    #[Url]
    public string $recherche = '';

    /** Nombre de mails supprimés au dernier nettoyage (pour le message de confirmation). */
    public ?int $derniersSupprimes = null;

    public function updating($name): void
    {
        if (in_array($name, ['sens', 'recherche'], true)) {
            $this->resetPage();
        }
    }

    /** Date avant laquelle un mail est considéré comme « ancien ». */
    private function limiteAnciennete(): Carbon
    {
        return Carbon::now()->subMonths(self::MOIS_CONSERVATION)->startOfDay();
    }

    /** Requête des mails antérieurs à la limite de conservation. */
    private function requeteAnciens()
    {
        return Email::query()
            ->whereNotNull('date_email')
            ->where('date_email', '<', $this->limiteAnciennete());
    }

    /**
     * Supprime définitivement les mails de plus de 12 mois (action manuelle,
     * déclenchée par le bouton, après confirmation). Ne touche ni aux contacts
     * ni aux mails récents.
     */
    public function supprimerAnciens(): void
    {
        $this->derniersSupprimes = $this->requeteAnciens()->delete();
        $this->resetPage();
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
            ->orderByDesc('id')
            ->paginate(20);

        return view('livewire.assistant.mails-recents', [
            'mails' => $mails,
            'nbAnciens' => $this->requeteAnciens()->count(),
        ]);
    }
}

<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Etiquette;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Export CSV des contacts pour une newsletter (réimportable dans FluentCRM).
 * Filtre par étiquette + suivi des déjà-exportés (pour n'exporter que les nouveaux).
 */
#[Layout('layouts.app')]
class ExportNewsletter extends Component
{
    /** Étiquette ciblée (id) ; vide = tous les contacts avec e-mail. */
    public string $etiquette = '';

    /** Portée : 'nouveaux' (jamais exportés) ou 'tous'. */
    public string $scope = 'nouveaux';

    public function mount(): void
    {
        // Pré-sélectionne une étiquette « Newsletter » si elle existe.
        $this->etiquette = (string) (Etiquette::whereRaw('LOWER(nom) = ?', ['newsletter'])->value('id') ?? '');
    }

    /** @return Builder<Contact> */
    private function cible(): Builder
    {
        return Contact::query()
            ->actifs()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->when($this->etiquette !== '', fn (Builder $q) => $q->whereHas(
                'etiquettes',
                fn (Builder $e) => $e->where('etiquettes.id', $this->etiquette)
            ));
    }

    /** @return Builder<Contact> */
    private function cibleScope(): Builder
    {
        return $this->cible()
            ->when($this->scope === 'nouveaux', fn (Builder $q) => $q->whereNull('newsletter_exporte_le'));
    }

    /** Construit le contenu CSV (compatible FluentCRM). */
    public function genererCsv(Collection $contacts): string
    {
        $lignes = ["\xEF\xBB\xBF".'Prénom,Nom,Email,Téléphone,Société'];

        foreach ($contacts as $c) {
            $societe = $c->estEntreprise()
                ? $c->nom
                : ($c->relationLoaded('entreprise') ? $c->entreprise?->nom : $c->entreprise()->value('nom'));

            $lignes[] = implode(',', [
                $this->champ($c->estEntreprise() ? '' : $c->prenom),
                $this->champ($c->nom),
                $this->champ($c->email),
                $this->champ($c->telephone),
                $this->champ($societe),
            ]);
        }

        return implode("\r\n", $lignes)."\r\n";
    }

    private function champ(?string $valeur): string
    {
        return '"'.str_replace('"', '""', (string) $valeur).'"';
    }

    public function exporter()
    {
        $contacts = $this->cibleScope()->with('entreprise')->orderBy('nom')->get();

        if ($contacts->isEmpty()) {
            session()->flash('erreur', 'Aucun contact à exporter pour ces critères.');

            return null;
        }

        $csv = $this->genererCsv($contacts);

        // Marque ces contacts comme exportés (pour n'exporter que les nouveaux ensuite).
        Contact::whereIn('id', $contacts->pluck('id'))->update(['newsletter_exporte_le' => now()]);

        $nom = 'newsletter-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($csv) {
            echo $csv;
        }, $nom, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Réinitialise le suivi d'export pour la cible (permet de tout ré-exporter). */
    public function reinitialiser(): void
    {
        $this->cible()->update(['newsletter_exporte_le' => null]);
        session()->flash('message', 'Suivi d’export réinitialisé : tous ces contacts sont de nouveau « à exporter ».');
    }

    public function render(): View
    {
        return view('livewire.contacts.export-newsletter', [
            'etiquettes' => Etiquette::withCount('contacts')->orderBy('nom')->get(),
            'total' => $this->cible()->count(),
            'nouveaux' => (clone $this->cible())->whereNull('newsletter_exporte_le')->count(),
        ]);
    }
}

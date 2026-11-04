<?php

namespace App\Livewire\Pipeline;

use App\Models\Opportunite;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Tableau extends Component
{
    /** Afficher aussi la colonne des opportunités perdues. */
    #[Url]
    public bool $voirPerdus = false;

    /** Filtre par pôle (id). */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id). */
    #[Url]
    public string $categorie = '';

    /** Filtre par rôle (participant / partenaire / les_deux). */
    #[Url]
    public string $role = '';

    /** Changer de pôle réinitialise la catégorie choisie. */
    public function updatedPole(): void
    {
        $this->categorie = '';
    }

    /** Déplace une opportunité vers une autre étape. */
    public function deplacer(int $opportuniteId, string $etape): void
    {
        if (! array_key_exists($etape, Opportunite::libellesEtapes())) {
            return;
        }

        Opportunite::findOrFail($opportuniteId)->changerEtape($etape);
    }

    public function supprimer(int $opportuniteId): void
    {
        Opportunite::findOrFail($opportuniteId)->delete();
        session()->flash('message', 'Opportunité supprimée.');
    }

    /** Requête de base appliquant les mêmes filtres que le kanban (hors étape). */
    private function requeteFiltree(): Builder
    {
        return Opportunite::query()
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role));
    }

    /**
     * Chiffres clés du pipeline + répartition par catégorie (respecte les filtres).
     *
     * @return array<string, mixed>
     */
    private function tableauDeBord(): array
    {
        $enCours = $this->requeteFiltree()
            ->whereIn('etape', Opportunite::ETAPES_EN_COURS)
            ->with('categorie')
            ->get();

        $nb = $enCours->count();
        $pipeline = (float) $enCours->sum('montant');
        $previsionnel = (float) $enCours->sum(fn (Opportunite $o) => $o->montantPondere());

        $gagneMois = (float) $this->requeteFiltree()
            ->where('etape', Opportunite::ETAPE_GAGNE)
            ->whereNotNull('date_cloture')
            ->whereBetween('date_cloture', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->sum('montant');

        $nbGagnes = $this->requeteFiltree()->where('etape', Opportunite::ETAPE_GAGNE)->count();
        $nbPerdus = $this->requeteFiltree()->where('etape', Opportunite::ETAPE_PERDU)->count();

        $parCategorie = $enCours
            ->groupBy(fn (Opportunite $o) => $o->categorie?->nom ?? 'Sans catégorie')
            ->map(fn ($grp, $nom) => [
                'nom' => $nom,
                'couleur' => optional($grp->first()->categorie)->couleur,
                'nb' => $grp->count(),
                'montant' => (float) $grp->sum('montant'),
                'previsionnel' => (float) $grp->sum(fn (Opportunite $o) => $o->montantPondere()),
            ])
            ->sortByDesc('montant')
            ->values();

        return [
            'nb' => $nb,
            'pipeline' => $pipeline,
            'previsionnel' => $previsionnel,
            'panier' => $nb > 0 ? $pipeline / $nb : 0.0,
            'gagne_mois' => $gagneMois,
            'conversion' => ($nbGagnes + $nbPerdus) > 0 ? (int) round($nbGagnes / ($nbGagnes + $nbPerdus) * 100) : null,
            'par_categorie' => $parCategorie,
        ];
    }

    public function render(): View
    {
        $etapes = Opportunite::etapesActives();
        if ($this->voirPerdus) {
            $etapes[] = Opportunite::ETAPE_PERDU;
        }

        $opportunites = Opportunite::with(['contact', 'categorie'])
            ->whereIn('etape', $etapes)
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
            ->orderByDesc('montant')
            ->get()
            ->groupBy('etape');

        $colonnes = [];
        foreach ($etapes as $etape) {
            $lot = $opportunites->get($etape, collect());
            $colonnes[$etape] = [
                'libelle' => Opportunite::libellesEtapes()[$etape],
                'couleur' => Opportunite::couleurEtape($etape),
                'items' => $lot,
                'total' => $lot->sum('montant'),
            ];
        }

        return view('livewire.pipeline.tableau', [
            'colonnes' => $colonnes,
            'libelles' => Opportunite::libellesEtapes(),
            'roles' => Opportunite::ROLES,
            'nbPerdus' => Opportunite::perdues()->count(),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
            'tableauBord' => $this->tableauDeBord(),
        ]);
    }
}

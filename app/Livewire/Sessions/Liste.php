<?php

namespace App\Livewire\Sessions;

use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Liste des sessions (séances) : places prises/restantes, liste d'attente,
 * filtres Pôle → Catégorie, onglets À venir / Passées.
 */
#[Layout('layouts.app')]
class Liste extends Component
{
    use WithPagination;

    /** Onglet : 'avenir' (défaut), 'passees' ou 'agenda' (vue annuelle). */
    #[Url]
    public string $onglet = 'avenir';

    /** Année affichée dans l'agenda (0 = année en cours). */
    #[Url]
    public int $annee = 0;

    /** Filtre par pôle (id). */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id). */
    #[Url]
    public string $categorie = '';

    public function updatedPole(): void
    {
        $this->categorie = '';
        $this->resetPage();
    }

    public function updatedCategorie(): void
    {
        $this->resetPage();
    }

    public function updatedOnglet(): void
    {
        $this->resetPage();
    }

    public function anneePrecedente(): void
    {
        $this->annee = $this->anneeAffichee() - 1;
    }

    public function anneeSuivante(): void
    {
        $this->annee = $this->anneeAffichee() + 1;
    }

    public function anneeCourante(): void
    {
        $this->annee = 0;
    }

    private function anneeAffichee(): int
    {
        return $this->annee >= 2000 && $this->annee <= 2100 ? $this->annee : (int) now()->year;
    }

    /**
     * Données de l'agenda annuel : 12 mois, et pour chaque jour les sessions
     * (un point de couleur par session, à la couleur de sa catégorie).
     *
     * @return array{mois: array<int, array<string, mixed>>, total: int, sansDate: int, legende: \Illuminate\Support\Collection}
     */
    private function agenda(int $annee): array
    {
        $filtre = fn ($q) => $q
            ->when($this->categorie !== '', fn ($c) => $c->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($c) => $c->whereHas('categorie', fn ($cat) => $cat->where('pole_id', $this->pole))
            );

        $seances = $filtre(Seance::with('categorie.pole'))
            ->whereYear('date_debut', $annee)
            ->orderBy('date_debut')
            ->get();

        $couleur = fn (Seance $s) => $s->categorie?->couleur ?: 'var(--accent)';

        $parJour = $seances->groupBy(fn (Seance $s) => $s->date_debut->toDateString())
            ->map(fn ($groupe) => $groupe->map(fn (Seance $s) => [
                'id' => $s->id,
                'titre' => $s->titre,
                'libelle' => $s->titre.', '.$s->date_debut->translatedFormat('j F Y'),
                'couleur' => $couleur($s),
            ])->all());

        $mois = [];
        for ($m = 1; $m <= 12; $m++) {
            $premier = \Illuminate\Support\Carbon::create($annee, $m, 1);
            $jours = [];
            for ($j = 1; $j <= $premier->daysInMonth; $j++) {
                $date = $premier->copy()->day($j);
                $jours[] = [
                    'numero' => $j,
                    'aujourdhui' => $date->isToday(),
                    'seances' => $parJour->get($date->toDateString(), []),
                ];
            }

            $mois[] = [
                'numero' => $m,
                'nom' => $premier->translatedFormat('F'),
                'decalage' => $premier->dayOfWeekIso - 1, // semaine commençant le lundi
                'jours' => $jours,
            ];
        }

        return [
            'mois' => $mois,
            'total' => $seances->count(),
            'sansDate' => $filtre(Seance::query())->whereNull('date_debut')->count(),
            'legende' => $seances->map(fn (Seance $s) => [
                'libelle' => $s->categorie?->libelleComplet() ?? 'Sans catégorie',
                'couleur' => $couleur($s),
            ])->unique('libelle')->values(),
        ];
    }

    public function render(): View
    {
        $seances = Seance::with(['categorie.pole', 'lieuContact'])
            ->withCount(['inscriptions as confirmes_count' => fn ($q) => $q->where('statut', 'confirme')])
            ->withCount(['inscriptions as attente_count' => fn ($q) => $q->where('statut', 'attente')])
            ->when($this->onglet === 'passees', fn ($q) => $q->passees(), fn ($q) => $q->aVenir())
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            ->orderByRaw('date_debut is null')
            ->orderBy('date_debut', $this->onglet === 'passees' ? 'desc' : 'asc')
            ->paginate(20);

        return view('livewire.sessions.liste', [
            'annee' => $this->anneeAffichee(),
            'agenda' => $this->onglet === 'agenda' ? $this->agenda($this->anneeAffichee()) : null,
            'seances' => $seances,
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

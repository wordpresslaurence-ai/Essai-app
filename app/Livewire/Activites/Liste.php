<?php

namespace App\Livewire\Activites;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Liste extends Component
{
    /** Filtre : a_faire (défaut), terminees, toutes. */
    #[Url]
    public string $filtre = 'a_faire';

    /** Filtre par pôle (id) — via le contact lié. */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id) — via le contact lié. */
    #[Url]
    public string $categorie = '';

    /** Vue : 'liste' (défaut) ou 'calendrier'. */
    #[Url]
    public string $vue = 'liste';

    /** Mode du calendrier : 'mois' (défaut) ou 'semaine'. */
    #[Url]
    public string $modeCalendrier = 'mois';

    /** Date de référence (AAAA-MM-JJ) de la période affichée ; aujourd'hui par défaut. */
    #[Url]
    public ?string $dateRef = null;

    /** Changer de pôle réinitialise la catégorie choisie. */
    public function updatedPole(): void
    {
        $this->categorie = '';
    }

    private function reference(): Carbon
    {
        return $this->dateRef ? Carbon::parse($this->dateRef)->startOfDay() : Carbon::today();
    }

    public function aujourdhui(): void
    {
        $this->dateRef = Carbon::today()->toDateString();
    }

    public function periodePrecedente(): void
    {
        $ref = $this->reference();
        $this->dateRef = ($this->modeCalendrier === 'semaine' ? $ref->subWeek() : $ref->subMonthNoOverflow())->toDateString();
    }

    public function periodeSuivante(): void
    {
        $ref = $this->reference();
        $this->dateRef = ($this->modeCalendrier === 'semaine' ? $ref->addWeek() : $ref->addMonthNoOverflow())->toDateString();
    }

    // Ajout rapide
    public string $titre = '';

    public string $type = Activite::TYPE_TACHE;

    public ?string $echeance = null;

    public ?string $contact_id = null;

    public function ajouter(): void
    {
        $donnees = $this->validate([
            'titre' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(array_keys(Activite::libellesTypes()))],
            'echeance' => ['nullable', 'date'],
            'contact_id' => ['nullable', 'exists:contacts,id'],
        ]);

        Activite::create($donnees);

        $this->reset('titre', 'echeance', 'contact_id');
        session()->flash('message', 'Activité ajoutée.');
    }

    public function basculer(int $id): void
    {
        Activite::findOrFail($id)->basculer();
    }

    public function supprimer(int $id): void
    {
        Activite::findOrFail($id)->delete();
        session()->flash('message', 'Activité supprimée.');
    }

    public function render(): View
    {
        // Requête de base : filtres statut + organisation (partagée liste / calendrier).
        $base = Activite::with(['contact', 'opportunite'])
            ->when($this->filtre === 'a_faire', fn ($q) => $q->aFaire())
            ->when($this->filtre === 'terminees', fn ($q) => $q->terminees())
            ->when($this->categorie !== '', fn ($q) => $q->whereHas(
                'contact',
                fn ($c) => $c->where('categorie_id', $this->categorie)
            ))
            ->when($this->categorie === '' && $this->pole !== '', fn ($q) => $q->whereHas(
                'contact',
                fn ($c) => $c->whereHas('categorie', fn ($cat) => $cat->where('pole_id', $this->pole))
            ));

        $activites = (clone $base)
            ->orderByRaw('echeance is null')
            ->orderBy('echeance')
            ->get();

        return view('livewire.activites.liste', [
            'activites' => $activites,
            'calendrier' => $this->vue === 'calendrier' ? $this->calendrier(clone $base) : null,
            'libelles' => Activite::libellesTypes(),
            'contacts' => Contact::actifs()->orderBy('nom')->get(['id', 'nom', 'prenom', 'type']),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }

    /**
     * Construit la grille du calendrier (mois ou semaine) à partir de la requête
     * filtrée : jours affichés + activités datées regroupées par jour.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<Activite>  $base
     * @return array{jours: array<int, array{date: Carbon, dansMois: bool, aujourdhui: bool, activites: \Illuminate\Support\Collection}>, libelle: string, sansDate: \Illuminate\Support\Collection}
     */
    private function calendrier($base): array
    {
        $ref = $this->reference();

        if ($this->modeCalendrier === 'semaine') {
            $debut = $ref->copy()->startOfWeek(Carbon::MONDAY);
            $fin = $ref->copy()->endOfWeek(Carbon::SUNDAY);
            $libelle = 'Semaine du '.$debut->translatedFormat('d').' au '.$fin->translatedFormat('d F Y');
        } else {
            $debut = $ref->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
            $fin = $ref->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);
            $libelle = ucfirst($ref->translatedFormat('F Y'));
        }

        $parJour = (clone $base)
            ->whereNotNull('echeance')
            ->whereBetween('echeance', [$debut->copy()->startOfDay(), $fin->copy()->endOfDay()])
            ->orderBy('echeance')
            ->get()
            ->groupBy(fn (Activite $a) => $a->echeance->toDateString());

        $jours = [];
        for ($jour = $debut->copy(); $jour->lte($fin); $jour->addDay()) {
            $jours[] = [
                'date' => $jour->copy(),
                'dansMois' => $this->modeCalendrier === 'semaine' || $jour->month === $ref->month,
                'aujourdhui' => $jour->isToday(),
                'activites' => $parJour->get($jour->toDateString(), collect()),
            ];
        }

        return [
            'jours' => $jours,
            'libelle' => $libelle,
            'sansDate' => (clone $base)->whereNull('echeance')->orderBy('created_at')->get(),
        ];
    }
}

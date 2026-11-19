<?php

namespace App\Livewire\Sessions;

use App\Models\Contenu;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Calendrier de contenu d'un trimestre (brique 020) : ce qui est prévu, prêt ou
 * à enregistrer. La publication se fait dans Telegram ; une publication marquée
 * #pratique le jour prévu passe ici en « Publié ».
 */
#[Layout('layouts.app')]
class Contenus extends Component
{
    public Seance $seance;

    public string $date_prevue = '';

    public string $format = 'vocal';

    public string $titre = '';

    public string $notes = '';

    /** Trimestre dont on copie le plan. */
    public string $source = '';

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
        $this->date_prevue = ($seance->date_debut && $seance->date_debut->isFuture() ? $seance->date_debut : now())->toDateString();
    }

    public function ajouter(): void
    {
        $donnees = $this->validate([
            'date_prevue' => ['required', 'date'],
            'format' => ['required', Rule::in(array_keys(Contenu::FORMATS))],
            'titre' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['titre.required' => 'Donne un titre à cette publication.']);

        $this->seance->contenus()->create($donnees + ['statut' => 'a_enregistrer']);

        // On garde la date et le format pour enchaîner ; la date avance d'un jour.
        $this->date_prevue = \Illuminate\Support\Carbon::parse($this->date_prevue)->addDay()->toDateString();
        $this->reset('titre', 'notes');
    }

    public function changer(int $id, string $champ, string $valeur): void
    {
        $contenu = $this->seance->contenus()->find($id);
        if (! $contenu) {
            return;
        }

        $valeur = trim($valeur);
        $valide = match ($champ) {
            'statut' => array_key_exists($valeur, Contenu::STATUTS),
            'format' => array_key_exists($valeur, Contenu::FORMATS),
            'titre' => $valeur !== '' && mb_strlen($valeur) <= 255,
            'date_prevue' => strtotime($valeur) !== false,
            'notes' => mb_strlen($valeur) <= 5000,
            default => false,
        };

        if ($valide) {
            $contenu->update([$champ => $valeur !== '' ? $valeur : null]);
        }
    }

    public function supprimer(int $id): void
    {
        $this->seance->contenus()->whereKey($id)->delete();
    }

    /**
     * Copie le plan d'un autre trimestre, décalé du même nombre de jours que les
     * deux débuts. Ce qui avait été enregistré revient en « Prêt » (à republier).
     */
    public function copierPlan(): void
    {
        $source = Seance::with('contenus')->find((int) $this->source);
        if (! $source || $source->id === $this->seance->id || $source->contenus->isEmpty()) {
            return;
        }

        $decalage = ($source->date_debut && $this->seance->date_debut)
            ? (int) $source->date_debut->copy()->startOfDay()->diffInDays($this->seance->date_debut->copy()->startOfDay(), false)
            : 0;

        foreach ($source->contenus as $contenu) {
            $this->seance->contenus()->create([
                'date_prevue' => $contenu->date_prevue->copy()->addDays($decalage),
                'format' => $contenu->format,
                'titre' => $contenu->titre,
                'notes' => $contenu->notes,
                'statut' => in_array($contenu->statut, ['pret', 'programme', 'publie'], true) ? 'pret' : $contenu->statut,
            ]);
        }

        $this->reset('source');
        session()->flash('message', $source->contenus->count().' publication(s) copiée(s) depuis « '.$source->titre.' ».');
    }

    public function render(): View
    {
        $contenus = $this->seance->contenus()->with('pratique')->get();
        $faites = \App\Models\PratiqueFaite::query()
            ->whereIn('pratique_telegram_id', $contenus->pluck('pratique_telegram_id')->filter())
            ->selectRaw('pratique_telegram_id, count(*) as n')->groupBy('pratique_telegram_id')
            ->pluck('n', 'pratique_telegram_id');

        return view('livewire.sessions.contenus', [
            'semaines' => $contenus->groupBy(fn (Contenu $c) => $c->date_prevue->copy()->startOfWeek()->toDateString()),
            'faites' => $faites,
            'compteurs' => collect(Contenu::STATUTS)->map(fn ($l, $cle) => $contenus->where('statut', $cle)->count()),
            'aPreparer' => $contenus->filter(fn (Contenu $c) => in_array($c->statut, Contenu::A_PREPARER, true)
                && $c->date_prevue->lte(now()->addDays(7)))->count(),
            'sources' => Seance::whereKeyNot($this->seance->id)->whereHas('contenus')->orderByDesc('date_debut')->get(['id', 'titre', 'date_debut']),
        ]);
    }
}

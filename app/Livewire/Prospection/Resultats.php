<?php

namespace App\Livewire\Prospection;

use App\Models\CandidatProspection;
use App\Models\RechercheProspection;
use App\Services\Prospection\AnalyseurCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Resultats extends Component
{
    public RechercheProspection $recherche;

    /** CSV collé depuis ChatGPT. */
    public string $csv = '';

    /** Dernier rapport d'analyse (total, ignorés, en-têtes inconnues). */
    public array $rapport = [];

    public function mount(RechercheProspection $recherche): void
    {
        $this->recherche = $recherche;
    }

    /**
     * Analyse le CSV collé et remplace les candidats de cette recherche.
     * (Idempotent : recoller le même CSV ne crée pas de doublons.)
     */
    public function analyser(): void
    {
        $this->validate([
            'csv' => ['required', 'string', 'min:10'],
        ], [
            'csv.required' => 'Colle d’abord le CSV renvoyé par ChatGPT.',
            'csv.min' => 'Le contenu collé est trop court pour être un CSV.',
        ]);

        $resultat = app(AnalyseurCsv::class)->analyser($this->csv);

        DB::transaction(function () use ($resultat) {
            $this->recherche->candidats()->delete();

            foreach ($resultat['candidats'] as $donnees) {
                $email = $donnees['email'] ?? null;

                CandidatProspection::create([
                    'recherche_prospection_id' => $this->recherche->id,
                    'donnees' => $donnees,
                    'nom' => $donnees['nom'] ?? null,
                    'email' => filled($email) ? mb_strtolower(trim($email)) : null,
                    'telephone' => $donnees['telephone'] ?? null,
                    'url_profil' => $donnees['url_profil'] ?? null,
                    'origine' => $donnees['origine'] ?? null,
                ]);
            }
        });

        $this->rapport = [
            'total' => $resultat['total'],
            'ignores' => $resultat['ignores'],
            'entetes_inconnues' => $resultat['entetes_inconnues'],
        ];

        session()->flash('message', $resultat['total'].' candidat(s) importé(s) dans la zone de préparation.');
    }

    /** Vide les candidats de cette recherche. */
    public function vider(): void
    {
        $this->recherche->candidats()->delete();
        $this->rapport = [];
        session()->flash('message', 'Candidats effacés.');
    }

    public function render(): View
    {
        return view('livewire.prospection.resultats', [
            'candidats' => $this->recherche->candidats()->orderBy('nom')->get(),
        ]);
    }
}

<?php

namespace App\Livewire\Prospection;

use App\Models\CandidatProspection;
use App\Models\RechercheProspection;
use App\Services\Prospection\AnalyseurCsv;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Resultats extends Component
{
    use WithFileUploads;

    public RechercheProspection $recherche;

    /** CSV collé depuis ChatGPT. */
    public string $csv = '';

    /** Fichier .csv déposé (alternative au collage, encodage plus fiable). */
    public ?TemporaryUploadedFile $fichier = null;

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
            'csv.required' => 'Colle d’abord le CSV renvoyé par ChatGPT (ou dépose le fichier).',
            'csv.min' => 'Le contenu collé est trop court pour être un CSV.',
        ]);

        $this->ingerer($this->csv);
    }

    /** Analyse le fichier .csv déposé et remplace les candidats de cette recherche. */
    public function analyserFichier(): void
    {
        $this->validate([
            'fichier' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ], [
            'fichier.required' => 'Dépose le fichier .csv renvoyé par ChatGPT.',
        ]);

        $contenu = (string) file_get_contents($this->fichier->getRealPath());
        $this->ingerer($contenu);
        $this->reset('fichier');
    }

    /** Analyse un contenu CSV et remplace les candidats (commun collage/fichier). */
    private function ingerer(string $contenu): void
    {
        $resultat = app(AnalyseurCsv::class)->analyser($contenu);

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

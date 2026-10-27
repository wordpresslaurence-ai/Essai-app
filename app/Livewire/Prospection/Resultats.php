<?php

namespace App\Livewire\Prospection;

use App\Livewire\Contacts\ImportCsv;
use App\Models\CandidatProspection;
use App\Models\RechercheProspection;
use App\Services\Prospection\AnalyseurCsv;
use App\Services\Prospection\Dedoublonnage;
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

    /** @var array<int, string> Candidats cochés (pour l'import, tranche 4). */
    public array $selection = [];

    /** Afficher aussi les candidats écartés. */
    public bool $voirEcartes = false;

    /** Candidat dont on affiche la fiche détaillée (40 champs). */
    public ?int $detailId = null;

    public function mount(RechercheProspection $recherche): void
    {
        $this->recherche = $recherche;
    }

    /** Coche / décoche tous les candidats actifs affichés. */
    public function basculerTout(): void
    {
        $ids = $this->recherche->candidats()->actifs()->pluck('id')->all();
        $this->selection = count($this->selection) >= count($ids) && $ids !== []
            ? []
            : array_map('strval', $ids);
    }

    /** Écarte un candidat pour cette recherche (reste en historique). */
    public function ecarter(int $id, ?string $motif = null): void
    {
        $this->recherche->candidats()->whereKey($id)->update(['ecarte' => true, 'motif_ecart' => $motif]);
        $this->selection = array_values(array_diff($this->selection, [(string) $id]));
    }

    /** Remet un candidat écarté dans la liste. */
    public function restaurer(int $id): void
    {
        $this->recherche->candidats()->whereKey($id)->update(['ecarte' => false, 'motif_ecart' => null]);
    }

    public function voirDetail(int $id): void
    {
        $this->detailId = $id;
    }

    public function fermerDetail(): void
    {
        $this->detailId = null;
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
        $this->selection = [];

        session()->flash('message', $resultat['total'].' candidat(s) importé(s) dans la zone de préparation.');
    }

    /** Vide les candidats de cette recherche. */
    public function vider(): void
    {
        $this->recherche->candidats()->delete();
        $this->rapport = [];
        $this->selection = [];
        session()->flash('message', 'Candidats effacés.');
    }

    public function render(): View
    {
        $candidats = $this->recherche->candidats()
            ->when(! $this->voirEcartes, fn ($q) => $q->actifs())
            ->orderBy('nom')
            ->get();

        $detail = $this->detailId
            ? $candidats->firstWhere('id', $this->detailId)
                ?? $this->recherche->candidats()->whereKey($this->detailId)->first()
            : null;

        return view('livewire.prospection.resultats', [
            'candidats' => $candidats,
            'doublons' => app(Dedoublonnage::class)->pour($candidats),
            'nbActifs' => $this->recherche->candidats()->actifs()->count(),
            'nbEcartes' => $this->recherche->candidats()->where('ecarte', true)->count(),
            'labels' => (new ImportCsv)->champsDisponibles(),
            'detail' => $detail,
        ]);
    }
}

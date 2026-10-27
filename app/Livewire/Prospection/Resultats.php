<?php

namespace App\Livewire\Prospection;

use App\Livewire\Contacts\ImportCsv;
use App\Models\CandidatProspection;
use App\Models\RechercheProspection;
use App\Services\Prospection\AnalyseurCsv;
use App\Services\Prospection\Dedoublonnage;
use App\Services\Prospection\ImporteurCandidats;
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

    /** Étape de confirmation d'import (récapitulatif avant écriture). */
    public bool $confirmation = false;

    /** Aperçu de l'import (créations / compléments). */
    public array $apercuImport = [];

    /** Rapport après import. */
    public array $rapportImport = [];

    public function mount(RechercheProspection $recherche): void
    {
        $this->recherche = $recherche;
    }

    /** Coche / décoche tous les candidats actifs NON importés. */
    public function basculerTout(): void
    {
        $ids = $this->recherche->candidats()->actifs()->whereNull('importe_at')->pluck('id')->all();
        $this->selection = count($this->selection) >= count($ids) && $ids !== []
            ? []
            : array_map('strval', $ids);
    }

    /** Prépare le récapitulatif avant import (créations vs fiches à compléter). */
    public function preparerImport(?array $ids = null): void
    {
        if ($ids !== null) {
            $this->selection = array_values(array_map('strval', $ids));
        }
        $this->rapportImport = [];
        if ($this->selection === []) {
            return;
        }

        $selectionnes = $this->recherche->candidats()->actifs()
            ->whereNull('importe_at')
            ->whereIn('id', $this->selection)
            ->get();

        $dedup = app(Dedoublonnage::class)->pour($selectionnes);
        $aCompleter = collect($dedup)->where('type', 'crm')->count();

        $this->apercuImport = [
            'total' => $selectionnes->count(),
            'nouveaux' => $selectionnes->count() - $aCompleter,
            'a_completer' => $aCompleter,
        ];
        $this->confirmation = true;
    }

    public function annulerImport(): void
    {
        $this->confirmation = false;
    }

    /** Écrit les contacts sélectionnés (création ou complément non destructif). */
    public function confirmerImport(): void
    {
        if ($this->selection === []) {
            $this->confirmation = false;

            return;
        }

        $this->rapportImport = app(ImporteurCandidats::class)->importer($this->recherche, $this->selection);
        $this->selection = [];
        $this->confirmation = false;
        $this->dispatch('selection-videe'); // réinitialise la sélection côté navigateur

        $r = $this->rapportImport;
        session()->flash('message', $r['crees'].' créé(s), '.$r['completes'].' complété(s)'.($r['ignores'] ? ', '.$r['ignores'].' ignoré(s)' : '').'.');
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

        // Ids cochables côté client (actifs, non importés).
        $idsSelectionnables = $candidats
            ->filter(fn ($c) => ! $c->ecarte && ! $c->estImporte())
            ->map(fn ($c) => (string) $c->id)
            ->values()
            ->all();

        return view('livewire.prospection.resultats', [
            'candidats' => $candidats,
            'doublons' => app(Dedoublonnage::class)->pour($candidats),
            'idsSelectionnables' => $idsSelectionnables,
            'nbEcartes' => $this->recherche->candidats()->where('ecarte', true)->count(),
            'labels' => (new ImportCsv)->champsDisponibles(),
            'detail' => $detail,
        ]);
    }
}

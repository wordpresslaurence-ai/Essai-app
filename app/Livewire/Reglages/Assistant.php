<?php

namespace App\Livewire\Reglages;

use App\Models\Categorie;
use App\Models\Parametre;
use App\Models\Pole;
use App\Services\Email\SynchronisationEmails;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

/**
 * Réglages de l'assistant : connexion e-mail, clé API Claude, liens Fluent Booking
 * et délai de relance. Les secrets ne sont jamais réaffichés en clair ; on ne les
 * remplace que si un nouveau contenu est saisi.
 */
#[Layout('layouts.app')]
class Assistant extends Component
{
    // Champs non secrets
    public ?string $anthropic_modele = null;

    public int $relance_delai_jours = 5;

    public ?string $lien_appel_visio = null;

    public ?string $lien_seance_decouverte = null;

    public ?string $lien_journee_aura = null;

    public ?string $pitch_prospection = null;

    /** Description de l'offre par catégorie : [categorie_id => texte]. */
    public array $offres = [];

    /** Liens par catégorie : [categorie_id => [ ['libelle' => , 'url' => ], … ] ]. */
    public array $liens = [];

    // Secrets : champs de saisie (vides au chargement), remplacés uniquement si saisis
    public string $anthropic_api_key = '';

    // Indique si un secret est déjà enregistré (pour l'affichage)
    public bool $cleApiDefinie = false;

    // Résultat du dernier test de connexion (affiché dans la vue)
    public ?string $testMessage = null;

    public ?string $testErreur = null;

    public function mount(): void
    {
        $p = Parametre::instance();

        $this->anthropic_modele = $p->anthropic_modele;
        $this->relance_delai_jours = $p->relance_delai_jours ?? 5;

        // Pré-remplissage des liens (valeurs connues) si vides
        $this->lien_appel_visio = $p->lien_appel_visio
            ?? 'https://homeharmony.be/?fluent-booking=calendar&host=siteweb&event=appel-visio1';
        $this->lien_seance_decouverte = $p->lien_seance_decouverte
            ?? 'https://homeharmony.be/seance-prana-gratuite/';
        $this->lien_journee_aura = $p->lien_journee_aura
            ?? 'https://homeharmony.be/nettoyage-aura-chakras';
        $this->pitch_prospection = $p->pitch_prospection;

        foreach (Categorie::all() as $cat) {
            $this->offres[$cat->id] = $cat->offre;
            $this->liens[$cat->id] = array_values($cat->liens ?? []);
        }

        $this->cleApiDefinie = filled($p->secret('anthropic_api_key'));
    }

    public function enregistrer(): void
    {
        $donnees = $this->validate([
            'anthropic_modele' => ['nullable', 'string', 'max:100'],
            'relance_delai_jours' => ['required', 'integer', 'min:1', 'max:365'],
            'lien_appel_visio' => ['nullable', 'url', 'max:255'],
            'lien_seance_decouverte' => ['nullable', 'url', 'max:255'],
            'lien_journee_aura' => ['nullable', 'url', 'max:255'],
            'pitch_prospection' => ['nullable', 'string'],
            'offres' => ['array'],
            'offres.*' => ['nullable', 'string'],
            'liens' => ['array'],
            'liens.*' => ['array'],
            'liens.*.*.libelle' => ['nullable', 'string', 'max:255'],
            'liens.*.*.url' => ['nullable', 'string', 'max:2000'],
            'anthropic_api_key' => ['nullable', 'string', 'max:255'],
        ]);

        $p = Parametre::instance();

        $p->anthropic_modele = $donnees['anthropic_modele'];
        $p->relance_delai_jours = $donnees['relance_delai_jours'];
        $p->lien_appel_visio = $donnees['lien_appel_visio'];
        $p->lien_seance_decouverte = $donnees['lien_seance_decouverte'];
        $p->lien_journee_aura = $donnees['lien_journee_aura'];
        $p->pitch_prospection = $donnees['pitch_prospection'];

        // Offre & liens par catégorie
        foreach ($this->offres as $categorieId => $description) {
            $liensCat = collect($this->liens[$categorieId] ?? [])
                ->filter(fn ($l) => filled($l['url'] ?? null))
                ->map(fn ($l) => [
                    'libelle' => trim((string) ($l['libelle'] ?? '')),
                    'url' => trim((string) $l['url']),
                ])
                ->values()
                ->all();

            Categorie::whereKey($categorieId)->update([
                'offre' => filled($description) ? $description : null,
                'liens' => $liensCat !== [] ? $liensCat : null,
            ]);
        }

        // Secret : on ne remplace que si un nouveau contenu a été saisi
        if (filled($this->anthropic_api_key)) {
            $p->anthropic_api_key = $this->anthropic_api_key;
        }

        $p->save();

        // On vide le champ secret et on rafraîchit l'indicateur
        $this->reset('anthropic_api_key');
        $this->cleApiDefinie = filled($p->secret('anthropic_api_key'));

        session()->flash('message', 'Réglages enregistrés.');
    }

    /** Ajoute une ligne de lien (vide) à une catégorie. */
    public function ajouterLien(int $categorieId): void
    {
        $this->liens[$categorieId][] = ['libelle' => '', 'url' => ''];
    }

    /** Retire une ligne de lien d'une catégorie. */
    public function supprimerLien(int $categorieId, int $index): void
    {
        if (isset($this->liens[$categorieId][$index])) {
            array_splice($this->liens[$categorieId], $index, 1);
        }
    }

    /** Lance la synchronisation des e-mails de toutes les boîtes actives. */
    public function synchroniser(): void
    {
        $this->reset('testMessage', 'testErreur');

        try {
            $resultat = app(SynchronisationEmails::class)->executer();
            $this->testMessage = $resultat->resume();
        } catch (Throwable $e) {
            $this->testErreur = $e->getMessage();
        }
    }

    public function render(): View
    {
        return view('livewire.reglages.assistant', [
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

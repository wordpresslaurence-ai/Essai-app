<?php

namespace App\Livewire\Contacts;

use App\Models\Activite;
use App\Models\Contact;
use App\Services\Debrief\AnalyseurDebrief;
use App\Services\Debrief\GenerateurPromptDebrief;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Débrief d'appel (Option conversationnelle, 0 € d'API) :
 *  1. le CRM affiche un prompt pré-rempli à coller dans ChatGPT avec la
 *     transcription tl;dv ;
 *  2. on recolle le débrief structuré → le CRM l'analyse et propose un aperçu ;
 *  3. après validation : une note d'historique sur la fiche, la mise à jour
 *     éventuelle de la capacité / du tarif, et une tâche de relance.
 */
#[Layout('layouts.app')]
class DebriefAppel extends Component
{
    public Contact $contact;

    /** Prompt pré-rempli à copier dans ChatGPT. */
    public string $prompt = '';

    /** Débrief structuré recollé par l'utilisatrice. */
    public string $contenu = '';

    /** Passe à true une fois le débrief analysé (affiche l'aperçu). */
    public bool $analyse = false;

    public ?string $erreur = null;

    // --- Champs éditables de l'aperçu (validés avant écriture) ---------------
    public string $noteTexte = '';

    public ?int $capacite = null;

    public string $tarif = '';

    public bool $majFiche = false;

    /** @var list<array{titre:string, echeance:?string, creer:bool}> Tâches de relance proposées. */
    public array $taches = [];

    public ?int $opportuniteId = null;

    public function mount(Contact $contact): void
    {
        $this->contact = $contact;
        $this->prompt = app(GenerateurPromptDebrief::class)->generer($contact);

        // Rattache par défaut à l'unique opportunité ouverte, s'il y en a une.
        $ouvertes = $contact->opportunites()->whereNull('date_cloture')->pluck('id');
        if ($ouvertes->count() === 1) {
            $this->opportuniteId = (int) $ouvertes->first();
        }
    }

    public function analyserDebrief(): void
    {
        $this->erreur = null;

        if (trim($this->contenu) === '') {
            $this->erreur = 'Colle d’abord le débrief renvoyé par ChatGPT.';

            return;
        }

        $r = app(AnalyseurDebrief::class)->analyser($this->contenu);

        if ($r->estVide()) {
            $this->erreur = 'Rien n’a pu être lu. Vérifie que tu as bien collé le débrief au format attendu.';

            return;
        }

        $this->noteTexte = $r->texteComplet;
        $this->capacite = $r->capacite;
        $this->tarif = $r->tarif ?? '';
        $this->majFiche = $r->capacite !== null || ($r->tarif ?? '') !== '';

        // Une tâche de relance proposée par action (toutes cochées par défaut),
        // avec l'échéance du débrief pré-remplie — modifiable ligne par ligne.
        $echeance = $r->echeance?->format('Y-m-d');
        $this->taches = [];
        foreach ($r->prochainesActions as $action) {
            $this->taches[] = ['titre' => Str::limit($action, 200, ''), 'echeance' => $echeance, 'creer' => true];
        }

        $this->analyse = true;
    }

    /** Ajoute une ligne de tâche vide (saisie manuelle). */
    public function ajouterTache(): void
    {
        $this->taches[] = ['titre' => '', 'echeance' => null, 'creer' => true];
    }

    public function retirerTache(int $index): void
    {
        unset($this->taches[$index]);
        $this->taches = array_values($this->taches);
    }

    public function enregistrer()
    {
        $this->validate([
            'noteTexte' => ['required', 'string', 'min:3'],
            'capacite' => ['nullable', 'integer', 'min:1'],
            'tarif' => ['nullable', 'string', 'max:255'],
            'taches' => ['array'],
            'taches.*.titre' => ['nullable', 'string', 'max:255'],
            'taches.*.echeance' => ['nullable', 'date'],
            'taches.*.creer' => ['boolean'],
            'opportuniteId' => ['nullable', 'integer'],
        ]);

        // Sécurité : l'opportunité choisie doit bien appartenir à ce contact.
        $oppId = $this->opportuniteId
            && $this->contact->opportunites()->whereKey($this->opportuniteId)->exists()
            ? $this->opportuniteId
            : null;

        DB::transaction(function () use ($oppId) {
            Activite::create([
                'titre' => 'Débrief d’appel — '.now()->translatedFormat('d/m/Y'),
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $this->contact->id,
                'opportunite_id' => $oppId,
                'notes' => $this->noteTexte,
                'terminee_at' => now(),
            ]);

            if ($this->majFiche) {
                $maj = [];
                if ($this->capacite !== null) {
                    $maj['capacite'] = $this->capacite;
                }
                if (trim($this->tarif) !== '') {
                    $maj['tarif'] = trim($this->tarif);
                }
                if ($maj !== []) {
                    $this->contact->update($maj);
                }
            }

            foreach ($this->taches as $t) {
                if (empty($t['creer']) || trim((string) ($t['titre'] ?? '')) === '') {
                    continue;
                }
                Activite::create([
                    'titre' => trim((string) $t['titre']),
                    'type' => Activite::TYPE_TACHE,
                    'contact_id' => $this->contact->id,
                    'opportunite_id' => $oppId,
                    'echeance' => ! empty($t['echeance']) ? Carbon::parse($t['echeance'])->startOfDay() : null,
                ]);
            }
        });

        session()->flash('debrief_ok', 'Débrief enregistré sur la fiche.');

        return $this->redirectRoute('contacts.fiche', $this->contact, navigate: true);
    }

    public function render(): View
    {
        return view('livewire.contacts.debrief-appel', [
            'opportunites' => $this->contact->opportunites()->orderByDesc('created_at')->get(),
        ]);
    }
}

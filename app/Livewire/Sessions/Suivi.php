<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\Seance;
use App\Services\Sessions\SuiviApresSeance;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Suivi après une séance (brique 014) : noter qui était présent, puis envoyer à
 * chacun le message qui convient (merci / prochaine date). Le CRM n'envoie rien.
 */
#[Layout('layouts.app')]
class Suivi extends Component
{
    public Seance $seance;

    public ?int $ouverteId = null;

    public string $brouillonObjet = '';

    public string $brouillonCorps = '';

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;

        // Les tâches de suivi des séances passées récentes sont créées au passage.
        app(SuiviApresSeance::class)->creerTaches(auth()->user()?->name);
    }

    /** Inscription confirmée de CETTE session. */
    protected function inscription(int $id): ?Inscription
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.lieuContact', 'contact.entreprise'])
            ->find($id);
    }

    /** @return Collection<int, Inscription> */
    protected function inscriptions(): Collection
    {
        return $this->seance->inscriptions()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->with(['seance.lieuContact', 'contact.entreprise'])
            ->get()
            ->sortBy(fn (Inscription $i) => mb_strtolower($i->contact->nomComplet()))
            ->values();
    }

    /** Note la présence ; cliquer à nouveau sur le même choix l'efface. */
    public function noterPresence(int $id, string $presence): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription || ! in_array($presence, [Inscription::PRESENT, Inscription::ABSENT], true)) {
            return;
        }

        $inscription->update(['presence' => $inscription->presence === $presence ? null : $presence]);

        // Le message dépend de la présence : la tâche ouverte et le brouillon suivent.
        app(SuiviApresSeance::class)->rafraichirTache($inscription, auth()->user()?->name);

        if ($this->ouverteId === $inscription->id) {
            $this->preparer($inscription->id);
        }
    }

    public function toutPresent(): void
    {
        foreach ($this->inscriptions()->whereNull('presence') as $inscription) {
            $inscription->update(['presence' => Inscription::PRESENT]);
            app(SuiviApresSeance::class)->rafraichirTache($inscription, auth()->user()?->name);
        }
    }

    public function preparer(int $id): void
    {
        $inscription = $this->inscription($id);
        if (! $inscription) {
            return;
        }

        $texte = app(SuiviApresSeance::class)->texte($inscription, auth()->user()?->name);

        $this->ouverteId = $inscription->id;
        $this->brouillonObjet = $texte['objet'];
        $this->brouillonCorps = $texte['corps'];
    }

    public function fermer(): void
    {
        $this->reset(['ouverteId', 'brouillonObjet', 'brouillonCorps']);
    }

    /** Le message est parti : date, trace sur la fiche, tâche clôturée, personne suivante. */
    public function marquerEnvoye(): void
    {
        $inscription = $this->ouverteId ? $this->inscription($this->ouverteId) : null;
        if (! $inscription) {
            return;
        }

        $inscription->update(['suivi_envoye_le' => now(), 'suivi_cree_le' => $inscription->suivi_cree_le ?? now()]);

        Activite::create([
            'titre' => 'Suivi envoyé : '.$this->seance->titre,
            'type' => Activite::TYPE_EMAIL,
            'contact_id' => $inscription->contact_id,
            'terminee_at' => now(),
            'notes' => trim((filled($this->brouillonObjet) ? 'Objet : '.$this->brouillonObjet."\n\n" : '').$this->brouillonCorps),
        ]);

        app(SuiviApresSeance::class)->cloturerTache($inscription);

        session()->flash('message', 'Suivi noté pour '.$inscription->contact->nomComplet().'.');

        $suivante = $this->inscriptions()->first(fn (Inscription $i) => $i->suivi_envoye_le === null);
        $suivante ? $this->preparer($suivante->id) : $this->fermer();
    }

    public function render(): View
    {
        $inscriptions = $this->inscriptions();

        return view('livewire.sessions.suivi', [
            'inscriptions' => $inscriptions,
            'prochaine' => app(SuiviApresSeance::class)->prochaine($this->seance),
            'compteurs' => [
                'presents' => $inscriptions->where('presence', Inscription::PRESENT)->count(),
                'absents' => $inscriptions->where('presence', Inscription::ABSENT)->count(),
                'non_notes' => $inscriptions->whereNull('presence')->count(),
                'envoyes' => $inscriptions->whereNotNull('suivi_envoye_le')->count(),
            ],
        ]);
    }
}

<?php

namespace App\Livewire\Sessions;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Etiquette;
use App\Models\Inscription;
use App\Models\Invitation;
use App\Models\ModeleMessage;
use App\Models\Pole;
use App\Models\Seance;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Campagne d'invitations à une session (brique 012) : choisir les contacts,
 * préparer un message personnalisé par personne, suivre qui a été invité,
 * relancé, a répondu ou s'est inscrit. Le CRM n'envoie rien lui-même.
 */
#[Layout('layouts.app')]
class Invitations extends Component
{
    /** Nombre de contacts proposés à l'ajout par écran. */
    public const LIMITE_CANDIDATS = 50;

    public Seance $seance;

    // --- Choix des contacts à ajouter ---------------------------------------

    public string $recherche = '';

    public string $type = '';

    public string $etiquette = '';

    public string $pole = '';

    public string $categorie = '';

    public string $pays = '';

    /** @var array<int, string> Identifiants des contacts cochés. */
    public array $selection = [];

    // --- Suivi de la campagne -----------------------------------------------

    /** tous | a_inviter | invites | a_relancer | repondu */
    public string $filtre = 'tous';

    // --- Préparation d'un message -------------------------------------------

    public ?int $ouverteId = null;

    public ?string $modeleId = null;

    public string $brouillonObjet = '';

    public string $brouillonCorps = '';

    public function mount(Seance $seance): void
    {
        $this->seance = $seance;
    }

    public function updated(string $name): void
    {
        // Un changement de filtre invalide les cases cochées (elles ne sont plus affichées).
        if (in_array($name, ['recherche', 'type', 'etiquette', 'pole', 'categorie', 'pays'], true)) {
            $this->selection = [];
        }

        if ($name === 'pole') {
            $this->categorie = '';
        }

        if ($name === 'modeleId') {
            $this->rendreBrouillon();
        }
    }

    // --- Contacts candidats --------------------------------------------------

    /**
     * Contacts que l'on peut encore ajouter : actifs, contactables, pas déjà
     * dans la campagne, et correspondant aux filtres.
     *
     * @return Builder<Contact>
     */
    protected function candidats(): Builder
    {
        $pays = match ($this->pays) {
            'BE' => ['be', 'belgique', 'belgium', 'belgië'],
            'FR' => ['fr', 'france'],
            default => [],
        };

        return Contact::query()
            ->actifs()
            ->where('ne_plus_contacter', false)
            ->whereNotIn('id', $this->seance->invitations()->select('contact_id'))
            ->recherche($this->recherche)
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->etiquette !== '', fn ($q) => $q->whereHas(
                'etiquettes',
                fn ($e) => $e->where('etiquettes.id', $this->etiquette)
            ))
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', $this->categorie))
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            ->when($pays !== [], fn ($q) => $q->where(function ($sub) use ($pays) {
                foreach ($pays as $valeur) {
                    $sub->orWhereRaw('LOWER(TRIM(adresse_pays)) = ?', [$valeur]);
                }
            }));
    }

    /** Coche tous les contacts affichés, ou décoche tout s'ils le sont déjà. */
    public function basculerTout(): void
    {
        $affiches = $this->candidats()->orderBy('nom')->orderBy('prenom')
            ->limit(self::LIMITE_CANDIDATS)->pluck('id')
            ->map(fn ($id) => (string) $id)->all();

        $this->selection = count(array_diff($affiches, $this->selection)) === 0 ? [] : $affiches;
    }

    public function ajouterSelection(): void
    {
        if ($this->selection === []) {
            return;
        }

        $ids = $this->candidats()->whereIn('id', $this->selection)->pluck('id');

        foreach ($ids as $id) {
            Invitation::firstOrCreate(
                ['seance_id' => $this->seance->id, 'contact_id' => $id],
                ['statut' => Invitation::STATUT_A_INVITER],
            );
        }

        $this->selection = [];
        session()->flash('message', $ids->count().' contact(s) ajouté(s) à la campagne.');
    }

    // --- Préparation du message ----------------------------------------------

    /** Invitation de CETTE session (jamais celle d'une autre). */
    protected function invitation(int $id): ?Invitation
    {
        return $this->seance->invitations()->with('contact.entreprise')->find($id);
    }

    public function preparer(int $id): void
    {
        $invitation = $this->invitation($id);
        if (! $invitation) {
            return;
        }

        $this->ouverteId = $invitation->id;

        // Modèle proposé : celui déjà choisi dans cet écran, sinon le dernier
        // utilisé pour cette invitation, sinon le premier de la liste.
        if (! $this->modeleId || ! ModeleMessage::whereKey($this->modeleId)->exists()) {
            $this->modeleId = (string) ($invitation->modele_message_id
                ?: ModeleMessage::orderBy('nom')->value('id')) ?: null;
        }

        $this->rendreBrouillon();
    }

    /** Recalcule le brouillon à partir du modèle choisi et de l'invitation ouverte. */
    protected function rendreBrouillon(): void
    {
        $this->brouillonObjet = '';
        $this->brouillonCorps = '';

        $invitation = $this->ouverteId ? $this->invitation($this->ouverteId) : null;
        $modele = $this->modeleId ? ModeleMessage::find($this->modeleId) : null;

        if (! $invitation || ! $modele) {
            return;
        }

        $rendu = $modele->rendre($invitation->contact, $this->seance, auth()->user()?->name);
        $this->brouillonObjet = $rendu['objet'];
        $this->brouillonCorps = $rendu['corps'];
    }

    public function fermer(): void
    {
        $this->reset(['ouverteId', 'brouillonObjet', 'brouillonCorps']);
    }

    /**
     * Le message vient d'être envoyé (par Laurence, hors CRM) : on date l'envoi,
     * on garde une trace sur la fiche du contact, puis on ouvre l'invitation suivante.
     */
    public function marquerEnvoyee(): void
    {
        $invitation = $this->ouverteId ? $this->invitation($this->ouverteId) : null;
        if (! $invitation) {
            return;
        }

        $estRelance = $invitation->statut !== Invitation::STATUT_A_INVITER;

        $maj = ['modele_message_id' => $this->modeleId ?: null];
        if ($invitation->statut === Invitation::STATUT_A_INVITER) {
            $maj += ['statut' => Invitation::STATUT_INVITEE, 'invitee_le' => now()];
        } elseif (in_array($invitation->statut, [Invitation::STATUT_INVITEE, Invitation::STATUT_RELANCEE], true)) {
            $maj += ['statut' => Invitation::STATUT_RELANCEE, 'relancee_le' => now()];
        }
        $invitation->update($maj);

        Activite::create([
            'titre' => ($estRelance ? 'Relance envoyée : ' : 'Invitation envoyée : ').$this->seance->titre,
            'type' => Activite::TYPE_EMAIL,
            'contact_id' => $invitation->contact_id,
            'terminee_at' => now(),
            'notes' => trim((filled($this->brouillonObjet) ? 'Objet : '.$this->brouillonObjet."\n\n" : '').$this->brouillonCorps),
        ]);

        session()->flash('message', ($estRelance ? 'Relance notée pour ' : 'Invitation notée pour ').$invitation->contact->nomComplet().'.');

        // Enchaîne sur la prochaine personne à inviter, s'il en reste.
        $suivante = $this->seance->invitations()
            ->where('statut', Invitation::STATUT_A_INVITER)
            ->orderBy('id')
            ->value('id');

        if ($suivante && ! $estRelance) {
            $this->preparer($suivante);
        } else {
            $this->fermer();
        }
    }

    // --- Suivi ---------------------------------------------------------------

    public function changerStatut(int $id, string $statut): void
    {
        $invitation = $this->invitation($id);
        if (! $invitation || ! array_key_exists($statut, Invitation::STATUTS)) {
            return;
        }

        $invitation->update(['statut' => $statut]);
    }

    public function inscrire(int $id): void
    {
        $invitation = $this->invitation($id);
        if (! $invitation) {
            return;
        }

        $inscription = $this->seance->inscrire($invitation->contact);

        session()->flash('message', $invitation->contact->nomComplet().($inscription->estEnAttente()
            ? ' est en liste d’attente (session complète).'
            : ' est inscrit(e) à la session.'));
    }

    public function retirer(int $id): void
    {
        $invitation = $this->invitation($id);
        if (! $invitation) {
            return;
        }

        if ($this->ouverteId === $invitation->id) {
            $this->fermer();
        }

        $invitation->delete();
        session()->flash('message', 'Contact retiré de la campagne.');
    }

    public function render(): View
    {
        $this->seance->load('lieuContact');

        $inscrits = $this->seance->inscriptions()
            ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
            ->pluck('contact_id')->map(fn ($id) => (int) $id)->all();

        $toutes = $this->seance->invitations()->with('contact.entreprise')->get()
            ->sortBy(fn (Invitation $i) => mb_strtolower($i->contact->nomComplet()))
            ->values();

        $estInscrit = fn (Invitation $i) => in_array($i->contact_id, $inscrits, true);
        $aRelancer = fn (Invitation $i) => $i->estARelancer() && ! $estInscrit($i);

        $compteurs = [
            'a_inviter' => $toutes->filter(fn ($i) => $i->statut === Invitation::STATUT_A_INVITER && ! $estInscrit($i))->count(),
            'invites' => $toutes->whereIn('statut', [Invitation::STATUT_INVITEE, Invitation::STATUT_RELANCEE])->count(),
            'a_relancer' => $toutes->filter($aRelancer)->count(),
            'repondu' => $toutes->whereIn('statut', [Invitation::STATUT_A_REPONDU, Invitation::STATUT_DECLINEE])->count(),
            'inscrits' => $toutes->filter($estInscrit)->count(),
        ];

        $invitations = match ($this->filtre) {
            'a_inviter' => $toutes->filter(fn ($i) => $i->statut === Invitation::STATUT_A_INVITER && ! $estInscrit($i)),
            'invites' => $toutes->whereIn('statut', [Invitation::STATUT_INVITEE, Invitation::STATUT_RELANCEE]),
            'a_relancer' => $toutes->filter($aRelancer),
            'repondu' => $toutes->whereIn('statut', [Invitation::STATUT_A_REPONDU, Invitation::STATUT_DECLINEE]),
            default => $toutes,
        };

        $candidats = $this->candidats();

        return view('livewire.sessions.invitations', [
            'invitations' => $invitations->values(),
            'total' => $toutes->count(),
            'compteurs' => $compteurs,
            'inscrits' => $inscrits,
            'nbCandidats' => (clone $candidats)->count(),
            'candidats' => $candidats->with('entreprise')->orderBy('nom')->orderBy('prenom')
                ->limit(self::LIMITE_CANDIDATS)->get(),
            'modeles' => ModeleMessage::orderBy('nom')->get(),
            'modeleOuvert' => $this->modeleId ? ModeleMessage::find($this->modeleId) : null,
            'etiquettes' => Etiquette::orderBy('nom')->get(),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

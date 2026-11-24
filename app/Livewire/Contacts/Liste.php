<?php

namespace App\Livewire\Contacts;

use App\Models\Contact;
use App\Models\Etiquette;
use App\Models\Pole;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Liste extends Component
{
    use WithPagination;

    /** Terme de recherche (EF-09). */
    #[Url]
    public string $recherche = '';

    /** Filtre par type : '', 'personne' ou 'entreprise' (EF-10). */
    #[Url]
    public string $type = '';

    /** Filtre par étiquette (id) (EF-10). */
    #[Url]
    public string $etiquette = '';

    /** Filtre par statut : 'actifs' (défaut) ou 'archives' (EF-10). */
    #[Url]
    public string $statut = 'actifs';

    /** Filtre lead : '' (tous), 'leads' (leads uniquement) ou une température précise. */
    #[Url]
    public string $temperature = '';

    /** Filtre par origine du contact. */
    #[Url]
    public string $origine = '';

    /** Filtre par pôle (id). */
    #[Url]
    public string $pole = '';

    /** Filtre par catégorie (id). */
    #[Url]
    public string $categorie = '';

    /** Mails : '' (tous), 'sans' (jamais écrit), 'avec' (déjà écrit), ou une tranche du dernier envoi (voir TRANCHES_MAILS). */
    #[Url]
    public string $mails = '';

    /** Tranches de date du dernier mail envoyé => [écrit depuis N jours, rien depuis N jours]. */
    public const TRANCHES_MAILS = [
        'moins30' => [30, null],
        '30a60' => [60, 30],
        '60a90' => [90, 60],
        'plus90' => [null, 90],
    ];

    /** N'afficher que les fiches qui partagent leur adresse e-mail avec une autre. */
    #[Url]
    public bool $doublons = false;

    /** Afficher aussi les catégories « masquées par défaut » (ex. dormant). */
    #[Url]
    public bool $inclureMasques = false;

    /** Colonne de tri. */
    #[Url]
    public string $tri = 'nom';

    /** Sens de tri. */
    #[Url]
    public string $sens = 'asc';

    /** Filtres retenus d'une visite à l'autre (retour depuis une fiche, menu « Contacts »). */
    public const FILTRES = ['recherche', 'type', 'etiquette', 'statut', 'temperature', 'origine', 'pole', 'categorie', 'mails', 'doublons', 'inclureMasques', 'tri', 'sens'];

    private const CLE_SESSION = 'contacts.filtres';

    /**
     * Sans filtre dans l'adresse, on reprend ceux de la dernière visite (et la page).
     * Une adresse qui porte ses propres filtres reste prioritaire.
     */
    public function mount(): void
    {
        $query = request()->query();
        $sauves = session(self::CLE_SESSION);

        if (! is_array($sauves) || array_intersect(array_keys($query), [...self::FILTRES, 'page']) !== []) {
            return;
        }

        foreach (self::FILTRES as $filtre) {
            if (array_key_exists($filtre, $sauves)) {
                $this->{$filtre} = is_bool($this->{$filtre}) ? (bool) $sauves[$filtre] : (string) $sauves[$filtre];
            }
        }

        if (($sauves['page'] ?? 1) > 1) {
            $this->setPage((int) $sauves['page']);
        }
    }

    /** Un filtre est-il actif (pour afficher « Effacer les filtres ») ? */
    public function filtresActifs(): bool
    {
        $defauts = [
            'recherche' => '', 'type' => '', 'etiquette' => '', 'statut' => 'actifs', 'temperature' => '',
            'origine' => '', 'pole' => '', 'categorie' => '', 'mails' => '', 'doublons' => false, 'inclureMasques' => false,
        ];

        foreach ($defauts as $filtre => $defaut) {
            if ($this->{$filtre} !== $defaut) {
                return true;
            }
        }

        return false;
    }

    /** Réinitialise la pagination dès qu'un filtre change. */
    public function updating($name): void
    {
        if (in_array($name, ['recherche', 'type', 'etiquette', 'statut', 'temperature', 'origine', 'pole', 'categorie', 'mails', 'doublons', 'inclureMasques'], true)) {
            $this->resetPage();
        }
    }

    /** Changer de pôle réinitialise la catégorie choisie. */
    public function updatedPole(): void
    {
        $this->categorie = '';
    }

    /** Choisir une catégorie précise prime sur le filtre pôle (on le vide). */
    public function updatedCategorie(): void
    {
        if ($this->categorie !== '') {
            $this->pole = '';
        }
    }

    /** Change la colonne de tri (ou inverse le sens si déjà active). */
    public function trierPar(string $colonne): void
    {
        if ($this->tri === $colonne) {
            $this->sens = $this->sens === 'asc' ? 'desc' : 'asc';
        } else {
            $this->tri = $colonne;
            $this->sens = 'asc';
        }
    }

    /**
     * Change la catégorie (donc le pôle) d'un contact directement depuis la liste.
     * Une valeur vide retire la catégorie.
     */
    public function changerCategorie(int $contactId, ?string $categorieId = null): void
    {
        $contact = Contact::find($contactId);
        if (! $contact) {
            return;
        }

        $id = ($categorieId !== null && $categorieId !== '') ? (int) $categorieId : null;

        // On n'accepte qu'une catégorie existante (sinon on retire).
        if ($id !== null && ! \App\Models\Categorie::whereKey($id)->exists()) {
            $id = null;
        }

        $contact->update(['categorie_id' => $id]);
    }

    public function reinitialiserFiltres(): void
    {
        $this->reset(['recherche', 'type', 'etiquette', 'statut', 'temperature', 'origine', 'pole', 'categorie', 'mails', 'doublons', 'inclureMasques']);
        $this->resetPage();
        session()->forget(self::CLE_SESSION);
    }

    /**
     * Fiches dont l'adresse e-mail est portée par une autre fiche. Une personne qui
     * a la même adresse que l'entreprise à laquelle elle est rattachée (adresse de
     * contact commune) n'est pas un doublon : elle est écartée des deux côtés.
     */
    private const ADRESSES_EN_DOUBLE = 'NOT EXISTS (SELECT 1 FROM contacts e WHERE e.id = contacts.entreprise_id AND LOWER(e.email) = LOWER(contacts.email))'
        .' AND LOWER(contacts.email) IN (SELECT LOWER(c2.email) FROM contacts c2 LEFT JOIN contacts e2 ON e2.id = c2.entreprise_id'
        .' WHERE c2.email IS NOT NULL AND c2.email <> \'\' AND (e2.id IS NULL OR e2.email IS NULL OR LOWER(e2.email) <> LOWER(c2.email))'
        .' GROUP BY LOWER(c2.email) HAVING COUNT(*) > 1)';

    public function render(): View
    {
        $triAutorise = in_array($this->tri, ['nom', 'updated_at'], true) ? $this->tri : 'nom';
        $sensAutorise = $this->sens === 'desc' ? 'desc' : 'asc';

        $contacts = Contact::query()
            ->with(['entreprise', 'etiquettes'])
            ->avecDernierEnvoi()
            ->recherche($this->recherche)
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->etiquette !== '', fn ($q) => $q->whereHas(
                'etiquettes',
                fn ($e) => $e->where('etiquettes.id', $this->etiquette)
            ))
            ->when(
                $this->statut === 'archives',
                fn ($q) => $q->archives(),
                fn ($q) => $q->actifs()
            )
            ->when($this->temperature === 'leads', fn ($q) => $q->leads())
            ->when(
                $this->temperature !== '' && $this->temperature !== 'leads',
                fn ($q) => $q->where('temperature', $this->temperature)
            )
            ->when($this->origine !== '', fn ($q) => $q->where('origine', $this->origine))
            ->when(in_array($this->mails, ['sans', 'avec'], true), fn ($q) => $q->dejaEcrit($this->mails === 'avec'))
            ->when(isset(self::TRANCHES_MAILS[$this->mails]), function ($q) {
                [$depuis, $rien] = self::TRANCHES_MAILS[$this->mails];
                $q->dernierEnvoiEntre(
                    $depuis ? now()->subDays($depuis)->startOfDay() : null,
                    $rien ? now()->subDays($rien)->startOfDay() : null,
                );
            })
            ->when($this->doublons, fn ($q) => $q->whereRaw(self::ADRESSES_EN_DOUBLE))
            ->when($this->categorie === 'aucune', fn ($q) => $q->whereNull('categorie_id'))
            ->when(
                $this->categorie !== '' && $this->categorie !== 'aucune',
                fn ($q) => $q->where('categorie_id', $this->categorie)
            )
            ->when(
                $this->categorie === '' && $this->pole !== '',
                fn ($q) => $q->whereHas('categorie', fn ($c) => $c->where('pole_id', $this->pole))
            )
            // Catégories masquées par défaut : exclues sauf si on les demande
            // explicitement (filtre catégorie) ou via « inclure masqués ».
            ->when(
                $this->categorie === '' && ! $this->inclureMasques,
                fn ($q) => $q->where(fn ($sub) => $sub
                    ->whereNull('categorie_id')
                    ->orWhereDoesntHave('categorie', fn ($c) => $c->where('masquer_par_defaut', true)))
            )
            // Étiquettes masquées par défaut (ex. « dormant ») : exclues sauf si on
            // filtre sur une étiquette précise ou via « inclure masqués ».
            ->when(
                $this->etiquette === '' && ! $this->inclureMasques,
                fn ($q) => $q->whereDoesntHave('etiquettes', fn ($e) => $e->where('masquer_par_defaut', true))
            )
            ->when($this->doublons, fn ($q) => $q->orderByRaw('LOWER(contacts.email)'))
            ->orderBy($triAutorise, $sensAutorise)
            ->paginate(15);

        // On retient les filtres (et la page) pour la prochaine visite.
        session([self::CLE_SESSION => $this->only(self::FILTRES) + ['page' => $contacts->currentPage()]]);

        return view('livewire.contacts.liste', [
            'contacts' => $contacts,
            'etiquettes' => Etiquette::orderBy('nom')->get(),
            'nbDoublons' => Contact::whereRaw(self::ADRESSES_EN_DOUBLE)->count(),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
        ]);
    }
}

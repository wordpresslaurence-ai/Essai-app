<?php

namespace App\Livewire\Prospection;

use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Pole;
use App\Models\RadarSite;
use App\Services\Radar\AnalyseurSite;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Radar « sites web » (brique 016) : parmi les contacts, repérer ceux qui n'ont
 * pas de site, seulement un réseau social, ou un site à améliorer — puis ouvrir
 * leur fiche avec l'angle du mail de prospection déjà rempli. Sans IA.
 */
#[Layout('layouts.app')]
class Radar extends Component
{
    /** Nombre de sites examinés par clic (chaque site peut prendre quelques secondes). */
    public const LOT = 4;

    public const PAR_PAGE = 40;

    #[Url]
    public string $statut = '';

    #[Url]
    public string $categorie = '';

    #[Url]
    public string $recherche = '';

    public bool $voirEcartes = false;

    /** Offre (catégorie) présentée dans le mail de prospection. */
    public string $offreId = '';

    public int $affiches = self::PAR_PAGE;

    /** @var array<int, int> Contacts que l'on vient d'analyser : gardés en tête de liste, quel que soit le filtre. */
    public array $recents = [];

    public function mount(): void
    {
        // Par défaut, l'offre dont le nom évoque les sites web.
        $offre = Categorie::with('pole')->whereNotNull('offre')->get()
            ->first(fn (Categorie $c) => preg_match('/web|wordpress|site|internet/i', $c->libelleComplet()));
        $this->offreId = $offre ? (string) $offre->id : '';
    }

    public function updated(string $propriete): void
    {
        if (in_array($propriete, ['statut', 'categorie', 'recherche', 'voirEcartes'], true)) {
            $this->affiches = self::PAR_PAGE;
        }
    }

    public function voirPlus(): void
    {
        $this->affiches += self::PAR_PAGE;
    }

    /**
     * Contacts concernés par les filtres (hors statut), avec leur statut calculé.
     *
     * @return Collection<int, Contact>
     */
    protected function contacts(): Collection
    {
        return Contact::actifs()
            ->where(fn ($q) => $q->whereNull('ne_plus_contacter')->orWhere('ne_plus_contacter', false))
            ->when($this->categorie !== '', fn ($q) => $q->where('categorie_id', (int) $this->categorie))
            ->recherche($this->recherche)
            ->with('radarSite')
            ->get(['id', 'type', 'nom', 'prenom', 'email', 'site_web', 'instagram', 'facebook', 'adresse_ville', 'categorie_id'])
            ->filter(fn (Contact $c) => $this->voirEcartes || ! $c->radarSite?->ecarte_le)
            ->each(fn (Contact $c) => $c->setAttribute('statut_radar', RadarSite::statutPour($c)))
            ->values();
    }

    private function enregistrer(Contact $contact): void
    {
        $r = app(AnalyseurSite::class)->analyser($contact->site_web);

        RadarSite::updateOrCreate(['contact_id' => $contact->id], [
            'url' => $contact->site_web,
            'url_finale' => $r['url_finale'],
            'statut' => $r['statut'],
            'techno' => $r['techno'],
            'constats' => $r['constats'],
            'duree_ms' => $r['duree_ms'],
            'analyse_le' => now(),
        ]);

        $this->recents = array_values(array_unique([$contact->id, ...$this->recents]));
    }

    /** Vide le bloc « Tu viens d'analyser ». */
    public function oublierRecents(): void
    {
        $this->recents = [];
    }

    public function analyser(int $contactId): void
    {
        $contact = Contact::actifs()->find($contactId);
        if ($contact && filled($contact->site_web)) {
            $this->enregistrer($contact);

            $statut = RadarSite::STATUTS[RadarSite::statutPour($contact->fresh())] ?? '';
            session()->flash('message', $contact->nomComplet().' : '.mb_strtolower($statut).'. Le détail est affiché en tête de liste.');
        }
    }

    /** Examine les prochains sites « à analyser » de la liste filtrée. */
    public function analyserSuivants(): void
    {
        @set_time_limit(90);

        $lot = $this->contacts()->where('statut_radar', RadarSite::NON_ANALYSE)->take(self::LOT);
        foreach ($lot as $contact) {
            $this->enregistrer($contact);
        }

        session()->flash('message', $lot->isEmpty()
            ? 'Tous les sites de cette liste sont déjà analysés.'
            : $lot->count().' site(s) analysé(s). Les résultats sont affichés en tête de liste.');
    }

    /** « Pas pour moi » : sort le contact du radar (ou l'y remet). */
    public function basculerEcarte(int $contactId): void
    {
        $contact = Contact::with('radarSite')->find($contactId);
        if (! $contact) {
            return;
        }

        $radar = $contact->radarSite ?? RadarSite::create([
            'contact_id' => $contact->id,
            'url' => $contact->site_web,
            'statut' => RadarSite::statutPour($contact),
        ]);

        $radar->update(['ecarte_le' => $radar->ecarte_le ? null : now()]);
    }

    /** Angle transmis au mail de prospection : les constats, à formuler avec tact. */
    public static function angle(Contact $contact, string $statut): string
    {
        $constats = $contact->radarSite?->textesConstats() ?? [];

        return match ($statut) {
            RadarSite::SANS_SITE => 'Proposition de création de site web. Je ne lui connais pas de site : le présenter comme une question ouverte (« je n’ai pas trouvé votre site »), sans l’affirmer.',
            RadarSite::RESEAU_SEUL => 'Proposition de création de site web. Sa présence en ligne semble passer seulement par les réseaux sociaux : montrer ce qu’un site à soi apporte en plus (être trouvé sur Google, inscriptions en ligne), sans dénigrer.',
            RadarSite::INJOIGNABLE => 'Son site ('.$contact->site_web.') ne répondait pas lors de ma visite : le signaler avec bienveillance et proposer mon aide.',
            default => 'Proposition d’amélioration de son site ('.$contact->site_web.'). Indices relevés sur la page d’accueil, à formuler avec tact et comme des pistes, jamais comme des reproches : '
                .mb_strtolower(implode(' ; ', $constats)).'.'
                .($contact->radarSite?->techno ? ' Site réalisé avec '.$contact->radarSite->techno.'.' : ''),
        };
    }

    public function render(): View
    {
        $tous = $this->contacts();

        $ordre = array_flip(array_keys(RadarSite::STATUTS));
        $liste = $tous
            ->when($this->statut !== '', fn (Collection $c) => $c->where('statut_radar', $this->statut))
            ->reject(fn (Contact $c) => in_array($c->id, $this->recents, true))
            ->sortBy(fn (Contact $c) => sprintf('%02d-%s', $ordre[$c->statut_radar] ?? 99, $c->cleTri()))
            ->values();

        // Ceux que l'on vient d'analyser restent visibles, même s'ils ne correspondent plus au filtre.
        $recents = collect($this->recents)
            ->map(fn (int $id) => $tous->firstWhere('id', $id))
            ->filter()
            ->values();

        return view('livewire.prospection.radar', [
            'contacts' => $liste->take($this->affiches),
            'recents' => $recents,
            'total' => $liste->count(),
            'compteurs' => $tous->countBy('statut_radar'),
            'poles' => Pole::with('categories')->orderBy('ordre')->orderBy('nom')->get(),
            'offres' => Categorie::with('pole')->whereNotNull('offre')->where('offre', '!=', '')->get()
                ->sortBy(fn (Categorie $c) => $c->libelleComplet())->values(),
        ]);
    }
}

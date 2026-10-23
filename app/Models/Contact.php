<?php

namespace App\Models;

use Database\Factories\ContactFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un contact : une PERSONNE ou une ENTREPRISE (modèle unique, cf. plan §1).
 */
class Contact extends Model
{
    /** @use HasFactory<ContactFactory> */
    use HasFactory;

    public const TYPE_PERSONNE = 'personne';

    public const TYPE_ENTREPRISE = 'entreprise';

    /** Températures d'un lead : libellé + classe d'avatar (couleur). */
    public const TEMPERATURES = [
        'chaud' => ['Client chaud', 'terra'],
        'eleve' => ['Intérêt élevé', 'mint'],
        'moyen' => ['Intérêt moyen', 'lav'],
        'a_qualifier' => ['À qualifier', 'muted'],
    ];

    /** Expérience du yoga du contact (choix unique). */
    public const EXPERIENCES_YOGA = [
        'debutants' => 'Débutants',
        'reguliers' => 'Pratiquants réguliers',
        'avances' => 'Pratiquants avancés',
        'professeurs' => 'Professeurs de yoga',
        'futurs_professeurs' => 'Futurs professeurs de yoga',
    ];

    /**
     * Disciplines enseignées, groupées par catégorie (menu à deux niveaux).
     * La catégorie elle-même est sélectionnable (niveau 1), suivie de ses
     * options (niveau 2). Valeurs stockées telles quelles (libellés).
     *
     * @var array<string, list<string>>
     */
    public const DISCIPLINES = [
        'Yoga traditionnel et lignées' => [
            'Hatha yoga', 'Raja yoga', 'Yoga intégral', 'Yoga Satyananda / Bihar',
            'Yoga Sivananda', 'Yoga Iyengar', 'Viniyoga', 'Kundalini yoga',
        ],
        'Approches posturales' => [
            'Vinyasa yoga', 'Ashtanga vinyasa', 'Yin yoga', 'Yoga restauratif', 'Power yoga',
        ],
        'Pratiques du souffle et de l’attention' => [
            'Pranayama', 'Méditation', 'Yoga nidra', 'Prana nidra', 'Prana Vidya',
            'Mantras et chant', 'Mudras',
        ],
        'Approches adaptées' => [
            'Yogathérapie', 'Yoga prénatal', 'Yoga postnatal', 'Yoga sur chaise', 'Yoga adapté',
        ],
        'Pratiques complémentaires' => [
            'Breathwork', 'Relaxation', 'Sophrologie', 'Qi gong', 'Pratiques sonores et gong',
        ],
        'Autre' => [
            'Autre — à préciser',
        ],
    ];

    /** Origine du contact (choix unique), liste détaillée. */
    public const ORIGINES = [
        'recherche_web' => 'Recherche web',
        'google_maps' => 'Google Maps',
        'annuaire' => 'Annuaire professionnel ou fédération',
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'autre_reseau' => 'Autre réseau social',
        'site_formulaire' => 'Site internet ou formulaire',
        'newsletter' => 'Inscription newsletter',
        'seance_decouverte' => 'Séance découverte',
        'atelier_stage' => 'Atelier ou stage',
        'salon_evenement' => 'Salon ou événement',
        'recommandation' => 'Recommandation',
        'partenaire' => 'Partenaire',
        'spontane' => 'Prise de contact spontanée',
        'relation' => 'Relation personnelle ou professionnelle',
        'autre' => 'Autre',
        'inconnue' => 'Origine inconnue',
    ];

    protected $fillable = [
        'type',
        'nom',
        'email',
        'telephone',
        'notes',
        'prenom',
        'fonction',
        'entreprise_id',
        'site_web',
        'numero_entreprise',
        'numero_tva',
        'secteur',
        'discipline',
        'fait_observe',
        'angle_contact',
        'experience_yoga',
        'origine',
        'origine_precision',
        'url_profil',
        'temperature',
        'categorie_id',
        'adresse_rue',
        'adresse_code_postal',
        'adresse_ville',
        'adresse_pays',
        'archived_at',
        'newsletter_exporte_le',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
            'newsletter_exporte_le' => 'datetime',
        ];
    }

    // --- Relations -----------------------------------------------------------

    /** L'entreprise à laquelle cette personne est rattachée. */
    public function entreprise(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'entreprise_id');
    }

    /** Les personnes rattachées à cette entreprise (EF-08). */
    public function personnes(): HasMany
    {
        return $this->hasMany(Contact::class, 'entreprise_id');
    }

    /** Les étiquettes associées à ce contact (EF-11). */
    public function etiquettes(): BelongsToMany
    {
        return $this->belongsToMany(Etiquette::class);
    }

    /** Les opportunités liées à ce contact (module Pipeline). */
    public function opportunites(): HasMany
    {
        return $this->hasMany(Opportunite::class);
    }

    /** Les activités liées à ce contact (module Activités). */
    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    /** Les e-mails synchronisés avec ce contact (assistant). */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /** Les analyses de l'assistant pour ce contact. */
    public function analyses(): HasMany
    {
        return $this->hasMany(Analyse::class);
    }

    /** La catégorie (et donc le pôle) de ce contact. */
    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    /** Les inscriptions de ce contact à des séances (sessions). */
    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    // --- Scopes --------------------------------------------------------------

    /** Filtre les contacts de type « personne ». @param  Builder<Contact>  $query */
    public function scopeTypePersonne(Builder $query): void
    {
        $query->where('type', self::TYPE_PERSONNE);
    }

    /** Filtre les contacts de type « entreprise ». @param  Builder<Contact>  $query */
    public function scopeTypeEntreprise(Builder $query): void
    {
        $query->where('type', self::TYPE_ENTREPRISE);
    }

    /** Contacts actifs (non archivés). @param  Builder<Contact>  $query */
    public function scopeActifs(Builder $query): void
    {
        $query->whereNull('archived_at');
    }

    /** Contacts archivés. @param  Builder<Contact>  $query */
    public function scopeArchives(Builder $query): void
    {
        $query->whereNotNull('archived_at');
    }

    /** Leads : contacts ayant une température. @param  Builder<Contact>  $query */
    public function scopeLeads(Builder $query): void
    {
        $query->whereNotNull('temperature');
    }

    /**
     * Recherche insensible à la casse sur nom, prénom, email, téléphone (EF-09).
     *
     * @param  Builder<Contact>  $query
     */
    public function scopeRecherche(Builder $query, ?string $terme): void
    {
        $terme = trim((string) $terme);

        if ($terme === '') {
            return;
        }

        $motif = '%'.$terme.'%';

        $query->where(function (Builder $q) use ($motif) {
            $q->where('nom', 'like', $motif)
                ->orWhere('prenom', 'like', $motif)
                ->orWhere('email', 'like', $motif)
                ->orWhere('telephone', 'like', $motif);
        });
    }

    // --- Aides ---------------------------------------------------------------

    public function estEntreprise(): bool
    {
        return $this->type === self::TYPE_ENTREPRISE;
    }

    public function estPersonne(): bool
    {
        return $this->type === self::TYPE_PERSONNE;
    }

    public function estArchive(): bool
    {
        return $this->archived_at !== null;
    }

    public function estLead(): bool
    {
        return $this->temperature !== null;
    }

    /** Libellé et couleur de la température (ou null). */
    public function temperatureInfo(): ?array
    {
        return $this->temperature ? (self::TEMPERATURES[$this->temperature] ?? null) : null;
    }

    /**
     * Lien cliquable vers le compte/profil (ou null). Préfixe https:// si l'on a
     * collé un domaine sans protocole ; renvoie null pour un simple pseudo.
     */
    public function urlProfilHref(): ?string
    {
        $valeur = trim((string) $this->url_profil);

        if ($valeur === '') {
            return null;
        }

        if (str_starts_with($valeur, 'http://') || str_starts_with($valeur, 'https://')) {
            return $valeur;
        }

        return str_contains($valeur, '.') ? 'https://'.$valeur : null;
    }

    public function archiver(): void
    {
        $this->update(['archived_at' => now()]);
    }

    public function desarchiver(): void
    {
        $this->update(['archived_at' => null]);
    }

    /** Nom d'affichage : « Prénom Nom » pour une personne, la raison sociale sinon. */
    public function nomComplet(): string
    {
        if ($this->estPersonne() && filled($this->prenom)) {
            return trim($this->prenom.' '.$this->nom);
        }

        return $this->nom;
    }

    /**
     * Toutes les valeurs de discipline reconnues (catégories + options),
     * pour savoir si une valeur saisie auparavant est « hors liste ».
     *
     * @return list<string>
     */
    public static function disciplinesConnues(): array
    {
        $valeurs = [];
        foreach (self::DISCIPLINES as $categorie => $options) {
            $valeurs[] = $categorie;
            foreach ($options as $option) {
                $valeurs[] = $option;
            }
        }

        return $valeurs;
    }

    /** Libellé lisible de l'expérience du yoga (ou null). */
    public function libelleExperienceYoga(): ?string
    {
        return filled($this->experience_yoga)
            ? (self::EXPERIENCES_YOGA[$this->experience_yoga] ?? $this->experience_yoga)
            : null;
    }

    /** Libellé lisible de l'origine du contact (ou null). */
    public function libelleOrigine(): ?string
    {
        return filled($this->origine)
            ? (self::ORIGINES[$this->origine] ?? $this->origine)
            : null;
    }

    /** Initiales pour l'avatar (2 lettres). */
    public function initiales(): string
    {
        if ($this->estPersonne() && filled($this->prenom)) {
            return mb_strtoupper(mb_substr($this->prenom, 0, 1).mb_substr($this->nom, 0, 1));
        }

        $mots = preg_split('/\s+/', trim($this->nom)) ?: [];
        if (count($mots) >= 2) {
            return mb_strtoupper(mb_substr($mots[0], 0, 1).mb_substr($mots[1], 0, 1));
        }

        return mb_strtoupper(mb_substr($this->nom, 0, 2));
    }

    /** Couleur d'avatar dérivée du nom (assortie à la charte) : indigo, mauve, slate ou sand. */
    public function couleurAvatar(): string
    {
        $palette = ['indigo', 'mauve', 'slate', 'sand'];

        return $palette[crc32($this->nomComplet()) % count($palette)];
    }
}

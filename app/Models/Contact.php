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

    /** Sources d'acquisition d'un lead : libellé + couleur de pastille. */
    public const SOURCES = [
        'linkedin' => ['LinkedIn', '#0A66C2'],
        'instagram' => ['Instagram', '#C13584'],
        'facebook' => ['Facebook', '#1877F2'],
        'email' => ['E-mail', 'var(--gold)'],
        'site_web' => ['Site web', 'var(--mint)'],
        'telephone' => ['Téléphone', 'var(--lav)'],
        'autre' => ['Autre', 'var(--text-muted)'],
    ];

    /** Températures d'un lead : libellé + classe d'avatar (couleur). */
    public const TEMPERATURES = [
        'chaud' => ['Client chaud', 'terra'],
        'eleve' => ['Intérêt élevé', 'mint'],
        'moyen' => ['Intérêt moyen', 'lav'],
        'a_qualifier' => ['À qualifier', 'muted'],
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
        'source',
        'temperature',
        'adresse_rue',
        'adresse_code_postal',
        'adresse_ville',
        'adresse_pays',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
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

    /** Libellé et couleur de la source (ou null). */
    public function sourceInfo(): ?array
    {
        return $this->source ? (self::SOURCES[$this->source] ?? null) : null;
    }

    /** Libellé et couleur de la température (ou null). */
    public function temperatureInfo(): ?array
    {
        return $this->temperature ? (self::TEMPERATURES[$this->temperature] ?? null) : null;
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

    /** Couleur d'avatar dérivée du nom : mint, lav, gold ou terra. */
    public function couleurAvatar(): string
    {
        $palette = ['mint', 'lav', 'gold', 'terra'];

        return $palette[crc32($this->nomComplet()) % count($palette)];
    }
}

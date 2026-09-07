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

    // --- Scopes --------------------------------------------------------------

    /** @param  Builder<Contact>  $query */
    public function scopePersonnes(Builder $query): void
    {
        $query->where('type', self::TYPE_PERSONNE);
    }

    /** @param  Builder<Contact>  $query */
    public function scopeEntreprises(Builder $query): void
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
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Une « session » (séance datée) : promo de formation, journée aura, etc.
 * Gère les places, les confirmés et la liste d'attente (avec promotion auto).
 */
class Seance extends Model
{
    protected $table = 'seances';

    public const ROLE_DECOUVERTE = 'decouverte';

    public const ROLE_FORMATION = 'formation';

    /** Rôle d'une session dans le parcours formation. */
    public const ROLES_PARCOURS = [
        self::ROLE_DECOUVERTE => 'Séance découverte',
        self::ROLE_FORMATION => 'Formation (stage)',
    ];

    protected $fillable = ['titre', 'categorie_id', 'date_debut', 'lieu', 'lieu_contact_id', 'lien_inscription', 'lien_groupe', 'role_parcours', 'capacite', 'prix', 'notes', 'date_fin'];

    protected function casts(): array
    {
        return [
            'date_debut' => 'datetime',
            'date_fin' => 'datetime',
            'capacite' => 'integer',
            'prix' => 'float',
        ];
    }

    // --- Relations -----------------------------------------------------------

    public function categorie(): BelongsTo
    {
        return $this->belongsTo(Categorie::class);
    }

    /** Contact (salle/lieu) rattaché à la séance. */
    public function lieuContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'lieu_contact_id');
    }

    public function inscriptions(): HasMany
    {
        return $this->hasMany(Inscription::class);
    }

    /** Les dates de la session (plusieurs pour un cycle), dans l'ordre. */
    public function dates(): HasMany
    {
        return $this->hasMany(SeanceDate::class)->orderBy('date_debut');
    }

    /** Un cycle : une session qui se déroule sur plusieurs dates. */
    public function estCycle(): bool
    {
        return $this->date_fin !== null;
    }

    /** Dernière date de la session (la seule, pour une session simple). */
    public function fin(): ?\Illuminate\Support\Carbon
    {
        return $this->date_fin ?? $this->date_debut;
    }

    /** Un cycle déjà commencé n'accepte plus d'inscription. */
    public function inscriptionsCloses(): bool
    {
        return $this->estCycle() && $this->date_debut !== null && $this->date_debut->isPast();
    }

    /** Invitations de la campagne liée à cette séance. */
    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    /** @return Collection<int, Inscription> */
    public function confirmes(): Collection
    {
        return $this->inscriptions->where('statut', Inscription::STATUT_CONFIRME)->sortBy('created_at')->values();
    }

    /** @return Collection<int, Inscription> */
    public function enAttente(): Collection
    {
        return $this->inscriptions->where('statut', Inscription::STATUT_ATTENTE)->sortBy('created_at')->values();
    }

    // --- Scopes --------------------------------------------------------------

    /** Séances à venir (ou sans date). @param  Builder<Seance>  $query */
    public function scopeAVenir(Builder $query): void
    {
        $query->where(fn ($q) => $q->whereNull('date_debut')
            ->orWhere('date_debut', '>=', now()->startOfDay())
            ->orWhere('date_fin', '>=', now()->startOfDay())); // cycle en cours
    }

    /** Séances passées. @param  Builder<Seance>  $query */
    public function scopePassees(Builder $query): void
    {
        $query->whereNotNull('date_debut')->where('date_debut', '<', now()->startOfDay())
            ->where(fn ($q) => $q->whereNull('date_fin')->orWhere('date_fin', '<', now()->startOfDay()));
    }

    /** Prix attendu par participant : celui de la session, sinon celui de sa catégorie. */
    public function prixApplique(): ?float
    {
        if ($this->prix !== null) {
            return (float) $this->prix;
        }

        return $this->categorie?->prix !== null ? (float) $this->categorie->prix : null;
    }

    // --- Places / inscriptions ----------------------------------------------

    public function placesPrises(): int
    {
        return $this->inscriptions()->where('statut', Inscription::STATUT_CONFIRME)->count();
    }

    /** Places restantes, ou null si pas de limite. */
    public function placesRestantes(): ?int
    {
        if (! $this->capacite) {
            return null;
        }

        return max(0, $this->capacite - $this->placesPrises());
    }

    public function estComplete(): bool
    {
        return (bool) $this->capacite && $this->placesPrises() >= $this->capacite;
    }

    /**
     * Inscrit un contact : confirmé s'il reste de la place, sinon liste d'attente.
     * Ne crée pas de doublon si le contact a déjà une inscription active.
     */
    public function inscrire(Contact $contact): Inscription
    {
        $active = $this->inscriptions()
            ->where('contact_id', $contact->id)
            ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
            ->first();

        if ($active) {
            return $active;
        }

        return $this->inscriptions()->create([
            'contact_id' => $contact->id,
            'statut' => $this->estComplete() ? Inscription::STATUT_ATTENTE : Inscription::STATUT_CONFIRME,
        ]);
    }

    /** Promeut la plus ancienne personne en attente s'il reste de la place. */
    public function promouvoirSuivant(): void
    {
        if ($this->estComplete()) {
            return;
        }

        $suivant = $this->inscriptions()
            ->where('statut', Inscription::STATUT_ATTENTE)
            ->orderBy('created_at')->orderBy('id')
            ->first();

        $suivant?->update(['statut' => Inscription::STATUT_CONFIRME]);
    }
}

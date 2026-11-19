<?php

namespace App\Services\Telegram;

use App\Models\Activite;
use App\Models\Inscription;
use App\Models\Parametre;
use App\Models\Seance;
use Illuminate\Support\Collection;
use RuntimeException;
use Throwable;

/**
 * Accès aux canaux Telegram par trimestre (brique 019) : lien personnel à usage
 * unique, renouvellement avant la fin commune, retrait de l'accès ensuite.
 *
 * Pas de tâche planifiée sur l'hébergement : la tâche de renouvellement et les
 * retraits se font à l'ouverture du tableau de bord ou de l'assistant.
 */
class AccesTelegram
{
    public const JOURS_AVANT_RENOUVELLEMENT = 14;

    public function __construct(private ClientTelegram $client) {}

    private static function robotConnecte(): bool
    {
        $p = Parametre::instance();

        return filled($p->telegram_robot) && filled($p->secret('telegram_jeton'));
    }

    /** Crée (ou rend) le lien personnel d'une inscription. */
    public function creerLien(Inscription $inscription): string
    {
        $inscription->loadMissing(['seance.canalTelegram', 'contact']);
        $canal = $inscription->seance->canalTelegram;

        if (! $canal) {
            throw new RuntimeException('Cette session n’est reliée à aucun canal Telegram : choisis-le dans la session.');
        }

        if (filled($inscription->acces_lien) && $inscription->acces_retire_le === null) {
            return $inscription->acces_lien;
        }

        $fin = $inscription->seance->fin_acces?->copy()->endOfDay();
        $lien = $this->client->creerLienInvitation(
            $canal->chat_id,
            $inscription->contact->nomComplet(),
            $fin && $fin->isFuture() ? $fin->timestamp : null,
        );

        $inscription->update([
            'acces_lien' => $lien,
            'acces_lien_cree_le' => now(),
            'acces_envoye_le' => null,
            'acces_rejoint_le' => null,
            'acces_telegram_id' => null,
            'acces_retire_le' => null,
        ]);

        return $lien;
    }

    /** Retire l'accès : la personne quitte le canal, ou son lien inutilisé est annulé. */
    public function retirer(Inscription $inscription): void
    {
        $inscription->loadMissing('seance.canalTelegram');
        $canal = $inscription->seance->canalTelegram;

        if ($canal && filled($inscription->acces_telegram_id)) {
            try {
                $this->client->exclure($canal->chat_id, $inscription->acces_telegram_id);
            } catch (RuntimeException $e) {
                // Déjà sortie du canal : rien à retirer.
                if (! str_contains(mb_strtolower($e->getMessage()), 'not found') && ! str_contains($e->getMessage(), 'PARTICIPANT')) {
                    throw $e;
                }
            }
        } elseif ($canal && filled($inscription->acces_lien)) {
            try {
                $this->client->revoquerLien($canal->chat_id, $inscription->acces_lien);
            } catch (RuntimeException) {
                // Lien déjà expiré : sans importance.
            }
        }

        $inscription->update(['acces_retire_le' => now()]);
    }

    /** Le contact a-t-il encore accès à ce canal par une autre inscription (trimestre renouvelé) ? */
    public static function accesProlonge(Inscription $inscription): bool
    {
        $seance = $inscription->seance;

        return Inscription::query()
            ->where('contact_id', $inscription->contact_id)
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->whereKeyNot($inscription->id)
            ->whereHas('seance', fn ($q) => $q
                ->where('canal_telegram_id', $seance->canal_telegram_id)
                ->where(fn ($f) => $f->whereNull('fin_acces')->orWhere('fin_acces', '>=', now()->toDateString())))
            ->exists();
    }

    /**
     * Retire l'accès de ceux dont le trimestre est terminé et qui n'ont pas renouvelé.
     * Un échec (Telegram injoignable) sera retenté à la prochaine ouverture.
     */
    public function retirerExpires(int $max = 20): int
    {
        if (! self::robotConnecte()) {
            return 0;
        }

        $candidates = Inscription::query()
            ->whereNull('acces_retire_le')
            ->where(fn ($q) => $q->whereNotNull('acces_telegram_id')->orWhereNotNull('acces_lien'))
            ->whereHas('seance', fn ($q) => $q->whereNotNull('canal_telegram_id')
                ->whereNotNull('fin_acces')->where('fin_acces', '<', now()->toDateString()))
            ->with('seance.canalTelegram')
            ->get()
            ->reject(fn (Inscription $i) => self::accesProlonge($i))
            ->take($max);

        $faits = 0;
        foreach ($candidates as $inscription) {
            try {
                $this->retirer($inscription);
                $faits++;
            } catch (Throwable) {
                // Nouvel essai à la prochaine ouverture du CRM.
            }
        }

        return $faits;
    }

    /** Une tâche de renouvellement par trimestre, deux semaines avant la fin commune. */
    public function creerTachesRenouvellement(): int
    {
        $seances = Seance::query()
            ->whereNotNull('canal_telegram_id')
            ->whereNull('renouvellement_cree_le')
            ->whereNotNull('fin_acces')
            ->whereBetween('fin_acces', [now()->toDateString(), now()->addDays(self::JOURS_AVANT_RENOUVELLEMENT)->toDateString()])
            ->whereHas('inscriptions', fn ($q) => $q->where('statut', Inscription::STATUT_CONFIRME))
            ->get();

        foreach ($seances as $seance) {
            Activite::create([
                'titre' => self::titreTache($seance),
                'type' => Activite::TYPE_TACHE,
                'echeance' => now()->startOfDay(),
                'notes' => 'Le message de renouvellement est prêt pour chaque abonné qui ne s’est pas encore réinscrit : '
                    .route('sessions.acces', ['seance' => $seance->id, 'mode' => 'renouvellement']),
            ]);

            $seance->update(['renouvellement_cree_le' => now()]);
        }

        return $seances->count();
    }

    public static function titreTache(Seance $seance): string
    {
        return 'Renouvellement : « '.$seance->titre.' », fin le '.$seance->fin_acces->translatedFormat('j F');
    }

    /** Trimestre suivant sur le même canal, proposé au renouvellement. */
    public static function prochaineSession(Seance $seance): ?Seance
    {
        if (! $seance->canal_telegram_id) {
            return null;
        }

        return Seance::query()
            ->whereKeyNot($seance->id)
            ->where('canal_telegram_id', $seance->canal_telegram_id)
            ->whereNotNull('date_debut')
            ->when($seance->date_debut, fn ($q) => $q->where('date_debut', '>', $seance->date_debut))
            ->orderBy('date_debut')
            ->first();
    }

    /**
     * Abonnés du trimestre à qui proposer le renouvellement : ceux qui ne sont pas
     * déjà inscrits à un trimestre suivant sur le même canal.
     *
     * @param  Collection<int, Inscription>  $inscriptions
     * @return Collection<int, Inscription>
     */
    public static function aRenouveler(Seance $seance, Collection $inscriptions): Collection
    {
        if (! $seance->canal_telegram_id || ! $seance->fin_acces) {
            return collect();
        }

        $dejaReinscrits = Inscription::query()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->whereIn('contact_id', $inscriptions->pluck('contact_id'))
            ->whereHas('seance', fn ($q) => $q->whereKeyNot($seance->id)
                ->where('canal_telegram_id', $seance->canal_telegram_id)
                ->where('fin_acces', '>', $seance->fin_acces->toDateString()))
            ->pluck('contact_id')
            ->all();

        return $inscriptions->reject(fn (Inscription $i) => in_array($i->contact_id, $dejaReinscrits))->values();
    }
}

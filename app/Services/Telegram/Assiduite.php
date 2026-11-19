<?php

namespace App\Services\Telegram;

use App\Models\Inscription;
use App\Models\Parametre;
use App\Models\PratiqueFaite;
use App\Models\PratiqueTelegram;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Assiduité des abonnés (brique 020) : qui clique « ✓ Fait » sous les pratiques,
 * et qui décroche (plus aucune pratique faite alors que d'autres ont été publiées).
 */
class Assiduite
{
    /** Fenêtre d'observation, et délai avant de signaler à nouveau la même personne. */
    public const JOURS = 10;

    /** Nombre minimum de pratiques publiées dans la fenêtre pour parler de décrochage. */
    public const PRATIQUES_MIN = 2;

    /**
     * Comptes Telegram d'une inscription : celui qui est entré dans le canal, et
     * celui relié à la fiche (souvent le même).
     *
     * @return array<int, string>
     */
    private static function comptes(Inscription $inscription): array
    {
        return array_values(array_unique(array_filter([
            $inscription->acces_telegram_id,
            $inscription->contact?->telegram_id,
        ])));
    }

    /**
     * Pratiques publiées et faites pour une inscription, depuis son entrée dans le
     * canal et jusqu'à la fin du trimestre.
     *
     * @return array{publiees: int, faites: int, derniere: ?\Illuminate\Support\Carbon}
     */
    public static function bilan(Inscription $inscription): array
    {
        $seance = $inscription->seance;
        if (! $seance?->canal_telegram_id || ! $inscription->acces_rejoint_le) {
            return ['publiees' => 0, 'faites' => 0, 'derniere' => null];
        }

        $pratiques = PratiqueTelegram::query()
            ->where('canal_telegram_id', $seance->canal_telegram_id)
            ->where('publie_le', '>=', $inscription->acces_rejoint_le->copy()->startOfDay())
            ->when($seance->fin_acces, fn ($q) => $q->where('publie_le', '<=', $seance->fin_acces->copy()->endOfDay()))
            ->pluck('id');

        $faites = PratiqueFaite::query()
            ->whereIn('pratique_telegram_id', $pratiques)
            ->whereIn('telegram_user_id', self::comptes($inscription))
            ->get(['pratique_telegram_id', 'fait_le']);

        return [
            'publiees' => $pratiques->count(),
            'faites' => $faites->unique('pratique_telegram_id')->count(),
            'derniere' => $faites->max('fait_le'),
        ];
    }

    /**
     * Abonnés d'un trimestre en cours qui décrochent : dans le canal depuis au moins
     * une semaine, au moins deux pratiques publiées ces 10 derniers jours, aucune faite.
     *
     * @return Collection<int, array{inscription: Inscription, derniere: ?\Illuminate\Support\Carbon}>
     */
    public static function decrocheurs(): Collection
    {
        $depuis = now()->subDays(self::JOURS);

        return Inscription::query()
            ->where('statut', Inscription::STATUT_CONFIRME)
            ->whereNotNull('acces_rejoint_le')
            ->where('acces_rejoint_le', '<=', now()->subDays(7))
            ->whereNull('acces_retire_le')
            ->where(fn ($q) => $q->whereNull('assiduite_vu_le')->orWhere('assiduite_vu_le', '<', $depuis))
            ->whereHas('seance', fn ($q) => $q->whereNotNull('canal_telegram_id')
                ->where(fn ($f) => $f->whereNull('fin_acces')->orWhere('fin_acces', '>=', now()->toDateString())))
            ->with(['seance', 'contact'])
            ->get()
            ->map(function (Inscription $inscription) use ($depuis) {
                $recentes = PratiqueTelegram::where('canal_telegram_id', $inscription->seance->canal_telegram_id)
                    ->where('publie_le', '>=', $depuis)
                    ->pluck('id');

                if ($recentes->count() < self::PRATIQUES_MIN) {
                    return null;
                }

                $comptes = self::comptes($inscription);
                if (PratiqueFaite::whereIn('pratique_telegram_id', $recentes)->whereIn('telegram_user_id', $comptes)->exists()) {
                    return null;
                }

                return [
                    'inscription' => $inscription,
                    'derniere' => PratiqueFaite::whereIn('telegram_user_id', $comptes)->max('fait_le'),
                ];
            })
            ->filter()
            ->sortBy(fn ($l) => $l['inscription']->contact->cleTri())
            ->values();
    }

    /**
     * Met à jour le branchement du robot quand la liste des événements reçus a changé
     * (une seule fois, à l'ouverture du CRM). En cas d'échec, « Détecter mes canaux »
     * dans les réglages fait la même chose.
     */
    public static function mettreAJourBranchement(): void
    {
        $p = Parametre::instance();

        if ((int) $p->telegram_branchement >= ClientTelegram::VERSION_BRANCHEMENT
            || blank($p->telegram_robot) || blank($p->telegram_secret) || blank($p->secret('telegram_jeton'))) {
            return;
        }

        $p->update(['telegram_branchement' => ClientTelegram::VERSION_BRANCHEMENT]);

        try {
            app(ClientTelegram::class)->brancher(route('webhooks.telegram'), $p->telegram_secret);
        } catch (Throwable) {
            // Rattrapable depuis Réglages → Telegram.
        }
    }
}

<?php

namespace App\Services\Prospection;

use App\Models\AdresseEmail;
use App\Models\Contact;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rapproche des candidats de prospection avec le carnet et entre eux, sur
 * l'e-mail (principal ou secondaire) et l'URL de profil normalisée. Signale
 * sans fusionner : un doute reste un doute.
 */
class Dedoublonnage
{
    /**
     * @param  Collection<int, \App\Models\CandidatProspection>  $candidats
     * @return array<int, array{type: string, contact_id?: int, contact_nom?: string}>
     *                                                            type = 'crm' (déjà au carnet), 'liste' (doublon dans la liste) ou 'nouveau'
     */
    public function pour(Collection $candidats): array
    {
        $emails = $candidats->pluck('email')->filter()
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))->unique()->values();
        $urls = $candidats->pluck('url_profil')->map(fn ($u) => self::normaliserUrl($u))
            ->filter()->unique()->values();

        [$contactsParEmail, $contactsParUrl] = $this->indexerCarnet($emails, $urls);

        $comptesEmail = $candidats->pluck('email')->filter()
            ->map(fn ($e) => mb_strtolower(trim((string) $e)))->countBy();
        $comptesUrl = $candidats->pluck('url_profil')->map(fn ($u) => self::normaliserUrl($u))
            ->filter()->countBy();

        $resultat = [];
        foreach ($candidats as $c) {
            $email = filled($c->email) ? mb_strtolower(trim((string) $c->email)) : null;
            $url = self::normaliserUrl($c->url_profil);

            $contact = ($email && isset($contactsParEmail[$email]))
                ? $contactsParEmail[$email]
                : (($url !== '' && isset($contactsParUrl[$url])) ? $contactsParUrl[$url] : null);

            if ($contact) {
                $resultat[$c->id] = [
                    'type' => 'crm',
                    'contact_id' => $contact->id,
                    'contact_nom' => $contact->nomComplet(),
                ];

                continue;
            }

            $doublonListe = ($email && ($comptesEmail[$email] ?? 0) > 1)
                || ($url !== '' && ($comptesUrl[$url] ?? 0) > 1);

            $resultat[$c->id] = ['type' => $doublonListe ? 'liste' : 'nouveau'];
        }

        return $resultat;
    }

    /**
     * @return array{0: array<string, Contact>, 1: array<string, Contact>}
     */
    private function indexerCarnet(Collection $emails, Collection $urls): array
    {
        $parEmail = [];
        if ($emails->isNotEmpty()) {
            Contact::query()->select('id', 'nom', 'prenom', 'type', 'email')
                ->whereIn(DB::raw('LOWER(email)'), $emails->all())
                ->get()
                ->each(function (Contact $c) use (&$parEmail) {
                    $parEmail[mb_strtolower((string) $c->email)] = $c;
                });

            AdresseEmail::query()->whereIn(DB::raw('LOWER(adresse)'), $emails->all())
                ->with('contact:id,nom,prenom,type')
                ->get()
                ->each(function (AdresseEmail $a) use (&$parEmail) {
                    if ($a->contact) {
                        $parEmail[mb_strtolower($a->adresse)] = $a->contact;
                    }
                });
        }

        $parUrl = [];
        if ($urls->isNotEmpty()) {
            Contact::query()->select('id', 'nom', 'prenom', 'type', 'url_profil')
                ->whereNotNull('url_profil')
                ->get()
                ->each(function (Contact $c) use (&$parUrl) {
                    $n = self::normaliserUrl($c->url_profil);
                    if ($n !== '') {
                        $parUrl[$n] = $c;
                    }
                });
        }

        return [$parEmail, $parUrl];
    }

    /** Normalise une URL pour comparaison : sans protocole, sans www, sans / final, en minuscules. */
    public static function normaliserUrl(?string $url): string
    {
        $u = mb_strtolower(trim((string) $url));
        if ($u === '') {
            return '';
        }
        $u = preg_replace('#^https?://#', '', $u) ?? $u;
        $u = preg_replace('#^www\.#', '', $u) ?? $u;

        return rtrim($u, '/');
    }
}

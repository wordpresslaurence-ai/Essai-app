<?php

namespace App\Services\Prospection;

use App\Models\AdresseEmail;
use App\Models\CandidatProspection;
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
     * @return array<int, array{type: string, contact_id?: int, contact_nom?: string, adresse_partagee?: string}>
     *                                                            type = 'crm' (déjà au carnet), 'liste' (doublon dans la liste) ou 'nouveau' ;
     *                                                            adresse_partagee = nom d'une fiche/candidat au même lieu (alerte douce, pas une fusion)
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

        // Adresses (alerte douce : une personne et une structure peuvent partager
        // un lieu sans être un doublon). On n'alerte que si la rue est renseignée.
        $adrCandidats = [];
        foreach ($candidats as $c) {
            $a = self::normaliserAdresse($c->champ('adresse_rue'), $c->champ('adresse_code_postal'), $c->champ('adresse_ville'));
            if ($a !== '') {
                $adrCandidats[$c->id] = $a;
            }
        }
        $comptesAdr = collect($adrCandidats)->countBy();
        $contactsParAdresse = $adrCandidats === [] ? [] : $this->indexerAdresses();

        // Rapprochement avec les candidats des AUTRES recherches (mémoire inter-campagnes).
        $rechercheId = $candidats->first()?->recherche_prospection_id;
        [$autresParEmail, $autresParUrl] = $this->indexerAutresCampagnes($rechercheId, $emails);

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

            $entree = ['type' => $doublonListe ? 'liste' : 'nouveau'];

            // Alerte « même adresse » (seulement si pas déjà rapproché par e-mail/URL).
            $adr = $adrCandidats[$c->id] ?? '';
            if ($adr !== '') {
                $nomMeme = null;
                if (isset($contactsParAdresse[$adr])) {
                    $nomMeme = $contactsParAdresse[$adr]->nomComplet();
                } elseif (($comptesAdr[$adr] ?? 0) > 1) {
                    $autre = $candidats->first(fn ($o) => $o->id !== $c->id && ($adrCandidats[$o->id] ?? '') === $adr);
                    $nomMeme = $autre?->nom;
                }
                if (filled($nomMeme)) {
                    $entree['adresse_partagee'] = $nomMeme;
                }
            }

            // Déjà repéré dans une autre recherche (même e-mail ou même URL).
            $autre = ($email && isset($autresParEmail[$email]))
                ? $autresParEmail[$email]
                : (($url !== '' && isset($autresParUrl[$url])) ? $autresParUrl[$url] : null);
            if ($autre) {
                $entree['deja_repere'] = (string) $autre->nom;
            }

            $resultat[$c->id] = $entree;
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

    /**
     * Candidats des autres recherches indexés par e-mail et par URL de profil.
     *
     * @return array{0: array<string, CandidatProspection>, 1: array<string, CandidatProspection>}
     */
    private function indexerAutresCampagnes(?int $rechercheId, Collection $emails): array
    {
        if ($rechercheId === null) {
            return [[], []];
        }

        $autres = CandidatProspection::query()
            ->where('recherche_prospection_id', '!=', $rechercheId)
            ->where(function ($q) use ($emails) {
                $q->whereNotNull('url_profil');
                if ($emails->isNotEmpty()) {
                    $q->orWhereIn(DB::raw('LOWER(email)'), $emails->all());
                }
            })
            ->select('id', 'recherche_prospection_id', 'nom', 'email', 'url_profil')
            ->get();

        $parEmail = [];
        $parUrl = [];
        foreach ($autres as $o) {
            if (filled($o->email)) {
                $k = mb_strtolower(trim((string) $o->email));
                $parEmail[$k] ??= $o;
            }
            $u = self::normaliserUrl($o->url_profil);
            if ($u !== '') {
                $parUrl[$u] ??= $o;
            }
        }

        return [$parEmail, $parUrl];
    }

    /** @return array<string, Contact> contacts du carnet indexés par adresse normalisée. */
    private function indexerAdresses(): array
    {
        $map = [];
        Contact::query()->select('id', 'nom', 'prenom', 'type', 'adresse_rue', 'adresse_code_postal', 'adresse_ville')
            ->whereNotNull('adresse_rue')
            ->get()
            ->each(function (Contact $c) use (&$map) {
                $a = self::normaliserAdresse($c->adresse_rue, $c->adresse_code_postal, $c->adresse_ville);
                if ($a !== '' && ! isset($map[$a])) {
                    $map[$a] = $c;
                }
            });

        return $map;
    }

    /** Clé d'adresse pour comparaison : rue|cp|ville en minuscules ; vide si pas de rue. */
    public static function normaliserAdresse(?string $rue, ?string $cp, ?string $ville): string
    {
        $rueN = self::simplifier($rue);
        if ($rueN === '') {
            return '';
        }

        return $rueN.'|'.self::simplifier($cp).'|'.self::simplifier($ville);
    }

    private static function simplifier(?string $v): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim((string) $v))) ?? '';
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

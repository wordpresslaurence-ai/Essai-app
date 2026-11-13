<?php

namespace App\Services\Radar;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Examine la page d'accueil d'un site, sans IA : quelques indices simples qui
 * signalent un site absent, daté ou incomplet. Ce sont des indices relevés
 * automatiquement sur UNE page — à vérifier d'un coup d'œil avant d'en parler.
 */
class AnalyseurSite
{
    /** Domaines qui ne sont pas un site à soi. */
    private const RESEAUX = [
        'facebook.com', 'fb.com', 'instagram.com', 'linkedin.com', 'youtube.com', 'youtu.be',
        'tiktok.com', 'linktr.ee', 'x.com', 'twitter.com', 'wa.me', 'whatsapp.com', 't.me',
    ];

    /** Outils de réservation ou de billetterie reconnus dans le code de la page. */
    private const RESERVATION = [
        'calendly', 'fluent-booking', 'fluentbooking', 'momoyoga', 'eversports', 'mindbody', 'bsport',
        'planity', 'simplybook', 'acuityscheduling', 'bookwhen', 'punchpass', 'teamup', 'resamania',
        'deciplus', 'glofox', 'fitogram', 'ameliabooking', 'bookly', 'wix-bookings', 'doctolib',
        'helloasso', 'weezevent', 'billetweb', 'eventbrite', 'yoplanning', 'sumup', 'setmore',
    ];

    /** Signature dans le code => nom de l'outil qui a servi à faire le site. */
    private const TECHNOS = [
        'wp-content' => 'WordPress', 'wp-includes' => 'WordPress',
        'wixstatic.com' => 'Wix', 'wix.com' => 'Wix',
        'squarespace' => 'Squarespace',
        'jimdo' => 'Jimdo', 'weebly' => 'Weebly', 'webnode' => 'Webnode',
        'e-monsite' => 'e-monsite', 'sites.google.com' => 'Google Sites',
        'webflow' => 'Webflow', 'shopify' => 'Shopify', 'godaddy' => 'GoDaddy',
        'site123' => 'SITE123', 'strikingly' => 'Strikingly', 'odoo' => 'Odoo',
    ];

    /**
     * @return array{statut: string, url: ?string, url_finale: ?string, techno: ?string, constats: array<int, array{cle: string, texte: string}>, duree_ms: ?int}
     */
    public function analyser(?string $url): array
    {
        $resultat = ['statut' => 'sans_site', 'url' => $url, 'url_finale' => null, 'techno' => null, 'constats' => [], 'duree_ms' => null];

        $adresse = self::normaliser($url);
        if ($adresse === null) {
            return $resultat;
        }

        $hote = strtolower((string) parse_url($adresse, PHP_URL_HOST));

        if (self::estReseau($hote)) {
            $resultat['statut'] = 'reseau_seul';
            $resultat['constats'][] = ['cle' => 'reseau', 'texte' => 'Pas de site à soi : seulement une page sur un réseau social'];

            return $resultat;
        }

        if (! self::hotePublic($hote)) {
            $resultat['statut'] = 'injoignable';
            $resultat['constats'][] = ['cle' => 'injoignable', 'texte' => 'Adresse non valide pour un site public'];

            return $resultat;
        }

        $reponse = $this->telecharger($adresse, $resultat['duree_ms']);

        // Pas de réponse en https : on réessaie une fois en http (site sans certificat).
        if ($reponse === null && str_starts_with($adresse, 'https://')) {
            $adresse = 'http://'.substr($adresse, 8);
            $reponse = $this->telecharger($adresse, $resultat['duree_ms']);
        }

        if ($reponse === null) {
            $resultat['statut'] = 'injoignable';
            $resultat['constats'][] = ['cle' => 'injoignable', 'texte' => 'Le site ne répond pas (adresse erronée, site fermé ou trop lent)'];

            return $resultat;
        }

        if ($reponse->failed()) {
            $resultat['statut'] = 'injoignable';
            $resultat['constats'][] = ['cle' => 'injoignable', 'texte' => 'Le site renvoie une erreur ('.$reponse->status().')'];

            return $resultat;
        }

        $redirections = $reponse->header('X-Guzzle-Redirect-History');
        $finale = filled($redirections) ? trim((string) last(explode(',', $redirections))) : $adresse;
        $resultat['url_finale'] = mb_substr($finale, 0, 500);

        // Redirigé vers un réseau social : pas de site à soi.
        if (self::estReseau(strtolower((string) parse_url($finale, PHP_URL_HOST)))) {
            $resultat['statut'] = 'reseau_seul';
            $resultat['constats'][] = ['cle' => 'reseau', 'texte' => 'L’adresse renvoie vers un réseau social, pas vers un site à soi'];

            return $resultat;
        }

        $html = substr($reponse->body(), 0, 400000);
        if (! mb_check_encoding($html, 'UTF-8')) {
            $html = mb_convert_encoding($html, 'UTF-8', 'ISO-8859-1');
        }

        $resultat['techno'] = self::techno($html);
        $resultat['constats'] = $this->constats($html, $finale, $resultat['duree_ms']);
        $resultat['statut'] = count($resultat['constats']) >= 2 ? 'a_ameliorer' : 'correct';

        return $resultat;
    }

    /** Télécharge la page ; null si le site ne répond pas. */
    private function telecharger(string $adresse, ?int &$dureeMs): ?\Illuminate\Http\Client\Response
    {
        try {
            $debut = microtime(true);
            $reponse = Http::timeout(5)->connectTimeout(3)
                ->withUserAgent('Mozilla/5.0 (compatible; verification-site/1.0)')
                ->withOptions(['allow_redirects' => [
                    'max' => 4,
                    'protocols' => ['http', 'https'],
                    'track_redirects' => true,
                    'on_redirect' => function ($requete, $rep, $uri) {
                        if (! self::hotePublic(strtolower((string) $uri->getHost()))) {
                            throw new \RuntimeException('Redirection vers une adresse non publique.');
                        }
                    },
                ]])
                ->get($adresse);
            $dureeMs = (int) round((microtime(true) - $debut) * 1000);

            return $reponse;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @return array<int, array{cle: string, texte: string}>
     */
    private function constats(string $html, string $urlFinale, ?int $dureeMs): array
    {
        $constats = [];
        $bas = mb_strtolower($html);

        if (! str_starts_with(strtolower($urlFinale), 'https://')) {
            $constats[] = ['cle' => 'https', 'texte' => 'Le site n’est pas sécurisé (pas de https) : les navigateurs affichent un avertissement'];
        }

        if (! preg_match('/<meta[^>]+name=["\']?viewport/i', $html)) {
            $constats[] = ['cle' => 'mobile', 'texte' => 'La page d’accueil ne semble pas adaptée aux téléphones'];
        }

        $outil = collect(self::RESERVATION)->first(fn (string $o) => str_contains($bas, $o));
        $mots = preg_match('/r(?:é|&eacute;|e)serv|inscription en ligne|s(?:\'|’|&rsquo;|&#8217;)inscrire|book (?:now|online|a class)|je m(?:\'|’)inscris/iu', $html);
        if (! $outil && ! $mots) {
            $constats[] = ['cle' => 'reservation', 'texte' => 'Pas de réservation ni d’inscription en ligne repérée sur la page d’accueil'];
        }

        $titre = preg_match('/<title[^>]*>\s*([^<]{3,})/i', $html);
        $resume = preg_match('/<meta[^>]+name=["\']?description["\']?[^>]+content=["\'][^"\']{10,}/i', $html)
            || preg_match('/<meta[^>]+content=["\'][^"\']{10,}["\'][^>]+name=["\']?description/i', $html);
        if (! $titre || ! $resume) {
            $constats[] = ['cle' => 'google', 'texte' => 'Il manque le titre ou le résumé que Google affiche dans ses résultats'];
        }

        if (preg_match_all('/(?:©|&copy;|&#169;|copyright)\s*(?:\d{4}\s*[-–]\s*)?((?:19|20)\d{2})/iu', $html, $m)) {
            $annee = max(array_map('intval', $m[1]));
            if ($annee <= (int) now()->year - 3) {
                $constats[] = ['cle' => 'ancien', 'texte' => 'Le bas de page indique '.$annee.' : le site ne paraît plus mis à jour'];
            }
        }

        if ($dureeMs !== null && $dureeMs > 5000) {
            $constats[] = ['cle' => 'lent', 'texte' => 'La page d’accueil met plus de '.floor($dureeMs / 1000).' secondes à répondre'];
        }

        return $constats;
    }

    private static function techno(string $html): ?string
    {
        $bas = strtolower($html);
        foreach (self::TECHNOS as $signature => $nom) {
            if (str_contains($bas, $signature)) {
                return $nom;
            }
        }

        return null;
    }

    /** Ajoute https:// au besoin ; null si ce n'est pas une adresse web. */
    public static function normaliser(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '') {
            return null;
        }
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.ltrim($url, '/');
        }

        return filter_var($url, FILTER_VALIDATE_URL) && filled(parse_url($url, PHP_URL_HOST)) ? $url : null;
    }

    private static function estReseau(string $hote): bool
    {
        $hote = preg_replace('/^(www|m|fr-fr|web)\./', '', $hote);

        foreach (self::RESEAUX as $reseau) {
            if ($hote === $reseau || str_ends_with($hote, '.'.$reseau)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Refuse les adresses internes (le CRM ne doit interroger que des sites publics).
     * Un nom qui ne se résout pas est laissé passer : la requête échouera d'elle-même.
     */
    private static function hotePublic(string $hote): bool
    {
        $hote = trim($hote, '[]');
        if ($hote === '' || $hote === 'localhost' || str_ends_with($hote, '.local') || str_ends_with($hote, '.internal')) {
            return false;
        }

        $ips = filter_var($hote, FILTER_VALIDATE_IP) ? [$hote] : (@gethostbynamel($hote) ?: []);

        foreach ($ips as $ip) {
            if (! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                return false;
            }
        }

        return true;
    }
}

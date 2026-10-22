<?php

use App\Services\Email\ClientImap;

/**
 * Vérifie la normalisation UTF-8 des e-mails lus (objet/corps). Sans elle, un
 * accent arrivant en octet brut (latin-1) ou en quoted-printable faisait
 * échouer l'insertion MySQL : « Incorrect string value: '\xE9ance' ».
 * On teste la conversion seule (mbstring), sans dépendre de l'extension IMAP.
 */
function versUtf8Prive(string $valeur, string $charset = 'default'): string
{
    $methode = new ReflectionMethod(ClientImap::class, 'versUtf8');

    return $methode->invoke(new ClientImap, $valeur, $charset);
}

it('convertit un objet latin-1 en UTF-8 valide', function () {
    $objet = 'S'.chr(0xE9).'ance prana vidya'; // « Séance » en octets latin-1

    $resultat = versUtf8Prive($objet);

    expect($resultat)->toBe('Séance prana vidya');
    expect(mb_check_encoding($resultat, 'UTF-8'))->toBeTrue();
});

it('convertit un corps quoted-printable décodé en UTF-8 valide', function () {
    // « interessée » après quoted_printable_decode donne des octets iso-8859-1.
    $brut = quoted_printable_decode('interess=E9e');

    $resultat = versUtf8Prive($brut, 'iso-8859-1');

    expect($resultat)->toBe('interessée');
    expect(mb_check_encoding($resultat, 'UTF-8'))->toBeTrue();
});

it('laisse intact un texte déjà en UTF-8', function () {
    expect(versUtf8Prive('Déjà bon'))->toBe('Déjà bon');
});

it('renvoie une chaîne vide pour une entrée vide', function () {
    expect(versUtf8Prive(''))->toBe('');
});

it('classe une erreur d’identifiants refusés', function () {
    $msg = ClientImap::interpreterErreur('[AUTHENTICATIONFAILED] Invalid credentials (Failure)');

    expect($msg)->toContain('Identifiants refusés')
        ->toContain('Gmail vs Hostinger');
});

it('classe une erreur de serveur injoignable', function () {
    $msg = ClientImap::interpreterErreur('Can not connect to IMAP server: connection timed out');

    expect($msg)->toContain('Serveur injoignable');
});

it('renvoie un message générique pour une erreur inconnue', function () {
    $msg = ClientImap::interpreterErreur('Erreur inattendue xyz');

    expect($msg)->toContain('Connexion refusée')
        ->toContain('xyz');
});

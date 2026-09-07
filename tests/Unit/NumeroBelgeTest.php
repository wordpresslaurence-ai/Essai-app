<?php

use App\Rules\NumeroEntrepriseBe;
use App\Rules\NumeroTvaBe;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

// Ces tests utilisent le conteneur Laravel (façade Validator + traductions).
uses(TestCase::class);

/*
 * Tests des règles de validation belges (EF-15, EF-16).
 * Numéro d'entreprise valide de référence : 0403.170.701 (clé de contrôle correcte).
 */

// --- Numéro d'entreprise (BCE) ------------------------------------------------

it('accepte un numéro d\'entreprise valide', function () {
    $v = Validator::make(
        ['numero_entreprise' => '0403.170.701'],
        ['numero_entreprise' => new NumeroEntrepriseBe]
    );

    expect($v->passes())->toBeTrue();
});

it('accepte le même numéro sans les points', function () {
    $v = Validator::make(
        ['numero_entreprise' => '0403170701'],
        ['numero_entreprise' => new NumeroEntrepriseBe]
    );

    expect($v->passes())->toBeTrue();
});

it('refuse un numéro d\'entreprise avec une clé de contrôle fausse', function () {
    $v = Validator::make(
        ['numero_entreprise' => '0403.170.700'],
        ['numero_entreprise' => new NumeroEntrepriseBe]
    );

    expect($v->passes())->toBeFalse();
});

it('refuse un numéro d\'entreprise au mauvais format (trop court)', function () {
    $v = Validator::make(
        ['numero_entreprise' => '12345'],
        ['numero_entreprise' => new NumeroEntrepriseBe]
    );

    expect($v->passes())->toBeFalse();
});

// --- Numéro de TVA ------------------------------------------------------------

it('accepte un numéro de TVA valide', function () {
    $v = Validator::make(
        ['numero_tva' => 'BE0403.170.701'],
        ['numero_tva' => new NumeroTvaBe]
    );

    expect($v->passes())->toBeTrue();
});

it('refuse un numéro de TVA incohérent avec le numéro d\'entreprise', function () {
    $v = Validator::make(
        [
            'numero_entreprise' => '0403.170.701',
            'numero_tva' => 'BE0400.378.485', // valide en soi mais différent
        ],
        ['numero_tva' => new NumeroTvaBe]
    );

    expect($v->passes())->toBeFalse();
});

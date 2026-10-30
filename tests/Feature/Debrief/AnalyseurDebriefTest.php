<?php

use App\Services\Debrief\AnalyseurDebrief;

function debriefExemple(): string
{
    return <<<'TXT'
=== DÉBRIEF D'APPEL ===
TYPE : prospection_salle
RÉSUMÉ : Appel avec la salle Feeling Better. Intéressée pour accueillir la formation.
POINTS CLÉS :
- Capacité : 40 personnes
- Tarif : 180 €/jour
- Disponibilités : mardi et jeudi
- Autres faits : parking gratuit
BESOINS / ATTENTES : veut un acompte
OBJECTIONS / FREINS : hésite sur le week-end
PROMIS / DÉCIDÉ : envoyer le programme
PROCHAINE ACTION : Relancer pour confirmer le mardi
ÉCHÉANCE : 2026-11-10
=== FIN ===
TXT;
}

it('extrait tous les champs d’un débrief au format attendu', function () {
    $r = (new AnalyseurDebrief)->analyser(debriefExemple());

    expect($r->type)->toBe('prospection_salle')
        ->and($r->resume)->toContain('Feeling Better')
        ->and($r->capacite)->toBe(40)
        ->and($r->tarif)->toBe('180 €/jour')
        ->and($r->disponibilites)->toContain('mardi')
        ->and($r->besoins)->toContain('acompte')
        ->and($r->objections)->toContain('week-end')
        ->and($r->promis)->toContain('programme')
        ->and($r->prochaineAction)->toContain('Relancer')
        ->and($r->echeance?->format('Y-m-d'))->toBe('2026-11-10')
        ->and($r->estVide())->toBeFalse();

    // Le texte complet garde le contenu mais pas les marqueurs d'encadrement.
    expect($r->texteComplet)->toContain('RÉSUMÉ')->not->toContain('=== FIN');
});

it('extrait la capacité même avec du texte autour', function () {
    $r = (new AnalyseurDebrief)->analyser("CAPACITE : environ 25 pers.\nTARIF : ");
    expect($r->capacite)->toBe(25)->and($r->tarif)->toBeNull();
});

it('calcule une échéance relative « dans X jours »', function () {
    $r = (new AnalyseurDebrief)->analyser('PROCHAINE ACTION : relancer'."\n".'ÉCHÉANCE : dans 7 jours');

    expect($r->echeance?->format('Y-m-d'))->toBe(now()->addDays(7)->format('Y-m-d'));
});

it('ignore les placeholders laissés par l’IA', function () {
    $r = (new AnalyseurDebrief)->analyser("RÉSUMÉ : (2-3 phrases)\nTARIF : N/A\nCAPACITE : ");

    expect($r->resume)->toBeNull()
        ->and($r->tarif)->toBeNull()
        ->and($r->capacite)->toBeNull()
        ->and($r->estVide())->toBeTrue();
});

it('ramène un type inconnu à « autre »', function () {
    $r = (new AnalyseurDebrief)->analyser('TYPE : réunion interne'."\n".'RÉSUMÉ : point hebdo');
    expect($r->type)->toBe('autre');
});

it('découpe « PROCHAINE ACTION » en plusieurs actions', function () {
    $texte = <<<'TXT'
PROCHAINE ACTION :
1. **Claudia :** envoyer les textes.
2. **Laurence :** modifier les tarifs.
- Ensemble : confirmer les dates.
ÉCHÉANCE : 2026-11-15
TXT;

    $r = (new AnalyseurDebrief)->analyser($texte);

    expect($r->prochainesActions)->toBe([
        'Claudia : envoyer les textes.',
        'Laurence : modifier les tarifs.',
        'Ensemble : confirmer les dates.',
    ]);
    expect($r->echeance?->format('Y-m-d'))->toBe('2026-11-15');
});

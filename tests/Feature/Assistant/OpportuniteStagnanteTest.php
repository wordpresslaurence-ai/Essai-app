<?php

use App\Models\Contact;
use App\Models\Opportunite;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function opp(string $etape, ?string $changeeLe = null): Opportunite
{
    $o = Opportunite::create([
        'titre' => 'Deal', 'contact_id' => Contact::factory()->create()->id, 'etape' => $etape,
    ]);

    if ($changeeLe !== null) {
        $o->update(['etape_changee_le' => Carbon::parse($changeeLe)]);
    }

    return $o->fresh();
}

it('liste une opportunité dont l’étape n’a pas bougé depuis plus de 21 jours', function () {
    opp(Opportunite::ETAPE_QUALIFIE, now()->subDays(30)->toDateTimeString());

    expect(Opportunite::stagnantes()->count())->toBe(1);
});

it('ne liste pas une opportunité récente', function () {
    opp(Opportunite::ETAPE_QUALIFIE); // etape_changee_le = maintenant (via booted)

    expect(Opportunite::stagnantes()->count())->toBe(0);
});

it('ne liste jamais une opportunité gagnée ou perdue', function () {
    opp(Opportunite::ETAPE_GAGNE, now()->subDays(60)->toDateTimeString());
    opp(Opportunite::ETAPE_PERDU, now()->subDays(60)->toDateTimeString());

    expect(Opportunite::stagnantes()->count())->toBe(0);
});

it('met à jour etape_changee_le lors d’un changement d’étape', function () {
    $o = opp(Opportunite::ETAPE_NOUVEAU, now()->subDays(30)->toDateTimeString());
    expect(Opportunite::stagnantes()->count())->toBe(1);

    $o->changerEtape(Opportunite::ETAPE_QUALIFIE);

    expect($o->fresh()->etape_changee_le->isToday())->toBeTrue();
    expect(Opportunite::stagnantes()->count())->toBe(0); // n'est plus en retard
});

it('initialise etape_changee_le à la création', function () {
    $o = Opportunite::create([
        'titre' => 'X', 'contact_id' => Contact::factory()->create()->id, 'etape' => Opportunite::ETAPE_NOUVEAU,
    ]);

    expect($o->etape_changee_le)->not->toBeNull();
});

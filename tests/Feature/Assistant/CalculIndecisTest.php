<?php

use App\Models\Contact;
use App\Models\Email;
use App\Models\Inscription;
use App\Models\Opportunite;
use App\Models\Seance;
use App\Services\Relances\CalculIndecis;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** Crée un contact en reculant sa date de création (pour le délai de silence). */
function contactAncien(array $attrs = [], int $joursAnciennete = 30): Contact
{
    $c = Contact::factory()->create($attrs);
    Contact::where('id', $c->id)->update(['created_at' => Carbon::now()->subDays($joursAnciennete)]);

    return $c->fresh();
}

it('détecte un lead chaud resté sans suite', function () {
    $c = contactAncien(['temperature' => 'chaud']);

    $indecis = (new CalculIndecis)->leadsChaudsSansSuite();
    expect($indecis->pluck('id'))->toContain($c->id);
});

it('ignore un lead chaud créé récemment', function () {
    Contact::factory()->create(['temperature' => 'chaud']); // created_at = aujourd'hui

    expect((new CalculIndecis)->leadsChaudsSansSuite())->toHaveCount(0);
});

it('ignore un lead chaud avec un e-mail récent', function () {
    $c = contactAncien(['temperature' => 'eleve']);
    Email::create([
        'contact_id' => $c->id, 'direction' => Email::DIRECTION_ENTRANT,
        'de' => 'x@example.com', 'a' => 'y@example.com', 'objet' => 'Coucou',
        'date_email' => Carbon::now()->subDay(), 'message_id' => 'rec-1', 'boite' => 'y@example.com',
    ]);

    expect((new CalculIndecis)->leadsChaudsSansSuite())->toHaveCount(0);
});

it('ignore un lead chaud déjà inscrit (confirmé)', function () {
    $c = contactAncien(['temperature' => 'chaud']);
    $seance = Seance::create(['titre' => 'Promo', 'date_debut' => Carbon::now()->addDays(10)]);
    Inscription::create(['seance_id' => $seance->id, 'contact_id' => $c->id, 'statut' => Inscription::STATUT_CONFIRME]);

    expect((new CalculIndecis)->leadsChaudsSansSuite())->toHaveCount(0);
});

it('ignore un lead chaud ayant déjà une opportunité gagnée', function () {
    $c = contactAncien(['temperature' => 'chaud']);
    Opportunite::create(['titre' => 'Deal', 'contact_id' => $c->id, 'etape' => Opportunite::ETAPE_GAGNE]);

    expect((new CalculIndecis)->leadsChaudsSansSuite())->toHaveCount(0);
});

it('détecte un contact intéressé à une session passée (attente) sans place à venir', function () {
    $c = Contact::factory()->create();
    $passee = Seance::create(['titre' => 'Journée aura', 'date_debut' => Carbon::now()->subDays(10)]);
    Inscription::create(['seance_id' => $passee->id, 'contact_id' => $c->id, 'statut' => Inscription::STATUT_ATTENTE]);

    $indecis = (new CalculIndecis)->interessesSessionPassee();
    expect($indecis->pluck('id'))->toContain($c->id);
});

it('ignore un contact qui a déjà une place confirmée à venir', function () {
    $c = Contact::factory()->create();
    $passee = Seance::create(['titre' => 'Journée aura', 'date_debut' => Carbon::now()->subDays(10)]);
    Inscription::create(['seance_id' => $passee->id, 'contact_id' => $c->id, 'statut' => Inscription::STATUT_ANNULE]);
    $future = Seance::create(['titre' => 'Prochaine', 'date_debut' => Carbon::now()->addDays(10)]);
    Inscription::create(['seance_id' => $future->id, 'contact_id' => $c->id, 'statut' => Inscription::STATUT_CONFIRME]);

    expect((new CalculIndecis)->interessesSessionPassee())->toHaveCount(0);
});

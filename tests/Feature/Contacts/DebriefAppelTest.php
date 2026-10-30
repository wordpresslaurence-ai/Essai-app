<?php

use App\Livewire\Contacts\DebriefAppel;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function debriefColle(): string
{
    return <<<'TXT'
=== DÉBRIEF D'APPEL ===
TYPE : prospection_salle
RÉSUMÉ : La salle est intéressée.
POINTS CLÉS :
- Capacité : 40 personnes
- Tarif : 180 €/jour
PROCHAINE ACTION : Relancer pour confirmer le mardi
ÉCHÉANCE : 2026-11-10
=== FIN ===
TXT;
}

it('affiche un prompt pré-rempli avec le contact', function () {
    $c = Contact::factory()->entreprise()->create(['nom' => 'Salle Feeling Better']);

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->assertSee('Salle Feeling Better')
        ->assertSee('=== DÉBRIEF', false)
        ->assertSet('analyse', false);
});

it('analyse le débrief collé et pré-remplit l’aperçu', function () {
    $c = Contact::factory()->create();

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->set('contenu', debriefColle())
        ->call('analyserDebrief')
        ->assertSet('analyse', true)
        ->assertSet('capacite', 40)
        ->assertSet('tarif', '180 €/jour')
        ->assertSet('majFiche', true)
        ->assertSet('taches.0.echeance', '2026-11-10')
        ->assertSet('taches.0.creer', true)
        ->assertSet('erreur', null);
});

it('enregistre une note, met à jour la fiche et crée la tâche de relance', function () {
    $c = Contact::factory()->create();

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->set('contenu', debriefColle())
        ->call('analyserDebrief')
        ->call('enregistrer')
        ->assertRedirect(route('contacts.fiche', $c));

    // Fiche mise à jour
    $c->refresh();
    expect($c->capacite)->toBe(40)->and($c->tarif)->toBe('180 €/jour');

    // Note d'historique
    $note = Activite::where('contact_id', $c->id)->where('type', Activite::TYPE_NOTE)->first();
    expect($note)->not->toBeNull();
    expect($note->notes)->toContain('intéressée');
    expect($note->terminee_at)->not->toBeNull();

    // Tâche de relance avec échéance
    $tache = Activite::where('contact_id', $c->id)->where('type', Activite::TYPE_TACHE)->first();
    expect($tache)->not->toBeNull();
    expect($tache->titre)->toContain('Relancer');
    expect($tache->echeance?->format('Y-m-d'))->toBe('2026-11-10');
    expect($tache->terminee_at)->toBeNull();
});

it('ne touche pas la fiche si on décoche la mise à jour', function () {
    $c = Contact::factory()->create(['capacite' => null, 'tarif' => null]);

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->set('contenu', debriefColle())
        ->call('analyserDebrief')
        ->set('majFiche', false)
        ->set('taches', [])
        ->call('enregistrer');

    $c->refresh();
    expect($c->capacite)->toBeNull()->and($c->tarif)->toBeNull();
    expect(Activite::where('contact_id', $c->id)->where('type', Activite::TYPE_TACHE)->count())->toBe(0);
    // La note reste créée (c'est le cœur du débrief).
    expect(Activite::where('contact_id', $c->id)->where('type', Activite::TYPE_NOTE)->count())->toBe(1);
});

it('rattache la note et la tâche à l’unique opportunité ouverte', function () {
    $c = Contact::factory()->create();
    $opp = Opportunite::factory()->create(['contact_id' => $c->id]);

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->assertSet('opportuniteId', $opp->id)
        ->set('contenu', debriefColle())
        ->call('analyserDebrief')
        ->call('enregistrer');

    expect(Activite::where('opportunite_id', $opp->id)->count())->toBe(2);
});

it('refuse d’analyser un contenu vide', function () {
    $c = Contact::factory()->create();

    Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->set('contenu', '   ')
        ->call('analyserDebrief')
        ->assertSet('analyse', false)
        ->assertSet('erreur', 'Colle d’abord le débrief renvoyé par ChatGPT.');
});

it('crée une tâche par action quand le débrief en liste plusieurs', function () {
    $c = Contact::factory()->create();

    $debrief = <<<'TXT'
=== DÉBRIEF D'APPEL ===
RÉSUMÉ : Réunion de préparation du site.
PROCHAINE ACTION :
1. **Claudia :** envoyer les textes Word et les photos.
2. **Laurence :** modifier le menu et les tarifs.
3. **Laurence :** vérifier la cohérence des tarifs.
4. **Ensemble :** confirmer les dates.
ÉCHÉANCE : 2026-11-15
=== FIN ===
TXT;

    $comp = Livewire::test(DebriefAppel::class, ['contact' => $c])
        ->set('contenu', $debrief)
        ->call('analyserDebrief');

    expect($comp->get('taches'))->toHaveCount(4);

    $comp->call('enregistrer');

    $taches = Activite::where('contact_id', $c->id)->where('type', Activite::TYPE_TACHE)->get();
    expect($taches)->toHaveCount(4);
    expect($taches->pluck('titre')->all())->toContain('Claudia : envoyer les textes Word et les photos.');
    expect($taches->first()->echeance?->format('Y-m-d'))->toBe('2026-11-15');
});

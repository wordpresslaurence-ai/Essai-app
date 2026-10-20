<?php

use App\Livewire\Activites\Formulaire;
use App\Livewire\Activites\Liste;
use App\Livewire\Contacts\FicheDetail;
use App\Livewire\TableauBord;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('ajoute une activité avec échéance aujourd\'hui, visible dans « À faire » et sur le tableau de bord', function () {
    Livewire::test(Liste::class)
        ->set('titre', 'Rappeler un client')
        ->set('type', Activite::TYPE_APPEL)
        ->set('echeance', now()->toDateString())
        ->call('ajouter')
        ->assertSee('Rappeler un client');

    expect(Activite::where('titre', 'Rappeler un client')->exists())->toBeTrue();

    Livewire::test(TableauBord::class)->assertSee('Rappeler un client');
});

it('coche une activité comme terminée', function () {
    $a = Activite::factory()->create(['titre' => 'À cocher']);

    Livewire::test(Liste::class)
        ->assertSee('À cocher')
        ->call('basculer', $a->id);

    expect($a->fresh()->estTerminee())->toBeTrue();

    // Terminée : quitte « à faire », visible dans « terminées »
    Livewire::test(Liste::class)->assertDontSee('À cocher');
    Livewire::test(Liste::class)->set('filtre', 'terminees')->assertSee('À cocher');
});

it('marque une échéance passée comme en retard', function () {
    $a = Activite::factory()->create(['echeance' => now()->subDays(2)]);

    expect($a->enRetard())->toBeTrue();
});

it('affiche les activités d\'un contact sur sa fiche', function () {
    $contact = Contact::factory()->create();
    Activite::factory()->create(['titre' => 'Sur la fiche', 'contact_id' => $contact->id]);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Sur la fiche');
});

it('conserve une activité quand son contact est supprimé (détachée)', function () {
    $contact = Contact::factory()->create();
    $a = Activite::factory()->create(['contact_id' => $contact->id]);

    $contact->delete();

    expect(Activite::find($a->id))->not->toBeNull();
    expect($a->fresh()->contact_id)->toBeNull();
});

it('supprime une activité', function () {
    $a = Activite::factory()->create();

    Livewire::test(Formulaire::class, ['activite' => $a])->call('supprimer');

    expect(Activite::find($a->id))->toBeNull();
});

it('filtre les activités par pôle via le contact lié', function () {
    $prana = App\Models\Pole::create(['nom' => 'Prana']);
    $digital = App\Models\Pole::create(['nom' => 'Digital']);
    $formation = App\Models\Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);
    $sites = App\Models\Categorie::create(['pole_id' => $digital->id, 'nom' => 'Sites web']);

    $cPrana = Contact::factory()->create(['categorie_id' => $formation->id]);
    $cDigital = Contact::factory()->create(['categorie_id' => $sites->id]);

    Activite::create(['titre' => 'ActiPrana', 'type' => Activite::TYPE_TACHE, 'contact_id' => $cPrana->id]);
    Activite::create(['titre' => 'ActiDigital', 'type' => Activite::TYPE_TACHE, 'contact_id' => $cDigital->id]);

    Livewire::test(Liste::class)
        ->set('filtre', 'toutes')
        ->set('pole', (string) $prana->id)
        ->assertSee('ActiPrana')
        ->assertDontSee('ActiDigital');
});

it('filtre les activités par catégorie via le contact lié', function () {
    $prana = App\Models\Pole::create(['nom' => 'Prana']);
    $formation = App\Models\Categorie::create(['pole_id' => $prana->id, 'nom' => 'Formation profs']);
    $aura = App\Models\Categorie::create(['pole_id' => $prana->id, 'nom' => 'Journée aura']);

    $cForm = Contact::factory()->create(['categorie_id' => $formation->id]);
    $cAura = Contact::factory()->create(['categorie_id' => $aura->id]);

    Activite::create(['titre' => 'ActiFormation', 'type' => Activite::TYPE_TACHE, 'contact_id' => $cForm->id]);
    Activite::create(['titre' => 'ActiAura', 'type' => Activite::TYPE_TACHE, 'contact_id' => $cAura->id]);

    Livewire::test(Liste::class)
        ->set('filtre', 'toutes')
        ->set('pole', (string) $prana->id)
        ->set('categorie', (string) $formation->id)
        ->assertSee('ActiFormation')
        ->assertDontSee('ActiAura');
});

it('affiche la vue calendrier avec une activité datée', function () {
    $contact = Contact::factory()->create();
    Activite::create([
        'titre' => 'RdvCalendrier', 'type' => Activite::TYPE_RDV,
        'contact_id' => $contact->id, 'echeance' => now(),
    ]);

    Livewire::test(Liste::class)
        ->set('filtre', 'toutes')
        ->set('vue', 'calendrier')
        ->assertOk()
        ->assertSee('RdvCalendrier');
});

it('navigue entre les périodes du calendrier', function () {
    Livewire::test(Liste::class)
        ->set('vue', 'calendrier')
        ->set('modeCalendrier', 'mois')
        ->call('aujourdhui')
        ->assertSet('dateRef', now()->toDateString())
        ->call('periodeSuivante')
        ->assertSet('dateRef', now()->addMonthNoOverflow()->toDateString())
        ->call('periodePrecedente')
        ->assertSet('dateRef', now()->toDateString());
});

it('affiche un lien vers la fiche du contact dans le formulaire activité', function () {
    $contact = Contact::factory()->create();
    $activite = Activite::create(['titre' => 'Test', 'type' => Activite::TYPE_TACHE, 'contact_id' => $contact->id]);

    Livewire::test(App\Livewire\Activites\Formulaire::class, ['activite' => $activite])
        ->assertSee('Voir la fiche du contact');
});

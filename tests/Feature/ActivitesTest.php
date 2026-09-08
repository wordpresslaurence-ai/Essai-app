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

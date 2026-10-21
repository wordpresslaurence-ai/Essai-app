<?php

use App\Livewire\Contacts\ExportNewsletter;
use App\Models\Contact;
use App\Models\Etiquette;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('génère un CSV avec les en-têtes et les e-mails', function () {
    $c = Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Marie', 'email' => 'marie@ex.be']);

    $csv = app(ExportNewsletter::class)->genererCsv(new Collection([$c]));

    expect($csv)->toContain('Prénom,Nom,Email,Téléphone,Société');
    expect($csv)->toContain('marie@ex.be');
    expect($csv)->toContain('Durand');
});

it('exporte et marque les contacts comme exportés', function () {
    $c1 = Contact::factory()->create(['email' => 'a@ex.be']);
    $c2 = Contact::factory()->create(['email' => 'b@ex.be']);

    Livewire::test(ExportNewsletter::class)
        ->set('etiquette', '')
        ->set('scope', 'tous')
        ->call('exporter');

    expect($c1->fresh()->newsletter_exporte_le)->not->toBeNull();
    expect($c2->fresh()->newsletter_exporte_le)->not->toBeNull();
});

it('n’exporte que les nouveaux en mode « nouveaux »', function () {
    $ancien = Contact::factory()->create(['email' => 'old@ex.be', 'newsletter_exporte_le' => now()->subDay()]);
    $nouveau = Contact::factory()->create(['email' => 'new@ex.be']);

    Livewire::test(ExportNewsletter::class)
        ->set('etiquette', '')
        ->set('scope', 'nouveaux')
        ->call('exporter');

    expect($nouveau->fresh()->newsletter_exporte_le)->not->toBeNull();
    // L'ancien n'est pas ré-exporté : sa date d'export ne change pas.
    expect($ancien->fresh()->newsletter_exporte_le->toDateString())->toBe(now()->subDay()->toDateString());
});

it('filtre par étiquette', function () {
    $et = Etiquette::create(['nom' => 'Newsletter']);
    $dedans = Contact::factory()->create(['email' => 'in@ex.be']);
    $dedans->etiquettes()->attach($et->id);
    $dehors = Contact::factory()->create(['email' => 'out@ex.be']);

    Livewire::test(ExportNewsletter::class)
        ->set('etiquette', (string) $et->id)
        ->set('scope', 'tous')
        ->call('exporter');

    expect($dedans->fresh()->newsletter_exporte_le)->not->toBeNull();
    expect($dehors->fresh()->newsletter_exporte_le)->toBeNull();
});

it('pré-sélectionne l’étiquette Newsletter si elle existe', function () {
    $et = Etiquette::create(['nom' => 'Newsletter']);

    Livewire::test(ExportNewsletter::class)
        ->assertSet('etiquette', (string) $et->id);
});

it('réinitialise le suivi d’export', function () {
    $c = Contact::factory()->create(['email' => 'a@ex.be', 'newsletter_exporte_le' => now()]);

    Livewire::test(ExportNewsletter::class)
        ->set('etiquette', '')
        ->call('reinitialiser');

    expect($c->fresh()->newsletter_exporte_le)->toBeNull();
});

it('exige une connexion', function () {
    auth()->logout();
    $this->get(route('contacts.newsletter'))->assertRedirect(route('login'));
});

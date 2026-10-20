<?php

use App\Livewire\Sessions\Fiche;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('inscrit un contact (confirmé quand il reste de la place)', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 2]);
    $contact = Contact::factory()->create();

    Livewire::test(Fiche::class, ['seance' => $seance])
        ->set('contactAInscrire', (string) $contact->id)
        ->call('inscrire')
        ->assertHasNoErrors();

    expect($seance->inscriptions()->where('contact_id', $contact->id)->first()->statut)
        ->toBe(Inscription::STATUT_CONFIRME);
});

it('met en liste d\'attente au-delà de la capacité', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 1]);
    $c1 = Contact::factory()->create();
    $c2 = Contact::factory()->create();

    $page = Livewire::test(Fiche::class, ['seance' => $seance]);
    $page->set('contactAInscrire', (string) $c1->id)->call('inscrire');
    $page->set('contactAInscrire', (string) $c2->id)->call('inscrire');

    expect($seance->inscriptions()->where('contact_id', $c2->id)->first()->statut)
        ->toBe(Inscription::STATUT_ATTENTE);
});

it('promeut automatiquement quand on désinscrit un confirmé', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 1]);
    $c1 = Contact::factory()->create();
    $c2 = Contact::factory()->create();
    $ins1 = $seance->inscrire($c1); // confirmé
    $ins2 = $seance->inscrire($c2); // attente

    Livewire::test(Fiche::class, ['seance' => $seance])
        ->call('desinscrire', $ins1->id);

    expect($ins2->fresh()->statut)->toBe(Inscription::STATUT_CONFIRME);
    expect(Inscription::find($ins1->id))->toBeNull();
});

it('promeut manuellement si une place est libre', function () {
    $seance = Seance::create(['titre' => 'Promo', 'capacite' => 2]);
    $c1 = Contact::factory()->create();
    $c2 = Contact::factory()->create();
    $c3 = Contact::factory()->create();
    $seance->inscrire($c1);
    $seance->inscrire($c2);
    $ins3 = $seance->inscrire($c3); // attente (complet)

    // On libère une place puis on promeut le 3e manuellement
    $seance->inscriptions()->where('contact_id', $c1->id)->first()->delete();

    Livewire::test(Fiche::class, ['seance' => $seance])
        ->call('promouvoir', $ins3->id);

    expect($ins3->fresh()->statut)->toBe(Inscription::STATUT_CONFIRME);
});

it('refuse d\'inscrire sans contact', function () {
    $seance = Seance::create(['titre' => 'Promo']);

    Livewire::test(Fiche::class, ['seance' => $seance])
        ->call('inscrire')
        ->assertHasErrors(['contactAInscrire']);
});

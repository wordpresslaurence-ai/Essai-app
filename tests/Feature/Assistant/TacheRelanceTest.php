<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\Activite;
use App\Models\Analyse;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function analysePour(Contact $c, string $delai): Analyse
{
    return Analyse::create([
        'contact_id' => $c->id,
        'resume' => 'Le contact demande le programme.',
        'verdict' => 'Oui, relancer : il demande explicitement le programme.',
        'delai_conseille' => $delai,
        'brouillon' => 'Bonjour, voici le programme…',
        'faite_le' => now(),
    ]);
}

it('crée une tâche de relance avec le verdict en note et l’échéance du jour', function () {
    $contact = Contact::factory()->create();
    analysePour($contact, 'sous 24 h');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('creerTacheRelance');

    $tache = Activite::where('contact_id', $contact->id)->firstOrFail();

    expect($tache->titre)->toBe('Relancer '.$contact->nomComplet());
    expect($tache->type)->toBe(Activite::TYPE_TACHE);
    expect($tache->notes)->toContain('il demande explicitement le programme');
    expect($tache->notes)->toContain('Brouillon de mail proposé')
        ->toContain('voici le programme'); // le brouillon est repris dans la tâche
    expect($tache->echeance?->toDateString())->toBe(now()->toDateString());
});

it('déduit une échéance à +2 jours pour « sous 48 h »', function () {
    $contact = Contact::factory()->create();
    analysePour($contact, 'sous 48 h');

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('creerTacheRelance');

    $tache = Activite::where('contact_id', $contact->id)->firstOrFail();

    expect($tache->echeance?->toDateString())->toBe(now()->addDays(2)->toDateString());
});

it('ne crée pas deux tâches de relance pour le même contact', function () {
    $contact = Contact::factory()->create();
    analysePour($contact, 'demain');

    $composant = Livewire::test(FicheDetail::class, ['contact' => $contact]);
    $composant->call('creerTacheRelance');
    $composant->call('creerTacheRelance');

    expect(Activite::where('contact_id', $contact->id)->count())->toBe(1);
});

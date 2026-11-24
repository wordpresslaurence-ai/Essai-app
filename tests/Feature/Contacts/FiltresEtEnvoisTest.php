<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Contacts\Liste;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Email;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('retient les filtres en revenant au carnet et les efface d’un clic', function () {
    Contact::factory()->create(['nom' => 'Zen SPRL', 'type' => Contact::TYPE_ENTREPRISE]);
    Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'type' => Contact::TYPE_PERSONNE]);

    Livewire::test(Liste::class)
        ->assertDontSee('Effacer les filtres')
        ->set('type', Contact::TYPE_ENTREPRISE)
        ->assertSee('Effacer les filtres');

    // Retour au carnet (depuis une fiche, ou par le menu) : le filtre est toujours là.
    Livewire::test(Liste::class)
        ->assertSet('type', Contact::TYPE_ENTREPRISE)
        ->assertSee('Zen SPRL')
        ->assertDontSee('Marie Durand')
        ->call('reinitialiserFiltres')
        ->assertSet('type', '')
        ->assertSee('Marie Durand');

    Livewire::test(Liste::class)->assertSet('type', '');
});

it('donne la priorité aux filtres présents dans l’adresse', function () {
    Livewire::test(Liste::class)->set('type', Contact::TYPE_ENTREPRISE);

    Livewire::withQueryParams(['type' => Contact::TYPE_PERSONNE])
        ->test(Liste::class)
        ->assertSet('type', Contact::TYPE_PERSONNE);
});

it('montre l’enveloppe du dernier mail envoyé dans le carnet et sur la fiche', function () {
    $ecrit = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $note = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']);
    $rien = Contact::factory()->create(['prenom' => 'Anne', 'nom' => 'Sansmail']);

    Email::create([
        'contact_id' => $ecrit->id, 'direction' => Email::DIRECTION_SORTANT, 'objet' => 'Bonjour',
        'date_email' => '2026-11-12 10:00:00', 'message_id' => 'a@x', 'de' => 'moi@x.be', 'a' => 'marie@x.be', 'boite' => 'moi@x.be',
    ]);
    Email::create([
        'contact_id' => $ecrit->id, 'direction' => Email::DIRECTION_ENTRANT, 'objet' => 'Re: Bonjour',
        'date_email' => '2026-11-15 10:00:00', 'message_id' => 'b@x', 'de' => 'marie@x.be', 'a' => 'moi@x.be', 'boite' => 'moi@x.be',
    ]);
    Activite::create([
        'titre' => 'Envoyer le mail de prospection à Paul Petit', 'type' => Activite::TYPE_TACHE,
        'contact_id' => $note->id, 'terminee_at' => '2026-11-20 09:00:00',
    ]);

    expect($ecrit->dernierEnvoi()->format('d/m'))->toBe('12/11'); // le mail reçu ne compte pas
    expect($note->dernierEnvoi()->format('d/m'))->toBe('20/11');
    expect($rien->dernierEnvoi())->toBeNull();

    Livewire::test(Liste::class)
        ->assertSee('Dernier mail envoyé le '.$ecrit->dernierEnvoi()->translatedFormat('j F Y'))
        ->assertSee('Dernier mail envoyé le '.$note->dernierEnvoi()->translatedFormat('j F Y'));

    Livewire::test(FicheDetail::class, ['contact' => $ecrit])->assertSee('Dernier mail envoyé le '.$ecrit->dernierEnvoi()->translatedFormat('j F Y'));
    Livewire::test(FicheDetail::class, ['contact' => $rien])->assertSee('Aucun mail envoyé');
});

it('filtre les contacts à qui un mail a déjà été envoyé, ou jamais', function () {
    $ecrit = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $note = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']);
    Contact::factory()->create(['prenom' => 'Anne', 'nom' => 'Sansmail']);
    Contact::factory()->create(['prenom' => 'Luc', 'nom' => 'Recu']);

    Email::create([
        'contact_id' => $ecrit->id, 'direction' => Email::DIRECTION_SORTANT, 'objet' => 'Bonjour',
        'date_email' => '2026-11-12 10:00:00', 'message_id' => 'a@x', 'de' => 'moi@x.be', 'a' => 'marie@x.be', 'boite' => 'moi@x.be',
    ]);
    Email::create([
        'contact_id' => Contact::where('nom', 'Recu')->value('id'), 'direction' => Email::DIRECTION_ENTRANT, 'objet' => 'Question',
        'date_email' => '2026-11-13 10:00:00', 'message_id' => 'b@x', 'de' => 'luc@x.be', 'a' => 'moi@x.be', 'boite' => 'moi@x.be',
    ]);
    Activite::create(['titre' => 'Invitation envoyée', 'type' => Activite::TYPE_EMAIL, 'contact_id' => $note->id, 'terminee_at' => now()]);

    Livewire::test(Liste::class)
        ->set('mails', 'sans')
        ->assertSee('Anne Sansmail')
        ->assertSee('Luc Recu')
        ->assertDontSee('Marie Durand')
        ->assertDontSee('Paul Petit')
        ->assertSee('Effacer les filtres')
        ->set('mails', 'avec')
        ->assertSee('Marie Durand')
        ->assertSee('Paul Petit')
        ->assertDontSee('Anne Sansmail')
        ->assertDontSee('Luc Recu');
});

it('filtre selon la date du dernier mail envoyé', function () {
    $envoi = function (string $nom, int $ilYaJours) {
        $contact = Contact::factory()->create(['prenom' => 'Contact', 'nom' => $nom]);
        Activite::create(['titre' => 'Mail', 'type' => Activite::TYPE_EMAIL, 'contact_id' => $contact->id, 'terminee_at' => now()->subDays($ilYaJours)]);

        return $contact;
    };
    $envoi('Recent', 10);
    $envoi('Moyen', 45);
    $envoi('Ancien', 75);
    $envoi('TresAncien', 120);
    // Écrit il y a 120 jours puis à nouveau il y a 10 jours : seul le dernier envoi compte.
    $relance = $envoi('Relance', 120);
    Activite::create(['titre' => 'Mail', 'type' => Activite::TYPE_EMAIL, 'contact_id' => $relance->id, 'terminee_at' => now()->subDays(10)]);
    Contact::factory()->create(['prenom' => 'Contact', 'nom' => 'Jamais']);

    $voir = fn (string $tranche) => Livewire::test(Liste::class)->set('mails', $tranche)->viewData('contacts')->pluck('nom')->sort()->values()->all();

    expect($voir('moins30'))->toBe(['Recent', 'Relance']);
    expect($voir('30a60'))->toBe(['Moyen']);
    expect($voir('60a90'))->toBe(['Ancien']);
    expect($voir('plus90'))->toBe(['TresAncien']);
    expect($voir('avec'))->toBe(['Ancien', 'Moyen', 'Recent', 'Relance', 'TresAncien']);
    expect($voir('sans'))->toBe(['Jamais']);
});

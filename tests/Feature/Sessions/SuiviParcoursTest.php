<?php

use App\Livewire\Sessions\Formulaire;
use App\Livewire\Sessions\Invitations;
use App\Livewire\Sessions\Parcours;
use App\Livewire\Sessions\Suivi;
use App\Livewire\TableauBord;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Invitation;
use App\Models\ModeleMessage;
use App\Models\Seance;
use App\Models\User;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use App\Services\Sessions\SuiviApresSeance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
});

/** Une séance découverte passée (hier) et la suivante, dans un mois. */
function seancesDecouverte(): array
{
    $hier = Seance::create([
        'titre' => 'Séance découverte', 'role_parcours' => Seance::ROLE_DECOUVERTE,
        'date_debut' => now()->subDay()->setTime(10, 30),
    ]);
    $prochaine = Seance::create([
        'titre' => 'Séance découverte de janvier', 'role_parcours' => Seance::ROLE_DECOUVERTE,
        'date_debut' => now()->addMonth()->setTime(10, 30),
        'lien_inscription' => 'https://exemple.be/janvier',
    ]);

    return [$hier, $prochaine];
}

// --- Tâches de suivi ---------------------------------------------------------

it('crée dès le lendemain une tâche de suivi par participant, avec le message prêt', function () {
    [$hier] = seancesDecouverte();
    $presente = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $absent = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']);
    $hier->inscrire($presente);
    $hier->inscrire($absent)->update(['presence' => Inscription::ABSENT]);

    $service = new SuiviApresSeance;
    expect($service->creerTaches('Laurence Test'))->toBe(2);
    expect($service->creerTaches('Laurence Test'))->toBe(0); // jamais deux fois

    $tacheMarie = Activite::where('contact_id', $presente->id)->firstOrFail();
    expect($tacheMarie->titre)->toBe('Suivi après « Séance découverte » : Marie Durand');
    expect($tacheMarie->type)->toBe(Activite::TYPE_TACHE);
    expect($tacheMarie->estTerminee())->toBeFalse();
    expect($tacheMarie->echeance->isToday())->toBeTrue();
    expect($tacheMarie->notes)
        ->toContain('Présence non notée')
        ->toContain('Bonjour Marie')
        ->toContain('Merci d’avoir participé à « Séance découverte »');

    // L'absent est invité à la prochaine date, avec son lien.
    expect(Activite::where('contact_id', $absent->id)->value('notes'))
        ->toContain('Absent(e) à la séance')
        ->toContain('10 h 30')
        ->toContain('https://exemple.be/janvier');
});

it('ne crée pas de suivi pour une séance à venir, trop ancienne, ou une liste d’attente', function () {
    $future = Seance::create(['titre' => 'À venir', 'date_debut' => now()->addWeek()]);
    $ancienne = Seance::create(['titre' => 'Ancienne', 'date_debut' => now()->subDays(30)]);
    $complete = Seance::create(['titre' => 'Complète', 'date_debut' => now()->subDay(), 'capacite' => 1]);

    $future->inscrire(Contact::factory()->create());
    $ancienne->inscrire(Contact::factory()->create());
    $complete->inscrire(Contact::factory()->create());
    $complete->inscrire(Contact::factory()->create()); // liste d'attente

    expect((new SuiviApresSeance)->creerTaches())->toBe(1);
    expect(Activite::value('titre'))->toStartWith('Suivi après « Complète »');
});

it('crée les tâches de suivi à l’ouverture du tableau de bord', function () {
    [$hier] = seancesDecouverte();
    $hier->inscrire(Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']));

    Livewire::test(TableauBord::class)->assertSee('Suivi après « Séance découverte » : Marie Durand');

    expect(Activite::count())->toBe(1);
});

it('utilise le modèle de suivi enregistré quand il existe', function () {
    [$hier] = seancesDecouverte();
    ModeleMessage::create(['nom' => 'Suivi — mon texte', 'canal' => ModeleMessage::CANAL_EMAIL, 'objet' => 'Merci', 'corps' => 'Merci {prenom} — {moi}']);
    ModeleMessage::create(['nom' => 'Pour un absent', 'canal' => ModeleMessage::CANAL_EMAIL, 'objet' => null, 'corps' => 'Rendez-vous {prochaine_date} ?']);
    $inscription = $hier->inscrire(Contact::factory()->create(['prenom' => 'Marie']));

    $service = new SuiviApresSeance;
    expect($service->texte($inscription->load('seance', 'contact'), 'Laurence Test')['corps'])->toBe('Merci Marie — Laurence Test');

    $inscription->update(['presence' => Inscription::ABSENT]);
    expect($service->texte($inscription, 'Laurence Test')['corps'])->toStartWith('Rendez-vous ')->not->toContain('[prochaine_date]');
});

// --- Page de suivi -----------------------------------------------------------

it('note la présence, adapte le message et la tâche, puis enregistre l’envoi', function () {
    [$hier] = seancesDecouverte();
    $marie = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $paul = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']);
    $insMarie = $hier->inscrire($marie);
    $insPaul = $hier->inscrire($paul);

    $composant = Livewire::test(Suivi::class, ['seance' => $hier]); // crée les deux tâches
    expect(Activite::count())->toBe(2);

    // Marie était absente : sa tâche et son brouillon passent au message « absent ».
    $composant->call('noterPresence', $insMarie->id, Inscription::ABSENT)
        ->call('preparer', $insMarie->id)
        ->assertSet('ouverteId', $insMarie->id);

    expect($insMarie->fresh()->presence)->toBe(Inscription::ABSENT);
    expect($composant->get('brouillonCorps'))->toContain('https://exemple.be/janvier');
    expect(Activite::where('contact_id', $marie->id)->value('notes'))->toContain('Absent(e) à la séance');

    // Recliquer sur le même choix efface la présence.
    $composant->call('noterPresence', $insMarie->id, Inscription::ABSENT);
    expect($insMarie->fresh()->presence)->toBeNull();

    // Envoi : date, trace, tâche clôturée, et on passe à Paul.
    $composant->set('brouillonCorps', 'Texte retouché')
        ->call('marquerEnvoye')
        ->assertSet('ouverteId', $insPaul->id);

    expect($insMarie->fresh()->suivi_envoye_le)->not->toBeNull();
    expect(Activite::where('contact_id', $marie->id)->where('titre', 'like', 'Suivi après%')->first()->estTerminee())->toBeTrue();
    $trace = Activite::where('contact_id', $marie->id)->where('titre', 'Suivi envoyé : Séance découverte')->firstOrFail();
    expect($trace->notes)->toContain('Texte retouché');

    expect($composant->viewData('compteurs'))->toMatchArray(['envoyes' => 1, 'non_notes' => 2]);
});

it('marque d’un coup les non notés comme présents', function () {
    [$hier] = seancesDecouverte();
    $a = $hier->inscrire(Contact::factory()->create());
    $b = $hier->inscrire(Contact::factory()->create());
    $b->update(['presence' => Inscription::ABSENT]);

    Livewire::test(Suivi::class, ['seance' => $hier])->call('toutPresent');

    expect($a->fresh()->presence)->toBe(Inscription::PRESENT);
    expect($b->fresh()->presence)->toBe(Inscription::ABSENT); // inchangé
});

it('refuse d’agir sur l’inscription d’une autre session, et affiche la page', function () {
    [$hier, $prochaine] = seancesDecouverte();
    $ailleurs = $prochaine->inscrire(Contact::factory()->create());

    Livewire::test(Suivi::class, ['seance' => $hier])
        ->call('noterPresence', $ailleurs->id, Inscription::PRESENT)
        ->call('preparer', $ailleurs->id)
        ->assertSet('ouverteId', null);
    expect($ailleurs->fresh()->presence)->toBeNull();

    $this->get(route('sessions.suivi', $hier))->assertOk()->assertSee('Suivi après la séance');
    $this->get(route('sessions.suivi', $prochaine))->assertOk()->assertSee('pas encore eu lieu');
    $this->get(route('sessions.fiche', $hier))->assertOk()->assertSee('Suivi après la séance');
});

// --- Parcours formation ------------------------------------------------------

it('calcule le parcours : invités, inscrits, présents, appels, inscrits à la formation', function () {
    [$hier] = seancesDecouverte();
    $stage = Seance::create(['titre' => 'Stage de mai', 'role_parcours' => Seance::ROLE_FORMATION, 'date_debut' => now()->addMonths(5), 'capacite' => 2]);
    [$a, $b, $c, $horsParcours] = Contact::factory()->count(4)->create()->all();

    // A et B ont été invités ; C est seulement dans la liste « à inviter ».
    Invitation::create(['seance_id' => $hier->id, 'contact_id' => $a->id, 'statut' => Invitation::STATUT_INVITEE]);
    Invitation::create(['seance_id' => $hier->id, 'contact_id' => $b->id, 'statut' => Invitation::STATUT_RELANCEE]);
    Invitation::create(['seance_id' => $hier->id, 'contact_id' => $c->id]);

    // A (présent) et C (absent) se sont inscrits à la séance.
    $hier->inscrire($a)->update(['presence' => Inscription::PRESENT]);
    $hier->inscrire($c)->update(['presence' => Inscription::ABSENT]);

    // A a réservé un appel puis s'est inscrit au stage ; un inconnu aussi.
    Activite::create(['titre' => 'Rendez-vous réservé : Appel visio', 'type' => Activite::TYPE_RDV, 'contact_id' => $a->id]);
    Activite::create(['titre' => 'Rendez-vous', 'type' => Activite::TYPE_RDV, 'contact_id' => $horsParcours->id]);
    $stage->inscrire($a);
    $stage->inscrire($horsParcours);

    $composant = Livewire::test(Parcours::class);

    expect(array_column($composant->viewData('etapes'), 'n'))->toBe([2, 2, 1, 1, 2]);
    expect($composant->viewData('formesViaDecouverte'))->toBe(1);
    $composant->assertSee('Complet')->assertSee('2 invité(s) · 2 inscrit(s) · 1 présent(s) · 1 absent(s)');
});

it('explique comment démarrer quand aucune session n’a de rôle, et exige une connexion', function () {
    Seance::create(['titre' => 'Sans rôle']);

    $this->get(route('sessions.parcours'))->assertOk()->assertSee('Aucune session n\'a encore de rôle', false);
    $this->get(route('sessions.index'))->assertOk()->assertSee('Parcours formation');

    auth()->logout();
    $this->get(route('sessions.parcours'))->assertRedirect(route('login'));
});

it('enregistre le rôle d’une session dans le parcours', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Stage de mai')
        ->set('role_parcours', Seance::ROLE_FORMATION)
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Seance::where('titre', 'Stage de mai')->first()->role_parcours)->toBe(Seance::ROLE_FORMATION);

    Livewire::test(Formulaire::class)
        ->set('titre', 'Autre')
        ->set('role_parcours', 'inconnu')
        ->call('enregistrer')
        ->assertHasErrors('role_parcours');
});

// --- Angle avant la rédaction ------------------------------------------------

it('ouvre le mail de prospection sans rédiger, pour laisser préciser l’angle', function () {
    app()->instance(AssistantIA::class, new AssistantFactice);
    $seance = Seance::create(['titre' => 'Séance découverte']);
    $invitation = Invitation::create(['seance_id' => $seance->id, 'contact_id' => Contact::factory()->create()->id]);

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->assertSee('Mail de prospection')
        ->call('preparer', $invitation->id, false)
        ->assertSet('ouverteId', $invitation->id)
        ->assertSet('brouillonCorps', '')
        ->assertSee('précise-le avant de lancer la rédaction')
        ->set('angle', 'son studio ouvre à Namur')
        ->call('redigerProspection')
        ->assertSet('brouillonErreur', null);
});

// --- Dupliquer une session ---------------------------------------------------

it('duplique une session sans reprendre la date, le groupe ni les inscrits', function () {
    $salle = Contact::factory()->create();
    $origine = Seance::create([
        'titre' => 'Séance découverte', 'role_parcours' => Seance::ROLE_DECOUVERTE,
        'date_debut' => '2026-12-13 10:30:00', 'lieu' => 'en ligne', 'lieu_contact_id' => $salle->id,
        'capacite' => 20, 'notes' => 'Prévoir un tapis', 'lien_inscription' => 'https://exemple.be/reserver',
        'lien_groupe' => 'https://chat.whatsapp.com/abc',
    ]);
    $origine->inscrire(Contact::factory()->create());

    $this->get(route('sessions.fiche', $origine))->assertOk()->assertSee('sessions/creer?dupliquer='.$origine->id, false);

    Livewire::withQueryParams(['dupliquer' => $origine->id])
        ->test(Formulaire::class)
        ->assertSee('Copie de « Séance découverte »')
        ->assertSet('titre', 'Séance découverte')
        ->assertSet('date_debut', null)
        ->assertSet('lien_groupe', null)
        ->assertSet('capacite', '20')
        ->assertSet('role_parcours', Seance::ROLE_DECOUVERTE)
        ->assertSet('lien_inscription', 'https://exemple.be/reserver')
        ->set('titre', 'Séance découverte de janvier')
        ->set('date_debut', '2027-01-17T10:30')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $copie = Seance::where('titre', 'Séance découverte de janvier')->firstOrFail();
    expect($copie->id)->not->toBe($origine->id);
    expect($copie->lieu_contact_id)->toBe($salle->id);
    expect($copie->notes)->toBe('Prévoir un tapis');
    expect($copie->inscriptions()->count())->toBe(0);
    expect($origine->fresh()->titre)->toBe('Séance découverte'); // l'originale n'a pas bougé
    expect(Seance::count())->toBe(2);
});

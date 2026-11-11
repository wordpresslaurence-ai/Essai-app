<?php

use App\Livewire\Reglages\ModelesMessage as GestionModeles;
use App\Livewire\Sessions\Formulaire;
use App\Livewire\Sessions\Invitations;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\Invitation;
use App\Models\ModeleMessage;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
});

function seanceDecouverte(array $attributs = []): Seance
{
    return Seance::create($attributs + [
        'titre' => 'Séance découverte',
        'date_debut' => '2026-12-13 10:30:00',
        'lieu' => 'en ligne',
        'lien_inscription' => 'https://exemple.be/reserver',
    ]);
}

function modeleInvitation(array $attributs = []): ModeleMessage
{
    return ModeleMessage::create($attributs + [
        'nom' => 'Invitation',
        'canal' => ModeleMessage::CANAL_EMAIL,
        'objet' => '{session}',
        'corps' => 'Bonjour {prenom}, rendez-vous à {heure} ({lieu}). Réserver : {lien} — {moi}',
    ]);
}

// --- Modèles de messages -----------------------------------------------------

it('remplace les variables d’un modèle et signale celles qui sont vides', function () {
    $contact = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'adresse_ville' => null]);
    $modele = modeleInvitation(['corps' => 'Bonjour {prenom} de {ville}, à {heure}. {lien} {inconnue}']);

    $rendu = $modele->rendre($contact, seanceDecouverte(), 'Laurence Test');

    expect($rendu['objet'])->toBe('Séance découverte');
    expect($rendu['corps'])->toBe('Bonjour Marie de [ville], à 10 h 30. https://exemple.be/reserver {inconnue}');
});

it('utilise le nom complet quand le contact n’a pas de prénom (entreprise)', function () {
    $studio = Contact::factory()->entreprise()->create(['nom' => 'Studio Zen']);
    $modele = modeleInvitation(['corps' => 'Bonjour {prenom} ({entreprise})']);

    expect($modele->rendre($studio, seanceDecouverte())['corps'])->toBe('Bonjour Studio Zen (Studio Zen)');
});

it('crée, modifie et supprime un modèle de message', function () {
    Livewire::test(GestionModeles::class)
        ->set('nom', 'Relance')
        ->set('canal', ModeleMessage::CANAL_MESSAGE)
        ->set('objet', 'ignoré pour un message court')
        ->set('corps', 'Bonjour {prenom}')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $modele = ModeleMessage::where('nom', 'Relance')->firstOrFail();
    expect($modele->objet)->toBeNull(); // un message court n'a pas d'objet

    Livewire::test(GestionModeles::class)
        ->call('editer', $modele->id)
        ->assertSet('corps', 'Bonjour {prenom}')
        ->set('corps', 'Bonsoir {prenom}')
        ->call('enregistrer');

    expect($modele->fresh()->corps)->toBe('Bonsoir {prenom}');

    Livewire::test(GestionModeles::class)->call('supprimer', $modele->id);
    expect(ModeleMessage::count())->toBe(0);
});

it('ajoute les modèles de départ une seule fois', function () {
    $nb = count(ModeleMessage::modelesDeDepart());

    Livewire::test(GestionModeles::class)
        ->call('ajouterModelesDeDepart')
        ->call('ajouterModelesDeDepart');

    expect(ModeleMessage::count())->toBe($nb);
});

// --- Session : lien d'inscription --------------------------------------------

it('enregistre le lien d’inscription d’une session', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Séance de janvier')
        ->set('lien_inscription', 'https://exemple.be/janvier')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Seance::where('titre', 'Séance de janvier')->first()->lien_inscription)->toBe('https://exemple.be/janvier');

    Livewire::test(Formulaire::class)
        ->set('titre', 'Mauvais lien')
        ->set('lien_inscription', 'pas une adresse')
        ->call('enregistrer')
        ->assertHasErrors('lien_inscription');
});

// --- Campagne ----------------------------------------------------------------

it('affiche l’écran des invitations et exige une connexion', function () {
    $seance = seanceDecouverte();

    $this->get(route('sessions.invitations', $seance))->assertOk()->assertSee('Séance découverte');

    auth()->logout();
    $this->get(route('sessions.invitations', $seance))->assertRedirect(route('login'));
});

it('ne propose pas les contacts archivés, « ne plus contacter » ou déjà dans la campagne', function () {
    $seance = seanceDecouverte();
    Contact::factory()->create(['nom' => 'Proposable']);
    Contact::factory()->create(['nom' => 'Refusant', 'ne_plus_contacter' => true]);
    $archive = Contact::factory()->create(['nom' => 'Archivee']);
    $archive->archiver();
    $dejaLa = Contact::factory()->create(['nom' => 'DejaInvitee']);
    Invitation::create(['seance_id' => $seance->id, 'contact_id' => $dejaLa->id]);

    $noms = Livewire::test(Invitations::class, ['seance' => $seance])
        ->viewData('candidats')->pluck('nom')->all();

    expect($noms)->toBe(['Proposable']);
});

it('filtre les contacts à ajouter par pays', function () {
    $seance = seanceDecouverte();
    Contact::factory()->create(['nom' => 'Belge', 'adresse_pays' => 'BE']);
    Contact::factory()->create(['nom' => 'Francaise', 'adresse_pays' => 'France']);

    $noms = Livewire::test(Invitations::class, ['seance' => $seance])
        ->set('pays', 'FR')
        ->viewData('candidats')->pluck('nom')->all();

    expect($noms)->toBe(['Francaise']);
});

it('ajoute les contacts cochés à la campagne, sans doublon', function () {
    $seance = seanceDecouverte();
    $a = Contact::factory()->create();
    $b = Contact::factory()->create();
    $interdit = Contact::factory()->create(['ne_plus_contacter' => true]);

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->set('selection', [(string) $a->id, (string) $b->id, (string) $interdit->id])
        ->call('ajouterSelection')
        ->assertSet('selection', []);

    expect($seance->invitations()->pluck('contact_id')->sort()->values()->all())->toBe([$a->id, $b->id]);
    expect($seance->invitations()->first()->statut)->toBe(Invitation::STATUT_A_INVITER);
});

it('coche tous les contacts affichés puis les décoche', function () {
    $seance = seanceDecouverte();
    Contact::factory()->count(3)->create();

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('basculerTout')
        ->assertCount('selection', 3)
        ->call('basculerTout')
        ->assertCount('selection', 0);
});

it('prépare un brouillon personnalisé pour un contact', function () {
    $seance = seanceDecouverte();
    $contact = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand']);
    $invitation = Invitation::create(['seance_id' => $seance->id, 'contact_id' => $contact->id]);
    modeleInvitation();

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('preparer', $invitation->id)
        ->assertSet('ouverteId', $invitation->id)
        ->assertSet('brouillonObjet', 'Séance découverte')
        ->assertSet('brouillonCorps', 'Bonjour Marie, rendez-vous à 10 h 30 (en ligne). Réserver : https://exemple.be/reserver — Laurence Test');
});

it('recalcule le brouillon quand on change de modèle', function () {
    $seance = seanceDecouverte();
    $contact = Contact::factory()->create(['prenom' => 'Marie']);
    $invitation = Invitation::create(['seance_id' => $seance->id, 'contact_id' => $contact->id]);
    modeleInvitation(['nom' => 'A']);
    $court = modeleInvitation(['nom' => 'B', 'canal' => ModeleMessage::CANAL_MESSAGE, 'objet' => null, 'corps' => 'Coucou {prenom}']);

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('preparer', $invitation->id)
        ->set('modeleId', (string) $court->id)
        ->assertSet('brouillonCorps', 'Coucou Marie')
        ->assertSet('brouillonObjet', '');
});

it('note l’envoi : statut invité, trace sur la fiche, puis ouvre la personne suivante', function () {
    $seance = seanceDecouverte();
    $marie = Contact::factory()->create(['prenom' => 'Marie']);
    $paul = Contact::factory()->create(['prenom' => 'Paul']);
    $premiere = Invitation::create(['seance_id' => $seance->id, 'contact_id' => $marie->id]);
    $seconde = Invitation::create(['seance_id' => $seance->id, 'contact_id' => $paul->id]);
    $modele = modeleInvitation();

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('preparer', $premiere->id)
        ->set('brouillonCorps', 'Texte retouché à la main')
        ->call('marquerEnvoyee')
        ->assertSet('ouverteId', $seconde->id); // enchaîne sur Paul

    $premiere->refresh();
    expect($premiere->statut)->toBe(Invitation::STATUT_INVITEE);
    expect($premiere->invitee_le)->not->toBeNull();
    expect($premiere->modele_message_id)->toBe($modele->id);

    $trace = Activite::where('contact_id', $marie->id)->firstOrFail();
    expect($trace->titre)->toBe('Invitation envoyée : Séance découverte');
    expect($trace->type)->toBe(Activite::TYPE_EMAIL);
    expect($trace->estTerminee())->toBeTrue();
    expect($trace->notes)->toContain('Texte retouché à la main');
});

it('note une relance sur une invitation déjà envoyée', function () {
    $seance = seanceDecouverte();
    $contact = Contact::factory()->create();
    $invitation = Invitation::create([
        'seance_id' => $seance->id, 'contact_id' => $contact->id,
        'statut' => Invitation::STATUT_INVITEE, 'invitee_le' => now()->subDays(6),
    ]);
    modeleInvitation();

    expect($invitation->estARelancer())->toBeTrue();

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('preparer', $invitation->id)
        ->call('marquerEnvoyee')
        ->assertSet('ouverteId', null);

    $invitation->refresh();
    expect($invitation->statut)->toBe(Invitation::STATUT_RELANCEE);
    expect($invitation->relancee_le)->not->toBeNull();
    expect($invitation->estARelancer())->toBeFalse();
    expect(Activite::where('contact_id', $contact->id)->value('titre'))->toBe('Relance envoyée : Séance découverte');
});

it('compte les invitations à relancer, sans les personnes déjà inscrites', function () {
    $seance = seanceDecouverte();
    $silencieuse = Contact::factory()->create();
    $inscrite = Contact::factory()->create();
    $recente = Contact::factory()->create();

    foreach ([[$silencieuse, 6], [$inscrite, 6], [$recente, 1]] as [$contact, $jours]) {
        Invitation::create([
            'seance_id' => $seance->id, 'contact_id' => $contact->id,
            'statut' => Invitation::STATUT_INVITEE, 'invitee_le' => now()->subDays($jours),
        ]);
    }
    $seance->inscrire($inscrite);

    $composant = Livewire::test(Invitations::class, ['seance' => $seance]);

    expect($composant->viewData('compteurs'))->toMatchArray(['a_relancer' => 1, 'invites' => 3, 'inscrits' => 1]);
    expect($composant->set('filtre', 'a_relancer')->viewData('invitations')->pluck('contact_id')->all())
        ->toBe([$silencieuse->id]);
});

it('change le statut, inscrit et retire un contact depuis la campagne', function () {
    $seance = seanceDecouverte();
    $contact = Contact::factory()->create();
    $invitation = Invitation::create(['seance_id' => $seance->id, 'contact_id' => $contact->id]);

    $composant = Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('changerStatut', $invitation->id, Invitation::STATUT_A_REPONDU)
        ->call('changerStatut', $invitation->id, 'statut_invente'); // ignoré

    expect($invitation->fresh()->statut)->toBe(Invitation::STATUT_A_REPONDU);

    $composant->call('inscrire', $invitation->id)->assertSee('Inscrit');
    expect($seance->inscriptions()->where('contact_id', $contact->id)->value('statut'))->toBe(Inscription::STATUT_CONFIRME);

    $composant->call('retirer', $invitation->id);
    expect(Invitation::find($invitation->id))->toBeNull();
});

it('refuse d’agir sur l’invitation d’une autre session', function () {
    $seance = seanceDecouverte();
    $autre = seanceDecouverte(['titre' => 'Autre session']);
    $contact = Contact::factory()->create();
    $ailleurs = Invitation::create(['seance_id' => $autre->id, 'contact_id' => $contact->id]);

    Livewire::test(Invitations::class, ['seance' => $seance])
        ->call('preparer', $ailleurs->id)
        ->assertSet('ouverteId', null)
        ->call('retirer', $ailleurs->id)
        ->call('changerStatut', $ailleurs->id, Invitation::STATUT_DECLINEE);

    expect($ailleurs->fresh()->statut)->toBe(Invitation::STATUT_A_INVITER);
});

it('supprime les invitations avec la session ou le contact', function () {
    $seance = seanceDecouverte();
    $contact = Contact::factory()->create();
    $autre = Contact::factory()->create();
    Invitation::create(['seance_id' => $seance->id, 'contact_id' => $contact->id]);
    Invitation::create(['seance_id' => $seance->id, 'contact_id' => $autre->id]);

    $contact->delete();
    expect(Invitation::count())->toBe(1);

    $seance->delete();
    expect(Invitation::count())->toBe(0);
});

// --- Numéro WhatsApp ---------------------------------------------------------

it('met un téléphone au format WhatsApp', function (?string $telephone, ?string $pays, ?string $attendu) {
    $contact = Contact::factory()->make(['telephone' => $telephone, 'adresse_pays' => $pays]);

    expect($contact->numeroWhatsapp())->toBe($attendu);
})->with([
    'belge national' => ['0470 12 34 56', 'BE', '32470123456'],
    'français national' => ['06 12 34 56 78', 'France', '33612345678'],
    'international +' => ['+32 470 12 34 56', 'BE', '32470123456'],
    'international 00' => ['0033 6 12 34 56 78', 'BE', '33612345678'],
    'zéro entre parenthèses' => ['+32 (0)470 12 34 56', 'BE', '32470123456'],
    'vide' => [null, 'BE', null],
    'trop court' => ['12345', 'BE', null],
]);

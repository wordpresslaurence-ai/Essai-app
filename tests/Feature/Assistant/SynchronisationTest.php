<?php

use App\Models\Activite;
use App\Models\BoiteEmail;
use App\Models\Contact;
use App\Models\Email;
use App\Services\Email\LecteurFactice;
use App\Services\Email\MessageEmail;
use App\Services\Email\SynchronisationEmails;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

const BOITE = 'laurencehomeharmony@gmail.com';

beforeEach(function () {
    $this->boite = BoiteEmail::factory()->create(['email' => BOITE]);
});

function recu(string $id, string $de, array $a, string $objet = 'Bonjour'): MessageEmail
{
    return new MessageEmail($id, $de, $a, $objet, 'Texte', Carbon::parse('2026-06-01 10:00'));
}

function sync(array $messages): App\Services\Email\ResultatSynchronisation
{
    return (new SynchronisationEmails(new LecteurFactice($messages)))->executer();
}

it('rattache un mail entrant au bon contact et le marque entrant', function () {
    $contact = Contact::factory()->create(['email' => 'client@example.com']);

    $r = sync([recu('m1', 'client@example.com', [BOITE])]);

    expect($r->emailsImportes)->toBe(1);
    $email = Email::first();
    expect($email->contact_id)->toBe($contact->id);
    expect($email->direction)->toBe(Email::DIRECTION_ENTRANT);
    expect($email->boite_email_id)->toBe($this->boite->id);
});

it('marque sortant un mail envoyé depuis la boîte', function () {
    Contact::factory()->create(['email' => 'client@example.com']);

    sync([recu('m2', BOITE, ['client@example.com'])]);

    expect(Email::first()->direction)->toBe(Email::DIRECTION_SORTANT);
});

it('ne crée pas de doublon pour le même message', function () {
    Contact::factory()->create(['email' => 'client@example.com']);
    $msg = recu('m3', 'client@example.com', [BOITE]);

    sync([$msg]);

    // On force le message à être re-vu (comme un chevauchement de fenêtre de synchro).
    // Écriture directe en base (le modèle en mémoire est périmé après le 1er passage).
    BoiteEmail::whereKey($this->boite->id)->update(['derniere_synchro_at' => null]);
    $r = sync([$msg]); // deuxième passage avec le même message_id

    expect(Email::count())->toBe(1);  // pas de doublon
    expect($r->ignores)->toBe(1);     // message re-vu mais ignoré (déjà présent)
    expect($r->emailsImportes)->toBe(0);
});

it('détaille les mails rattachés (qui / objet / sens)', function () {
    Contact::factory()->create(['nom' => 'Durand', 'prenom' => 'Marie', 'email' => 'client@example.com']);

    $r = sync([recu('d1', 'client@example.com', [BOITE], 'Demande de devis')]);

    expect($r->imports)->toHaveCount(1);
    expect($r->imports[0]['nom'])->toBe('Marie Durand');
    expect($r->imports[0]['objet'])->toBe('Demande de devis');
    expect($r->imports[0]['direction'])->toBe('entrant');
});

it('détaille les prospects à créer', function () {
    $r = sync([recu('p1', 'inconnu@example.com', [BOITE], 'Bonjour')]);

    expect($r->prospects)->toHaveCount(1);
    expect($r->prospects[0]['adresse'])->toBe('inconnu@example.com');
});

it('crée une tâche « créer le prospect » pour un expéditeur inconnu', function () {
    $r = sync([recu('m4', 'inconnu@example.com', [BOITE], 'Demande d’info')]);

    expect($r->emailsImportes)->toBe(0);
    expect($r->tachesCreees)->toBe(1);
    $tache = Activite::where('type', Activite::TYPE_TACHE)->first();
    expect($tache->contact_id)->toBeNull();
    expect($tache->titre)->toContain('inconnu@example.com');
});

it('ne crée pas deux fois la tâche pour la même adresse inconnue', function () {
    sync([recu('m5', 'inconnu@example.com', [BOITE])]);
    $r = sync([recu('m6', 'inconnu@example.com', [BOITE])]);

    expect(Activite::where('type', Activite::TYPE_TACHE)->count())->toBe(1);
    expect($r->tachesCreees)->toBe(0);
});

it('rattache un mail venu d’une adresse secondaire au bon contact (pas de prospect)', function () {
    $contact = Contact::factory()->create(['email' => 'principal@example.com']);
    $contact->adressesEmail()->create(['adresse' => 'perso@example.com']);

    $r = sync([recu('s1', 'perso@example.com', [BOITE])]);

    expect($r->emailsImportes)->toBe(1);
    expect($r->tachesCreees)->toBe(0); // reconnu : aucune tâche « créer le prospect »
    expect(Email::first()->contact_id)->toBe($contact->id);
});

it('met à jour la date de dernière synchronisation de la boîte', function () {
    expect($this->boite->derniere_synchro_at)->toBeNull();
    sync([]);
    expect($this->boite->fresh()->derniere_synchro_at)->not->toBeNull();
});

it('lit plusieurs boîtes et déduplique entre elles', function () {
    $hostinger = BoiteEmail::factory()->hostinger()->create(['email' => 'laurence@homeharmony.be']);
    Contact::factory()->create(['email' => 'client@example.com']);

    $msgGmail = recu('g1', 'client@example.com', [BOITE]);
    $msgHostinger = recu('h1', 'client@example.com', ['laurence@homeharmony.be']);
    $partage = recu('partage', 'client@example.com', [BOITE]); // présent dans les 2 boîtes

    $lecteur = new LecteurFactice(parBoite: [
        BOITE => [$msgGmail, $partage],
        'laurence@homeharmony.be' => [$msgHostinger, $partage],
    ]);

    $r = (new SynchronisationEmails($lecteur))->executer();

    expect($r->boitesLues)->toBe(2);
    expect($r->emailsImportes)->toBe(3); // g1 + h1 + partage (une seule fois)
    expect(Email::count())->toBe(3);
    expect(Email::where('message_id', 'partage')->count())->toBe(1);
});

it('rattache sans filtrer par pôle (« ne rien rater »)', function () {
    // La boîte appartient à un pôle, mais le contact est dans un AUTRE pôle.
    $poleBoite = App\Models\Pole::create(['nom' => 'Prana']);
    $this->boite->update(['pole_id' => $poleBoite->id]);

    $autrePole = App\Models\Pole::create(['nom' => 'Digital']);
    $cat = App\Models\Categorie::create(['pole_id' => $autrePole->id, 'nom' => 'Sites']);
    $contact = Contact::factory()->create(['email' => 'client@example.com', 'categorie_id' => $cat->id]);

    $r = sync([recu('x1', 'client@example.com', [BOITE])]);

    expect($r->emailsImportes)->toBe(1);
    expect(Email::first()->contact_id)->toBe($contact->id);
});

it('stocke le corps du mail dans la tâche « créer le prospect »', function () {
    $msg = new MessageEmail('b1', 'inconnu@example.com', [BOITE], 'Bonjour', 'Je suis Jean, prof de yoga.', Carbon::parse('2026-06-01 10:00'));

    sync([$msg]);

    $tache = Activite::where('type', Activite::TYPE_TACHE)->first();
    expect($tache)->not->toBeNull();
    expect($tache->notes)->toContain('Je suis Jean');
});

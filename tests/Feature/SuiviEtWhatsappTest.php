<?php

use App\Livewire\Assistant\Brief;
use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Sessions\Formulaire;
use App\Livewire\Sessions\MessageInscrits;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Email;
use App\Models\ModeleMessage;
use App\Models\Parametre;
use App\Models\Seance;
use App\Models\User;
use App\Services\IA\AssistantClaude;
use App\Services\Relances\CalculRelances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
});

function mailEchange(Contact $c, string $direction, int $ilYaJours): Email
{
    return Email::create([
        'contact_id' => $c->id,
        'direction' => $direction,
        'de' => 'x@example.com',
        'a' => 'y@example.com',
        'objet' => 'Échange',
        'extrait' => 'Aperçu',
        'date_email' => now()->subDays($ilYaJours),
        'message_id' => uniqid('m', true),
        'boite' => 'boite@example.com',
    ]);
}

// --- Conversation terminée ---------------------------------------------------

it('sort un contact des relances, clôture sa tâche, puis le reprend au prochain e-mail', function () {
    $contact = Contact::factory()->create();
    mailEchange($contact, Email::DIRECTION_SORTANT, 10); // à relancer
    $tache = Activite::create(['titre' => 'Relancer '.$contact->nomComplet(), 'type' => Activite::TYPE_TACHE, 'contact_id' => $contact->id]);

    expect((new CalculRelances(5))->aRelancer())->toHaveCount(1);

    Livewire::test(Brief::class)->call('terminerConversation', $contact->id);

    expect($contact->fresh()->suiviEnPause())->toBeTrue();
    expect((new CalculRelances(5))->aRelancer())->toHaveCount(0);
    expect($tache->fresh()->estTerminee())->toBeTrue();

    // Un nouvel e-mail (ici reçu) rouvre la conversation.
    mailEchange($contact, Email::DIRECTION_ENTRANT, 0);

    expect($contact->fresh()->suiviEnPause())->toBeFalse();
    expect((new CalculRelances(5))->reponsesDues())->toHaveCount(1);
});

it('sort aussi un contact de la liste « réponse due »', function () {
    $contact = Contact::factory()->create();
    $autre = Contact::factory()->create();
    mailEchange($contact, Email::DIRECTION_ENTRANT, 1);
    mailEchange($autre, Email::DIRECTION_ENTRANT, 1);

    Livewire::test(Brief::class)
        ->assertSee('Terminée')
        ->call('terminerConversation', $contact->id);

    $restants = (new CalculRelances(5))->reponsesDues()->map(fn ($l) => $l['contact']->id)->all();
    expect($restants)->toBe([$autre->id]);
});

it('sort aussi un lead chaud et un intéressé de session passée des indécis', function () {
    $lead = Contact::factory()->create(['temperature' => 'chaud', 'created_at' => now()->subDays(30)]);
    $interesse = Contact::factory()->create();
    $passee = Seance::create(['titre' => 'Passée', 'date_debut' => now()->subWeek(), 'capacite' => 1]);
    $passee->inscrire(Contact::factory()->create()); // prend la place
    $passee->inscrire($interesse);                   // liste d'attente

    $indecis = new App\Services\Relances\CalculIndecis(5);
    expect($indecis->leadsChaudsSansSuite()->pluck('id')->all())->toBe([$lead->id]);
    expect($indecis->interessesSessionPassee()->pluck('id')->all())->toBe([$interesse->id]);

    // Une relance créée pour un indécis n'est plus clôturée à l'ouverture du Brief…
    $tache = Activite::create(['titre' => 'Relancer '.$lead->nomComplet(), 'type' => Activite::TYPE_TACHE, 'contact_id' => $lead->id]);
    $brief = Livewire::test(Brief::class);
    expect($tache->fresh()->estTerminee())->toBeFalse();

    // …mais elle l'est quand on termine la conversation.
    $brief->call('terminerConversation', $lead->id)->call('terminerConversation', $interesse->id);

    expect($tache->fresh()->estTerminee())->toBeTrue();
    expect($indecis->leadsChaudsSansSuite())->toHaveCount(0);
    expect($indecis->interessesSessionPassee())->toHaveCount(0);
});

it('affiche la pause sur la fiche et permet de reprendre le suivi', function () {
    $contact = Contact::factory()->create();
    $contact->mettreSuiviEnPause();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Conversation terminée depuis le')
        ->call('basculerSuivi')
        ->assertDontSee('Conversation terminée depuis le');

    expect($contact->fresh()->suiviEnPause())->toBeFalse();
});

it('ne laisse pas la pause se poser par assignation de masse', function () {
    $contact = Contact::factory()->create();
    $contact->update(['suivi_pause_le' => now()]);

    expect($contact->fresh()->suivi_pause_le)->toBeNull();
});

// --- Échange WhatsApp noté à la main -----------------------------------------

it('note un échange WhatsApp dans l’historique du contact', function () {
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->set('whatsappOuvert', true)
        ->call('noterWhatsapp')
        ->assertHasErrors('whatsappTexte')
        ->set('whatsappTexte', 'Elle confirme sa venue samedi.')
        ->call('noterWhatsapp')
        ->assertHasNoErrors()
        ->assertSet('whatsappOuvert', false)
        ->assertSee('Échange WhatsApp du');

    $note = Activite::where('contact_id', $contact->id)->firstOrFail();
    expect($note->titre)->toStartWith(Activite::TITRE_WHATSAPP);
    expect($note->notes)->toBe('Elle confirme sa venue samedi.');
    expect($note->estTerminee())->toBeTrue();
});

it('transmet à l’assistant les échanges WhatsApp notés', function () {
    Parametre::instance()->update(['anthropic_api_key' => 'sk-ant-test']);
    $contact = Contact::factory()->create();
    mailEchange($contact, Email::DIRECTION_ENTRANT, 1);
    Activite::create([
        'titre' => Activite::TITRE_WHATSAPP.' du 01/11/2026', 'type' => Activite::TYPE_NOTE,
        'contact_id' => $contact->id, 'terminee_at' => now(), 'notes' => 'Repère WhatsApp WXYZ789.',
    ]);

    Http::fake(['api.anthropic.com/*' => Http::response([
        'content' => [['type' => 'text', 'text' => json_encode(['objet' => 'Re:', 'corps' => 'ok Laurence'])]],
    ], 200)]);

    (new AssistantClaude)->redigerReponse($contact);

    Http::assertSent(fn ($request) => str_contains(json_encode($request->data(), JSON_UNESCAPED_UNICODE), 'WXYZ789'));
});

// --- Message WhatsApp aux inscrits -------------------------------------------

it('prépare un message WhatsApp personnalisé pour chaque inscrit', function () {
    $seance = Seance::create([
        'titre' => 'Stage de mai', 'date_debut' => '2027-04-30 09:00:00',
        'lien_groupe' => 'https://chat.whatsapp.com/abc',
    ]);
    $marie = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'telephone' => '0470 12 34 56', 'adresse_pays' => 'BE']);
    $sansNumero = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Zed', 'telephone' => null]);
    $seance->inscrire($marie);
    $seance->inscrire($sansNumero);

    $composant = Livewire::test(MessageInscrits::class, ['seance' => $seance])
        ->set('message', 'Bonjour {prenom}, rejoins le groupe : {lien_groupe}');

    $lignes = $composant->viewData('lignes')->keyBy(fn ($l) => $l['contact']->id);

    expect($lignes[$marie->id]['texte'])->toBe('Bonjour Marie, rejoins le groupe : https://chat.whatsapp.com/abc');
    expect($lignes[$marie->id]['lien'])->toStartWith('https://wa.me/32470123456?text=Bonjour%20Marie');
    expect($lignes[$sansNumero->id]['lien'])->toBeNull();
    expect($composant->viewData('nbJoignables'))->toBe(1);
    $composant->assertSee('Suivant : Marie Durand')->assertSee('Pas de numéro');
});

it('note l’envoi une seule fois et garde le texte dans l’historique', function () {
    $seance = Seance::create(['titre' => 'Stage de mai']);
    $marie = Contact::factory()->create(['prenom' => 'Marie', 'telephone' => '+32 470 12 34 56']);
    $seance->inscrire($marie);

    $composant = Livewire::test(MessageInscrits::class, ['seance' => $seance])
        ->set('message', 'Bonjour {prenom}')
        ->call('marquerEnvoye', $marie->id)
        ->call('marquerEnvoye', $marie->id)
        ->assertSee('Tout le monde a été servi');

    $traces = Activite::where('contact_id', $marie->id)->get();
    expect($traces)->toHaveCount(1);
    expect($traces->first()->titre)->toBe('Message WhatsApp : Stage de mai');
    expect($traces->first()->notes)->toBe('Bonjour Marie');

    // Changer le texte remet les envois à zéro.
    $composant->set('message', 'Autre message')->assertSet('envoyes', []);
});

it('n’inclut la liste d’attente que sur demande, et charge un modèle de message court', function () {
    $seance = Seance::create(['titre' => 'Stage', 'capacite' => 1]);
    $confirme = Contact::factory()->create();
    $attente = Contact::factory()->create();
    $seance->inscrire($confirme);
    $seance->inscrire($attente);
    $modele = ModeleMessage::create(['nom' => 'Rappel', 'canal' => ModeleMessage::CANAL_MESSAGE, 'corps' => 'À demain {prenom}']);

    $composant = Livewire::test(MessageInscrits::class, ['seance' => $seance]);
    expect($composant->viewData('lignes'))->toHaveCount(1);

    $composant->set('avecAttente', true)
        ->set('modeleId', (string) $modele->id)
        ->assertSet('message', 'À demain {prenom}');
    expect($composant->viewData('lignes'))->toHaveCount(2);
});

it('affiche la page du message aux inscrits et exige une connexion', function () {
    $seance = Seance::create(['titre' => 'Stage de mai']);

    $this->get(route('sessions.message', $seance))->assertOk()->assertSee('Message WhatsApp aux inscrits');
    $this->get(route('sessions.fiche', $seance))->assertOk()->assertSee('Message aux inscrits');

    auth()->logout();
    $this->get(route('sessions.message', $seance))->assertRedirect(route('login'));
});

it('enregistre le lien du groupe WhatsApp d’une session', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Stage de mai')
        ->set('lien_groupe', 'https://chat.whatsapp.com/abc')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Seance::where('titre', 'Stage de mai')->first()->lien_groupe)->toBe('https://chat.whatsapp.com/abc');
});

<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\BoiteEmail;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Pole;
use App\Models\User;
use App\Services\Email\DeposeBrouillon;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use App\Services\IA\ResultatProspection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    app()->instance(AssistantIA::class, new AssistantFactice(prospection: new ResultatProspection(
        objet: 'Formation Prana Vidya', corps: "Bonjour Marie,\n\nLe programme : https://exemple.be/programme\n\nLaurence", modele: 'factice',
    )));
});

/** Faux dépôt : garde ce qui a été demandé au lieu de se connecter. */
function fauxDepot(): object
{
    $faux = new class extends DeposeBrouillon
    {
        public array $appels = [];

        public function deposer(BoiteEmail $boite, string $destinataire, string $objet, string $corps): string
        {
            $this->appels[] = compact('boite', 'destinataire', 'objet', 'corps');

            return 'INBOX.Drafts';
        }
    };
    app()->instance(DeposeBrouillon::class, $faux);

    return $faux;
}

it('dépose le mail de prospection dans les brouillons de la boîte du pôle du contact', function () {
    $faux = fauxDepot();
    $yoga = Pole::create(['nom' => 'Yoga']);
    $web = Pole::create(['nom' => 'Web']);
    $boiteWeb = BoiteEmail::factory()->create(['email' => 'web@exemple.be', 'pole_id' => $web->id, 'active' => true]);
    $boiteYoga = BoiteEmail::factory()->create(['email' => 'yoga@exemple.be', 'pole_id' => $yoga->id, 'active' => true]);
    $categorie = Categorie::create(['pole_id' => $yoga->id, 'nom' => 'Profs de yoga']);
    $contact = Contact::factory()->create(['email' => 'marie@exemple.be', 'categorie_id' => $categorie->id]);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('redigerProspection')
        ->assertSee('Mettre dans mes brouillons')
        ->assertSee('Ouvrir dans ma messagerie')
        ->call('deposerBrouillon')
        ->assertSee('Brouillon déposé dans « INBOX.Drafts » de yoga@exemple.be');

    expect($faux->appels)->toHaveCount(1);
    expect($faux->appels[0]['boite']->id)->toBe($boiteYoga->id);
    expect($faux->appels[0]['destinataire'])->toBe('marie@exemple.be');
    expect($faux->appels[0]['objet'])->toBe('Formation Prana Vidya');

    // On peut choisir une autre boîte.
    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('redigerProspection')
        ->set('boiteBrouillonId', (string) $boiteWeb->id)
        ->call('deposerBrouillon');
    expect($faux->appels[1]['boite']->id)->toBe($boiteWeb->id);
});

it('explique l’échec du dépôt sans rien perdre du brouillon', function () {
    app()->instance(DeposeBrouillon::class, new class extends DeposeBrouillon
    {
        public function deposer(BoiteEmail $boite, string $destinataire, string $objet, string $corps): string
        {
            throw new RuntimeException('Connexion à la boîte impossible : mot de passe refusé.');
        }
    });
    BoiteEmail::factory()->create(['active' => true]);
    $contact = Contact::factory()->create(['email' => 'marie@exemple.be']);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('redigerProspection')
        ->call('deposerBrouillon')
        ->assertSee('mot de passe refusé')
        ->assertSet('prospectionObjet', 'Formation Prana Vidya');
});

it('construit un e-mail aux liens cliquables, avec un objet accentué', function () {
    $message = DeposeBrouillon::construireMessage('yoga@exemple.be', 'marie@exemple.be', 'Séance découverte', "Bonjour,\nLe programme : https://exemple.be/p?a=1&b=2\nÀ bientôt");

    expect($message)->toContain("To: marie@exemple.be\r\n");
    expect($message)->toContain('Subject: =?UTF-8?B?');
    expect($message)->toContain('multipart/alternative');

    $html = DeposeBrouillon::htmlAvecLiens("Bonjour,\nLe programme : https://exemple.be/p?a=1&b=2\n- https://seul.be");
    expect($html)->toBe('Bonjour,<br><a href="https://exemple.be/p?a=1&amp;b=2">Le programme</a><br>- <a href="https://seul.be">https://seul.be</a>');
});

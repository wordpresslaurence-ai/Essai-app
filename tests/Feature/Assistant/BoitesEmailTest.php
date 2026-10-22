<?php

use App\Livewire\Reglages\BoitesEmail;
use App\Models\BoiteEmail;
use App\Models\Pole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('applique les presets IMAP selon le fournisseur', function () {
    $gmail = BoiteEmail::factory()->create(['fournisseur' => 'gmail']);
    expect($gmail->hoteImap())->toBe('imap.gmail.com');
    expect($gmail->dossierEnvoyes())->toBe('[Gmail]/Sent Mail');

    $hostinger = BoiteEmail::factory()->hostinger()->create();
    expect($hostinger->hoteImap())->toBe('imap.hostinger.com');
    expect($hostinger->dossierEnvoyes())->toBe('INBOX.Sent');
    expect($hostinger->chaineImap('INBOX'))->toBe('{imap.hostinger.com:993/imap/ssl/novalidate-cert}INBOX');
});

it('lit le dossier « Envoyés » de Gmail en anglais et en français', function () {
    $gmail = BoiteEmail::factory()->create(['fournisseur' => 'gmail']);

    // On veut détecter les réponses, que le compte Gmail soit en anglais
    // (« [Gmail]/Sent Mail ») ou en français (« [Gmail]/Messages envoyés »).
    expect($gmail->dossiers())->toContain('INBOX')
        ->toContain('[Gmail]/Sent Mail')
        ->toContain('[Gmail]/Messages envoyés');
});

it('encode en UTF-7 modifié le dossier accentué « Messages envoyés »', function () {
    $gmail = BoiteEmail::factory()->create(['fournisseur' => 'gmail']);

    // « é » doit devenir « &AOk- » (modified UTF-7, RFC 3501), sinon le serveur
    // IMAP ne trouve pas le dossier et les réponses ne sont jamais lues.
    $chaine = $gmail->chaineImap('[Gmail]/Messages envoyés');

    expect($chaine)->toContain('[Gmail]/Messages envoy&AOk-s')
        ->not->toContain('envoyés');

    // Un nom ASCII reste intact.
    expect($gmail->chaineImap('[Gmail]/Sent Mail'))
        ->toBe('{imap.gmail.com:993/imap/ssl/novalidate-cert}[Gmail]/Sent Mail');
});

it('privilégie le dossier « Envoyés » saisi à la main', function () {
    $boite = BoiteEmail::factory()->create([
        'fournisseur' => 'autre',
        'imap_host' => 'imap.exemple.com',
        'dossier_envoyes' => 'Dossier-Perso',
    ]);

    expect($boite->dossiers())->toBe(['INBOX', 'Dossier-Perso']);
});

it('utilise le serveur saisi en mode « autre »', function () {
    $boite = BoiteEmail::factory()->create([
        'fournisseur' => 'autre',
        'imap_host' => 'imap.exemple.com',
        'imap_port' => 143,
        'dossier_envoyes' => 'Sent',
    ]);

    expect($boite->hoteImap())->toBe('imap.exemple.com');
    expect($boite->portImap())->toBe(143);
    expect($boite->dossierEnvoyes())->toBe('Sent');
});

it('crée une boîte via l’écran de réglages', function () {
    $pole = Pole::create(['nom' => 'Prana']);

    Livewire::test(BoitesEmail::class)
        ->call('nouvelle')
        ->set('fournisseur', 'hostinger')
        ->set('libelle', 'Boîte Prana')
        ->set('email', 'laurence@homeharmony.be')
        ->set('mot_de_passe', 'motdepasse')
        ->set('pole_id', $pole->id)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $boite = BoiteEmail::first();
    expect($boite->email)->toBe('laurence@homeharmony.be');
    expect($boite->fournisseur)->toBe('hostinger');
    expect($boite->pole_id)->toBe($pole->id);
    expect($boite->active)->toBeTrue();
});

it('chiffre le mot de passe de la boîte au repos', function () {
    $boite = BoiteEmail::factory()->create(['mot_de_passe' => 'secret-boite']);

    expect($boite->fresh()->mot_de_passe)->toBe('secret-boite'); // déchiffré via le modèle

    $brut = DB::table('boites_email')->where('id', $boite->id)->first();
    expect($brut->mot_de_passe)->not->toContain('secret-boite');
});

it('exige le mot de passe à la création mais le conserve en modification', function () {
    // Création sans mot de passe : erreur.
    Livewire::test(BoitesEmail::class)
        ->call('nouvelle')
        ->set('email', 'x@gmail.com')
        ->call('enregistrer')
        ->assertHasErrors(['mot_de_passe']);

    // Boîte existante : on peut modifier sans ressaisir le mot de passe.
    $boite = BoiteEmail::factory()->create(['email' => 'y@gmail.com', 'mot_de_passe' => 'conserve']);

    Livewire::test(BoitesEmail::class)
        ->call('modifier', $boite->id)
        ->set('libelle', 'Nouveau libellé')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect($boite->fresh()->libelle)->toBe('Nouveau libellé');
    expect($boite->fresh()->mot_de_passe)->toBe('conserve'); // conservé
});

it('active et désactive une boîte', function () {
    $boite = BoiteEmail::factory()->create(['active' => true]);

    Livewire::test(BoitesEmail::class)->call('basculerActive', $boite->id);
    expect($boite->fresh()->active)->toBeFalse();

    Livewire::test(BoitesEmail::class)->call('basculerActive', $boite->id);
    expect($boite->fresh()->active)->toBeTrue();
});

it('supprime une boîte', function () {
    $boite = BoiteEmail::factory()->create();

    Livewire::test(BoitesEmail::class)->call('supprimer', $boite->id);

    expect(BoiteEmail::count())->toBe(0);
});

it('exige une connexion pour accéder à l’écran des boîtes', function () {
    auth()->logout();
    $this->get(route('reglages.boites'))->assertRedirect(route('login'));
});

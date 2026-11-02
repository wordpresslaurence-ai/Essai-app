<?php

use App\Http\Controllers\SauvegardeController;
use App\Models\Contact;
use App\Models\Parametre;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('exporte les données métier sans aucun secret', function () {
    $this->actingAs(User::factory()->create());
    Contact::factory()->create(['nom' => 'Durand']);
    Parametre::instance()->update(['anthropic_api_key' => 'SECRET-KEY-XYZ']);

    $donnees = (new SauvegardeController)->donnees();
    $json = (string) json_encode($donnees, JSON_UNESCAPED_UNICODE);

    expect($donnees)->toHaveKey('contacts')
        ->and($donnees)->not->toHaveKey('parametres')   // pas de clés / mots de passe
        ->and($donnees)->not->toHaveKey('users')
        ->and($donnees)->not->toHaveKey('boites_email');

    expect($json)->toContain('Durand')
        ->and($json)->not->toContain('SECRET-KEY-XYZ');
});

it('télécharge une sauvegarde depuis la route protégée', function () {
    $this->actingAs(User::factory()->create());
    Contact::factory()->create(['nom' => 'Durand']);

    $res = $this->get(route('sauvegarde'));

    $res->assertOk();
    expect($res->headers->get('content-disposition'))->toContain('sauvegarde-crm');
});

it('refuse le téléchargement à un visiteur non connecté', function () {
    $this->get(route('sauvegarde'))->assertRedirect(route('login'));
});

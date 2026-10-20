<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\Analyse;
use App\Models\Contact;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use App\Services\IA\ResultatAnalyse;
use App\Services\IA\ResultatProspection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('analyse un contact, enregistre et affiche le résultat + brouillon', function () {
    $this->app->bind(AssistantIA::class, fn () => new AssistantFactice(
        new ResultatAnalyse(
            resume: 'Le client attend un devis.',
            verdict: 'À relancer rapidement.',
            delaiConseille: 'sous 2 jours',
            brouillon: 'Bonjour, voici le lien pour réserver un appel : …',
            modele: 'test',
        )
    ));

    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->assertSet('analyseErreur', null)
        ->assertSee('Le client attend un devis.')
        ->assertSee('À relancer rapidement.')
        ->assertSee('Bonjour, voici le lien');

    expect(Analyse::where('contact_id', $contact->id)->count())->toBe(1);
});

it('affiche une erreur propre si l’assistant échoue', function () {
    $this->app->bind(AssistantIA::class, function () {
        return new class implements AssistantIA
        {
            public function analyser(Contact $contact): ResultatAnalyse
            {
                throw new RuntimeException('Clé API manquante.');
            }

            public function redigerProspection(Contact $contact, ?string $angle = null): ResultatProspection
            {
                throw new RuntimeException('non utilisé');
            }

            public function suggestions(array $contexte): array
            {
                return [];
            }
        };
    });

    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->assertSet('analyseErreur', 'Clé API manquante.');

    expect(Analyse::count())->toBe(0);
});

it('affiche un espace vide avant toute analyse', function () {
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->assertSee('Pas encore');
});

it('rédige une prospection, affiche le brouillon et crée une tâche de trace', function () {
    $this->app->bind(AssistantIA::class, fn () => new AssistantFactice(
        prospection: new ResultatProspection(
            objet: 'Rejoindre la formation Prana Vidya',
            corps: 'Bonjour, je serais ravie de vous présenter ma formation. Laurence',
            modele: 'test',
        )
    ));

    $contact = Contact::factory()->create(['nom' => 'Martin', 'prenom' => 'Claire']);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->set('angleProspection', 'fait du yin')
        ->call('redigerProspection')
        ->assertSet('prospectionErreur', null)
        ->assertSet('prospectionObjet', 'Rejoindre la formation Prana Vidya');

    expect(App\Models\Activite::where('contact_id', $contact->id)
        ->where('titre', 'like', 'Envoyer le mail de prospection%')->count())->toBe(1);
});

it('ne crée pas de doublon de tâche de prospection si on régénère', function () {
    $this->app->bind(AssistantIA::class, fn () => new AssistantFactice(
        prospection: new ResultatProspection(objet: 'O', corps: 'C', modele: 't')
    ));
    $contact = Contact::factory()->create();

    $c = Livewire::test(FicheDetail::class, ['contact' => $contact]);
    $c->call('redigerProspection');
    $c->call('redigerProspection');

    expect(App\Models\Activite::where('contact_id', $contact->id)->count())->toBe(1);
});

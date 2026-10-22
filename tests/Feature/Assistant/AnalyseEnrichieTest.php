<?php

use App\Livewire\Contacts\FicheDetail;
use App\Models\Activite;
use App\Models\Analyse;
use App\Models\Contact;
use App\Models\Opportunite;
use App\Models\User;
use App\Services\IA\AssistantFactice;
use App\Services\IA\AssistantIA;
use App\Services\IA\ResultatAnalyse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** Lie l'assistant à un résultat enrichi déterministe. */
function brancherAnalyse(array $extra = []): void
{
    app()->bind(AssistantIA::class, fn () => new AssistantFactice(
        new ResultatAnalyse(
            resume: 'Le client attend un devis.',
            verdict: 'À relancer.',
            delaiConseille: 'sous 2 jours',
            brouillon: 'Bonjour…',
            modele: 'test',
            intention: $extra['intention'] ?? 'demande_devis',
            temperatureConseillee: $extra['temperature'] ?? 'chaud',
            etapeConseillee: $extra['etape'] ?? 'proposition',
            infosCles: $extra['infos'] ?? ['budget' => '1500 €', 'delai' => 'juin', 'dates_rdv' => ['2026-07-10'], 'decisions' => 'devis à envoyer'],
            promesses: $extra['promesses'] ?? [['texte' => 'Envoyer le devis', 'echeance' => '2026-07-01']],
            rdv: $extra['rdv'] ?? ['demande' => true, 'date' => '2026-07-10', 'details' => 'en visio'],
        )
    ));
}

it('enregistre les champs enrichis de l’analyse', function () {
    brancherAnalyse();
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->assertSet('analyseErreur', null);

    $a = Analyse::where('contact_id', $contact->id)->firstOrFail();
    expect($a->intention)->toBe('demande_devis');
    expect($a->temperature_conseillee)->toBe('chaud');
    expect($a->etape_conseillee)->toBe('proposition');
    expect($a->infos_cles['budget'])->toBe('1500 €');
    expect($a->promesses[0]['texte'])->toBe('Envoyer le devis');
    expect($a->rdv['date'])->toBe('2026-07-10');
});

it('applique la température conseillée au contact', function () {
    brancherAnalyse(['temperature' => 'chaud']);
    $contact = Contact::factory()->create(['temperature' => null]);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('appliquerTemperature');

    expect($contact->fresh()->temperature)->toBe('chaud');
});

it('fait avancer l’opportunité ouverte vers l’étape conseillée', function () {
    brancherAnalyse(['etape' => 'proposition']);
    $contact = Contact::factory()->create();
    $opp = Opportunite::create([
        'titre' => 'Site web', 'contact_id' => $contact->id, 'etape' => Opportunite::ETAPE_NOUVEAU,
    ]);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('avancerPipeline');

    expect($opp->fresh()->etape)->toBe(Opportunite::ETAPE_PROPOSITION);
});

it('signale l’absence d’opportunité ouverte', function () {
    brancherAnalyse(['etape' => 'proposition']);
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('avancerPipeline')
        ->assertSee('Aucune opportunité ouverte');
});

it('crée une opportunité à l’étape conseillée quand le contact n’en a pas', function () {
    brancherAnalyse(['etape' => 'qualifie']);
    $contact = Contact::factory()->create(['nom' => 'Roy', 'prenom' => 'Anne']);

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('creerOpportunite');

    $opp = Opportunite::where('contact_id', $contact->id)->firstOrFail();
    expect($opp->etape)->toBe(Opportunite::ETAPE_QUALIFIE);
    expect($opp->titre)->toContain('Anne Roy');
});

it('crée une tâche de rappel depuis une promesse, avec échéance', function () {
    brancherAnalyse(['promesses' => [['texte' => 'Envoyer le devis', 'echeance' => '2026-07-01']]]);
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('creerRappel', 0);

    $tache = Activite::where('contact_id', $contact->id)->where('type', Activite::TYPE_TACHE)->firstOrFail();
    expect($tache->titre)->toContain('Envoyer le devis');
    expect($tache->echeance->toDateString())->toBe('2026-07-01');
});

it('ne crée pas deux fois le même rappel', function () {
    brancherAnalyse(['promesses' => [['texte' => 'Envoyer le devis', 'echeance' => null]]]);
    $contact = Contact::factory()->create();

    $c = Livewire::test(FicheDetail::class, ['contact' => $contact])->call('analyser');
    $c->call('creerRappel', 0);
    $c->call('creerRappel', 0);

    expect(Activite::where('contact_id', $contact->id)->where('type', Activite::TYPE_TACHE)->count())->toBe(1);
});

it('crée une tâche « proposer un RDV » depuis une demande de rendez-vous', function () {
    brancherAnalyse(['rdv' => ['demande' => true, 'date' => null, 'details' => 'il veut un appel']]);
    $contact = Contact::factory()->create(['nom' => 'Petit', 'prenom' => 'Léa']);

    $c = Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('creerTacheProposerRdv');

    $tache = Activite::where('contact_id', $contact->id)->where('type', Activite::TYPE_TACHE)->firstOrFail();
    expect($tache->titre)->toBe('Proposer un rendez-vous à Léa Petit');

    // Pas de doublon.
    $c->call('creerTacheProposerRdv');
    expect(Activite::where('contact_id', $contact->id)->where('type', Activite::TYPE_TACHE)->count())->toBe(1);
});

it('crée un rendez-vous depuis la date détectée', function () {
    brancherAnalyse(['rdv' => ['demande' => true, 'date' => '2026-07-10', 'details' => 'visio']]);
    $contact = Contact::factory()->create();

    Livewire::test(FicheDetail::class, ['contact' => $contact])
        ->call('analyser')
        ->call('creerRdv');

    $rdv = Activite::where('contact_id', $contact->id)->where('type', Activite::TYPE_RDV)->firstOrFail();
    expect($rdv->echeance->toDateString())->toBe('2026-07-10');
    expect($rdv->notes)->toBe('visio');
});

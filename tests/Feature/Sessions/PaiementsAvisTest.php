<?php

use App\Livewire\Sessions\Avis;
use App\Livewire\Sessions\Formulaire;
use App\Livewire\Sessions\Paiements;
use App\Models\Activite;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\ModeleMessage;
use App\Models\Paiement;
use App\Models\Parametre;
use App\Models\Pole;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
});

function stageAvecInscrit(array $seance = [], array $contact = []): array
{
    $stage = Seance::create(array_merge([
        'titre' => 'Stage 1', 'prix' => 285, 'date_debut' => now()->subDays(2)->setTime(9, 0),
    ], $seance));
    $personne = Contact::factory()->create(array_merge(['prenom' => 'Marie', 'nom' => 'Durand'], $contact));

    return [$stage, $stage->inscrire($personne), $personne];
}

// --- Paiements ---------------------------------------------------------------

it('calcule le reste à payer à partir du prix de la session et des versements', function () {
    [$stage, $inscription] = stageAvecInscrit();

    expect($inscription->fresh()->etatPaiement())->toBe('impaye');

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->assertSee('À payer')
        ->call('ouvrir', $inscription->id)
        ->assertSet('montant', '285')
        ->set('montant', '100,50')
        ->set('note', 'acompte')
        ->call('enregistrerPaiement')
        ->assertHasNoErrors()
        ->assertSet('ouverteId', null)
        ->assertSee('Acompte');

    $inscription = $inscription->fresh();
    expect($inscription->totalPaye())->toBe(100.5);
    expect($inscription->resteDu())->toBe(184.5);
    expect($inscription->etatPaiement())->toBe('partiel');
    expect(Paiement::first()->note)->toBe('acompte');

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('solder', $inscription->id)
        ->assertSee('Payé');

    expect($inscription->fresh()->etatPaiement())->toBe('solde');
    expect(Paiement::count())->toBe(2);
});

it('refuse un montant vide ou nul', function () {
    [$stage, $inscription] = stageAvecInscrit();

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('ouvrir', $inscription->id)
        ->set('montant', '0')
        ->call('enregistrerPaiement')
        ->assertHasErrors(['montant']);

    expect(Paiement::count())->toBe(0);
});

it('prend le prix de la catégorie à défaut, et un montant propre à la personne en priorité', function () {
    $pole = Pole::create(['nom' => 'Yoga']);
    $categorie = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Formation', 'prix' => 300]);
    [$stage, $inscription] = stageAvecInscrit(['prix' => null, 'categorie_id' => $categorie->id]);

    expect($inscription->fresh()->montantDu())->toBe(300.0);

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('definirMontant', $inscription->id, '150');
    expect($inscription->fresh()->montantDu())->toBe(150.0);

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('definirMontant', $inscription->id, '');
    expect($inscription->fresh()->montantDu())->toBe(300.0);
});

it('supprime un paiement de la session, pas celui d’une autre', function () {
    [$stage, $inscription] = stageAvecInscrit();
    [$autre, $ailleurs] = stageAvecInscrit(['titre' => 'Stage 2'], ['prenom' => 'Paul', 'nom' => 'Petit']);
    $ici = $inscription->paiements()->create(['montant' => 50, 'paye_le' => now()->toDateString()]);
    $labas = $ailleurs->paiements()->create(['montant' => 60, 'paye_le' => now()->toDateString()]);

    Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('supprimerPaiement', $labas->id)
        ->call('supprimerPaiement', $ici->id);

    expect(Paiement::pluck('id')->all())->toBe([$labas->id]);
});

it('prépare un rappel de paiement avec les montants et en garde la trace', function () {
    [$stage, $inscription, $personne] = stageAvecInscrit();
    $inscription->paiements()->create(['montant' => 85, 'paye_le' => now()->toDateString()]);

    $page = Livewire::test(Paiements::class, ['seance' => $stage])
        ->call('preparerRappel', $inscription->id);

    expect($page->get('rappelCorps'))
        ->toContain('Bonjour Marie')
        ->toContain('il reste 200 € à régler')
        ->toContain('montant total : 285 €')
        ->toContain('déjà reçu : 85 €');

    $page->call('rappelEnvoye')->assertSet('rappelId', null);

    $trace = Activite::where('contact_id', $personne->id)->firstOrFail();
    expect($trace->titre)->toBe('Rappel de paiement envoyé : Stage 1');
    expect($trace->estTerminee())->toBeTrue();
});

it('enregistre le prix dans le formulaire de session, virgule acceptée', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Stage 3')
        ->set('prix', '142,50')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect(Seance::where('titre', 'Stage 3')->firstOrFail()->prix)->toBe(142.5);
});

// --- Avis --------------------------------------------------------------------

it('prépare la demande d’avis, la date et en garde la trace', function () {
    Parametre::instance()->update(['lien_avis' => 'https://exemple.be/avis']);
    [$stage, $inscription, $personne] = stageAvecInscrit();

    $page = Livewire::test(Avis::class, ['seance' => $stage])
        ->assertSee('À demander')
        ->call('preparer', $inscription->id);

    expect($page->get('brouillonObjet'))->toBe('Votre avis sur Stage 1');
    expect($page->get('brouillonCorps'))
        ->toContain('Bonjour Marie')
        ->toContain('https://exemple.be/avis')
        ->toContain('Laurence Test');

    $page->call('marquerEnvoye')->assertSet('ouverteId', null)->assertSee('Demandé le');

    expect($inscription->fresh()->avis_demande_le)->not->toBeNull();
    expect(Activite::where('contact_id', $personne->id)->firstOrFail()->titre)
        ->toBe('Demande d’avis envoyée : Stage 1');
});

it('utilise le modèle « Avis » personnalisé et signale un lien d’avis manquant', function () {
    ModeleMessage::create([
        'nom' => 'Avis — ma version', 'canal' => ModeleMessage::CANAL_MESSAGE,
        'corps' => 'Coucou {prenom}, un petit mot sur {session} ? {lien_avis}',
    ]);
    [$stage, $inscription] = stageAvecInscrit();

    $page = Livewire::test(Avis::class, ['seance' => $stage])->call('preparer', $inscription->id);

    expect($page->get('brouillonCorps'))->toBe('Coucou Marie, un petit mot sur Stage 1 ? [lien_avis]');
});

it('ne propose pas de demander un avis aux absents', function () {
    [$stage] = stageAvecInscrit();
    $absent = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit']);
    $stage->inscrire($absent)->update(['presence' => Inscription::ABSENT]);

    Livewire::test(Avis::class, ['seance' => $stage])
        ->assertSee('Marie Durand')
        ->assertDontSee('Paul Petit');
});

it('garde l’avis reçu avec l’accord de publication', function () {
    [$stage, $inscription, $personne] = stageAvecInscrit();

    Livewire::test(Avis::class, ['seance' => $stage])
        ->call('noter', $inscription->id)
        ->call('enregistrerAvis')
        ->assertHasErrors(['avisTexte'])
        ->set('avisTexte', 'Trois jours qui ont changé ma façon d’enseigner.')
        ->set('avisPubliable', true)
        ->call('enregistrerAvis')
        ->assertHasNoErrors()
        ->assertSee('Trois jours qui ont changé')
        ->assertSee('Accord de publication');

    $inscription = $inscription->fresh();
    expect($inscription->avis_publiable)->toBeTrue();
    expect($inscription->avis_recu_le)->not->toBeNull();
    expect(Activite::where('contact_id', $personne->id)->where('titre', 'Avis reçu : Stage 1')->exists())->toBeTrue();

    Livewire::test(Avis::class, ['seance' => $stage])->call('supprimerAvis', $inscription->id);
    expect($inscription->fresh()->avis_recu_le)->toBeNull();
});

it('ouvre les pages paiements et avis depuis la fiche de session', function () {
    [$stage] = stageAvecInscrit();

    $this->get(route('sessions.fiche', $stage))->assertOk()->assertSee('Paiements')->assertSee('Demander un avis');
    $this->get(route('sessions.paiements', $stage))->assertOk()->assertSee('reste à recevoir');
    $this->get(route('sessions.avis', $stage))->assertOk()->assertSee('Demande d\'avis', false);
});

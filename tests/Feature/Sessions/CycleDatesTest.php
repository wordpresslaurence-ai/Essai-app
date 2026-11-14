<?php

use App\Livewire\Sessions\Dates;
use App\Livewire\Sessions\Fiche;
use App\Livewire\Sessions\Formulaire;
use App\Livewire\Sessions\Liste;
use App\Models\Activite;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\PresenceDate;
use App\Models\Seance;
use App\Models\User;
use App\Services\Sessions\RappelsCycle;
use App\Services\Sessions\SuiviApresSeance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Laurence Test']));
});

/** Un cycle de trois dates (hier, demain, dans huit jours) avec deux inscrits. */
function cycleEnCours(): array
{
    $cycle = Seance::create(['titre' => 'Aura en ligne', 'date_debut' => now()->subDay()->setTime(10, 0), 'date_fin' => now()->addDays(8)->setTime(10, 0)]);
    $hier = $cycle->dates()->create(['date_debut' => now()->subDay()->setTime(10, 0), 'lien_visio' => 'https://visio.test/1']);
    $demain = $cycle->dates()->create(['date_debut' => now()->addDay()->setTime(10, 0), 'lien_visio' => 'https://visio.test/2']);
    $cycle->dates()->create(['date_debut' => now()->addDays(8)->setTime(10, 0)]);

    $marie = Contact::factory()->create(['prenom' => 'Marie', 'nom' => 'Durand', 'telephone' => '+32 475 11 22 33']);
    $paul = Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Petit', 'telephone' => null]);

    return [$cycle, $hier, $demain, $cycle->inscrire($marie), $cycle->inscrire($paul)];
}

// --- Formulaire --------------------------------------------------------------

it('enregistre un cycle de plusieurs dates avec un lien de visio par date', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Aura en ligne')
        ->set('date_debut', '2027-01-11T10:00')
        ->set('lien_visio', 'https://visio.test/1')
        ->call('ajouterDate')
        ->set('autresDates.0.date', '2027-01-25T10:00')
        ->set('autresDates.0.lien_visio', 'https://visio.test/3')
        ->call('ajouterDate')
        ->set('autresDates.1.date', '2027-01-18T10:00')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $cycle = Seance::where('titre', 'Aura en ligne')->firstOrFail();
    expect($cycle->estCycle())->toBeTrue();
    expect($cycle->date_debut->format('Y-m-d'))->toBe('2027-01-11');
    expect($cycle->date_fin->format('Y-m-d'))->toBe('2027-01-25');
    expect($cycle->dates->map(fn ($d) => $d->date_debut->format('d/m'))->all())->toBe(['11/01', '18/01', '25/01']);
    expect($cycle->dates->pluck('lien_visio')->all())->toBe(['https://visio.test/1', null, 'https://visio.test/3']);
});

it('garde les présences quand on modifie le cycle, et redevient une session simple avec une seule date', function () {
    [$cycle, $hier, , $marie] = cycleEnCours();
    PresenceDate::create(['seance_date_id' => $hier->id, 'inscription_id' => $marie->id, 'presence' => 'present']);

    $form = Livewire::test(Formulaire::class, ['seance' => $cycle])
        ->assertSet('premiereDateId', $hier->id)
        ->assertSet('lien_visio', 'https://visio.test/1')
        ->assertCount('autresDates', 2)
        ->call('retirerDate', 1)
        ->call('retirerDate', 0)
        ->call('enregistrer')
        ->assertHasNoErrors();

    $cycle->refresh();
    expect($cycle->dates)->toHaveCount(1);
    expect($cycle->dates->first()->id)->toBe($hier->id);
    expect($cycle->date_fin)->toBeNull();
    expect(PresenceDate::count())->toBe(1);
});

it('refuse un lien de visio qui n’est pas une adresse', function () {
    Livewire::test(Formulaire::class)
        ->set('titre', 'Aura')
        ->set('date_debut', '2027-01-11T10:00')
        ->set('lien_visio', 'zoom demain')
        ->call('enregistrer')
        ->assertHasErrors(['lien_visio']);
});

// --- Présences et messages ---------------------------------------------------

it('note les présences date par date et en déduit la présence au cycle', function () {
    [$cycle, $hier, $demain, $marie, $paul] = cycleEnCours();

    Livewire::test(Dates::class, ['seance' => $cycle])
        ->assertSee('Date 1 sur 3')
        ->call('noterPresence', $hier->id, $marie->id, 'present')
        ->call('noterPresence', $hier->id, $paul->id, 'absent')
        ->assertSee('1 présent(s) · 1 absent(s)');

    expect($marie->fresh()->presence)->toBe(Inscription::PRESENT);
    expect($paul->fresh()->presence)->toBeNull(); // il lui reste deux dates

    Livewire::test(Dates::class, ['seance' => $cycle])
        ->call('noterPresence', $demain->id, $paul->id, 'absent')
        ->call('noterPresence', $cycle->dates[2]->id, $paul->id, 'absent');
    expect($paul->fresh()->presence)->toBe(Inscription::ABSENT);

    // Cliquer à nouveau efface.
    Livewire::test(Dates::class, ['seance' => $cycle])->call('noterPresence', $hier->id, $marie->id, 'present');
    expect($marie->fresh()->presence)->toBeNull();
});

it('prépare le rappel de la veille avec le lien de visio de la date', function () {
    [$cycle, , $demain, $marie] = cycleEnCours();

    $page = Livewire::test(Dates::class, ['seance' => $cycle])->call('ouvrir', $demain->id, 'rappel');

    $lignes = $page->viewData('lignes');
    expect($lignes)->toHaveCount(2);
    $ligne = $lignes->first(fn ($l) => $l['inscription']->id === $marie->id);
    expect($ligne['texte'])
        ->toContain('Bonjour Marie')
        ->toContain('https://visio.test/2')
        ->toContain($demain->date_debut->translatedFormat('l j F Y'))
        ->toContain('10 h');
    expect($ligne['whatsapp'])->toStartWith('https://wa.me/32475112233?text=');
});

it('envoie l’enregistrement aux seuls absents de la date et en garde la trace', function () {
    [$cycle, $hier, , $marie, $paul] = cycleEnCours();
    PresenceDate::create(['seance_date_id' => $hier->id, 'inscription_id' => $marie->id, 'presence' => 'present']);
    PresenceDate::create(['seance_date_id' => $hier->id, 'inscription_id' => $paul->id, 'presence' => 'absent']);

    $page = Livewire::test(Dates::class, ['seance' => $cycle])
        ->call('majLien', $hier->id, 'lien_enregistrement', 'https://video.test/seance-1')
        ->call('ouvrir', $hier->id, 'enregistrement');

    $lignes = $page->viewData('lignes');
    expect($lignes)->toHaveCount(1);
    expect($lignes[0]['inscription']->id)->toBe($paul->id);
    expect($lignes[0]['texte'])->toContain('https://video.test/seance-1');

    $page->call('marquer', $paul->id)->call('marquer', $paul->id); // un seul envoi noté

    expect(PresenceDate::where('inscription_id', $paul->id)->first()->enregistrement_envoye_le)->not->toBeNull();
    expect(Activite::where('contact_id', $paul->contact_id)->where('titre', 'Enregistrement envoyé : Aura en ligne')->count())->toBe(1);
});

it('refuse un lien d’enregistrement invalide', function () {
    [$cycle, $hier] = cycleEnCours();

    Livewire::test(Dates::class, ['seance' => $cycle])->call('majLien', $hier->id, 'lien_enregistrement', 'pas un lien');

    expect($hier->fresh()->lien_enregistrement)->toBeNull();
});

// --- Rappel de la veille, suivi, listes --------------------------------------

it('crée une seule tâche de rappel la veille de chaque date, clôturée quand le rappel part', function () {
    [$cycle, , $demain, $marie] = cycleEnCours();

    expect((new RappelsCycle)->creerTaches())->toBe(1);
    expect((new RappelsCycle)->creerTaches())->toBe(0);

    $tache = Activite::where('titre', 'like', 'Rappel aux inscrits%')->firstOrFail();
    expect($tache->titre)->toContain('Aura en ligne');
    expect($tache->notes)->toContain('/sessions/'.$cycle->id.'/dates');
    expect($tache->estTerminee())->toBeFalse();

    Livewire::test(Dates::class, ['seance' => $cycle])
        ->call('ouvrir', $demain->id, 'rappel')
        ->call('marquer', $marie->id);

    expect($tache->fresh()->estTerminee())->toBeTrue();
});

it('ne propose le suivi d’un cycle qu’après sa dernière date', function () {
    [$cycle] = cycleEnCours();

    expect((new SuiviApresSeance)->creerTaches('Laurence Test'))->toBe(0);

    $this->travel(9)->days();
    expect((new SuiviApresSeance)->creerTaches('Laurence Test'))->toBe(2);
});

it('garde un cycle en cours dans « À venir », ferme ses inscriptions et montre chaque date dans l’agenda', function () {
    [$cycle] = cycleEnCours();

    expect(Seance::aVenir()->whereKey($cycle->id)->exists())->toBeTrue();
    expect(Seance::passees()->whereKey($cycle->id)->exists())->toBeFalse();
    expect($cycle->fresh()->inscriptionsCloses())->toBeTrue();

    Livewire::test(Fiche::class, ['seance' => $cycle])
        ->assertSee('les inscriptions sont closes')
        ->assertSee('Cycle de 3 dates')
        ->assertDontSee('Choisir un contact')
        ->set('forcerInscription', true)
        ->assertSee('Choisir un contact');

    Livewire::test(Liste::class)->assertSee('(3 dates)');

    $agenda = Livewire::test(Liste::class)->set('onglet', 'agenda')->set('annee', now()->addDays(8)->year);
    $agenda->assertSee('date 3 sur 3');

    $this->get(route('sessions.dates', $cycle))->assertOk();
});

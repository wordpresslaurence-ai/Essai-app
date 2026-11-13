<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Prospection\Radar;
use App\Models\Categorie;
use App\Models\Contact;
use App\Models\Pole;
use App\Models\RadarSite;
use App\Models\User;
use App\Services\Radar\AnalyseurSite;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function pageDatee(): string
{
    return '<html><head><title>Yoga Zen</title></head><body><h1>Cours de yoga</h1>'
        .'<img src="https://static.wixstatic.com/a.jpg"><footer>© 2019 Yoga Zen</footer></body></html>';
}

function pageSoignee(): string
{
    return '<html><head><title>Studio Lotus, yoga à Namur</title>'
        .'<meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<meta name="description" content="Cours de yoga à Namur, tous niveaux, toute la semaine.">'
        .'</head><body><link href="/wp-content/themes/x/style.css"><a href="/reserver">Réserver un cours</a>'
        .'<footer>© '.now()->year.' Studio Lotus</footer></body></html>';
}

// --- Analyseur ---------------------------------------------------------------

it('relève les indices d’un site daté', function () {
    Http::fake(['yoga-zen.test*' => Http::response(pageDatee())]);

    $r = (new AnalyseurSite)->analyser('http://yoga-zen.test');

    expect($r['statut'])->toBe(RadarSite::A_AMELIORER);
    expect($r['techno'])->toBe('Wix');
    expect(array_column($r['constats'], 'cle'))->toBe(['https', 'mobile', 'reservation', 'google', 'ancien']);
});

it('juge correct un site soigné', function () {
    Http::fake(['studio-lotus.test*' => Http::response(pageSoignee())]);

    $r = (new AnalyseurSite)->analyser('studio-lotus.test');

    expect($r['statut'])->toBe(RadarSite::CORRECT);
    expect($r['techno'])->toBe('WordPress');
    expect($r['constats'])->toBe([]);
});

it('reconnaît une page de réseau social, un site en erreur et une adresse interne, sans les visiter à tort', function () {
    Http::fake(['ferme.test*' => Http::response('', 404)]);
    $analyseur = new AnalyseurSite;

    expect($analyseur->analyser('https://www.instagram.com/yoga.zen')['statut'])->toBe(RadarSite::RESEAU_SEUL);
    expect($analyseur->analyser('https://ferme.test')['statut'])->toBe(RadarSite::INJOIGNABLE);
    expect($analyseur->analyser('http://127.0.0.1/admin')['statut'])->toBe(RadarSite::INJOIGNABLE);
    expect($analyseur->analyser(null)['statut'])->toBe(RadarSite::SANS_SITE);

    Http::assertSentCount(1); // seul ferme.test a été interrogé
});

// --- Page radar --------------------------------------------------------------

it('classe les contacts, analyse les suivants et garde le résultat', function () {
    Http::fake([
        'yoga-zen.test*' => Http::response(pageDatee()),
        'studio-lotus.test*' => Http::response(pageSoignee()),
    ]);
    $date = Contact::factory()->create(['prenom' => 'Zoé', 'nom' => 'Datée', 'site_web' => 'http://yoga-zen.test']);
    $soigne = Contact::factory()->create(['prenom' => 'Léa', 'nom' => 'Soignée', 'site_web' => 'https://studio-lotus.test']);
    $sansSite = Contact::factory()->create(['prenom' => 'Anne', 'nom' => 'Sansite', 'site_web' => null]);
    Contact::factory()->create(['prenom' => 'Paul', 'nom' => 'Refus', 'site_web' => null, 'ne_plus_contacter' => true]);

    $page = Livewire::test(Radar::class)
        ->assertSee('Anne Sansite')
        ->assertSee('Pas de site connu')
        ->assertDontSee('Paul Refus')
        ->call('analyserSuivants')
        ->assertSee('La page d’accueil ne semble pas adaptée aux téléphones')
        ->assertSee('fait avec Wix');

    expect(RadarSite::where('contact_id', $date->id)->firstOrFail()->statut)->toBe(RadarSite::A_AMELIORER);
    expect(RadarSite::where('contact_id', $soigne->id)->firstOrFail()->statut)->toBe(RadarSite::CORRECT);
    expect(RadarSite::where('contact_id', $sansSite->id)->exists())->toBeFalse();

    // Filtre par statut, puis écarter.
    $page->set('statut', RadarSite::A_AMELIORER)
        ->assertSee('Zoé Datée')
        ->assertDontSee('Léa Soignée')
        ->call('basculerEcarte', $date->id)
        ->assertDontSee('Zoé Datée')
        ->set('voirEcartes', true)
        ->assertSee('Zoé Datée');

    // L'adresse du site change sur la fiche : le contact redevient « à analyser ».
    $soigne->update(['site_web' => 'https://nouveau.test']);
    expect(RadarSite::statutPour($soigne->fresh()))->toBe(RadarSite::NON_ANALYSE);
});

it('prépare l’angle du mail à partir des constats et l’ouvre sur la fiche', function () {
    $pole = Pole::create(['nom' => 'Web']);
    $offre = Categorie::create(['pole_id' => $pole->id, 'nom' => 'Sites WordPress', 'offre' => 'Création et refonte de sites.']);
    $contact = Contact::factory()->create(['site_web' => 'http://yoga-zen.test']);
    RadarSite::create([
        'contact_id' => $contact->id, 'url' => 'http://yoga-zen.test', 'statut' => RadarSite::A_AMELIORER, 'techno' => 'Wix',
        'constats' => [['cle' => 'mobile', 'texte' => 'La page d’accueil ne semble pas adaptée aux téléphones']],
        'analyse_le' => now(),
    ]);

    $angle = Radar::angle($contact->fresh(), RadarSite::A_AMELIORER);
    expect($angle)->toContain('la page d’accueil ne semble pas adaptée aux téléphones')->toContain('Wix');

    Livewire::test(Radar::class)->assertSet('offreId', (string) $offre->id)->assertSee('Préparer le mail');

    Livewire::withQueryParams(['angle' => $angle, 'offre' => (string) $offre->id])
        ->test(FicheDetail::class, ['contact' => $contact])
        ->assertSet('angleProspection', $angle)
        ->assertSet('offreCategorieId', (string) $offre->id);

    $this->get(route('prospection.radar'))->assertOk();
    $this->get(route('prospection.index'))->assertOk()->assertSee('Radar sites web');
});

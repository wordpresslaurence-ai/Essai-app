<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Activites\Formulaire as ActiviteFormulaire;
use App\Livewire\Activites\Liste as ActivitesListe;
use App\Livewire\Contacts\ExportNewsletter;
use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Contacts\Formulaire;
use App\Livewire\Contacts\GestionEtiquettes;
use App\Livewire\Contacts\ImportCsv;
use App\Livewire\Contacts\Liste;
use App\Livewire\Pipeline\Formulaire as PipelineFormulaire;
use App\Livewire\Pipeline\Tableau as PipelineTableau;
use App\Livewire\TableauBord;
use Illuminate\Support\Facades\Route;

Route::redirect('/', 'dashboard');

Route::get('dashboard', TableauBord::class)
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

// Module Contacts (protégé par l'authentification)
Route::middleware(['auth'])->group(function () {
    Route::get('contacts', Liste::class)->name('contacts.index');
    Route::get('contacts/creer', Formulaire::class)->name('contacts.creer');
    Route::get('contacts/import', ImportCsv::class)->name('contacts.import');
    Route::get('contacts/newsletter', ExportNewsletter::class)->name('contacts.newsletter');
    Route::get('contacts/{contact}', FicheDetail::class)->name('contacts.fiche');
    Route::get('contacts/{contact}/modifier', Formulaire::class)->name('contacts.modifier');
    Route::get('etiquettes', GestionEtiquettes::class)->name('etiquettes.index');

    // Module Organisation : Pôles & Catégories (module 006)
    Route::get('organisation', \App\Livewire\Organisation\Gestion::class)->name('organisation.index');

    // Module Sessions & listes d'attente (module 008)
    Route::get('sessions', \App\Livewire\Sessions\Liste::class)->name('sessions.index');
    Route::get('sessions/creer', \App\Livewire\Sessions\Formulaire::class)->name('sessions.creer');
    Route::get('sessions/{seance}', \App\Livewire\Sessions\Fiche::class)->name('sessions.fiche');
    Route::get('sessions/{seance}/modifier', \App\Livewire\Sessions\Formulaire::class)->name('sessions.modifier');

    // Assistant (module 005)
    Route::get('assistant', \App\Livewire\Assistant\Brief::class)->name('assistant.brief');
    Route::get('mails', \App\Livewire\Assistant\MailsRecents::class)->name('mails.index');

    // Réglages de l'assistant (module 005)
    Route::get('reglages', \App\Livewire\Reglages\Assistant::class)->name('reglages.assistant');

    // Réglages : boîtes e-mail multiples (module 009)
    Route::get('reglages/boites', \App\Livewire\Reglages\BoitesEmail::class)->name('reglages.boites');

    // Module Prospection (Option B : brief pour ChatGPT, tri et import côté CRM)
    Route::get('prospection', \App\Livewire\Prospection\Liste::class)->name('prospection.index');
    Route::get('prospection/creer', \App\Livewire\Prospection\Formulaire::class)->name('prospection.creer');
    Route::get('prospection/{recherche}/modifier', \App\Livewire\Prospection\Formulaire::class)->name('prospection.modifier');
    Route::get('prospection/{recherche}/resultats', \App\Livewire\Prospection\Resultats::class)->name('prospection.resultats');

    // Module Pipeline
    Route::get('pipeline', PipelineTableau::class)->name('pipeline.index');
    Route::get('pipeline/creer', PipelineFormulaire::class)->name('pipeline.creer');
    Route::get('pipeline/{opportunite}/modifier', PipelineFormulaire::class)->name('pipeline.modifier');

    // Module Activités
    Route::get('activites', ActivitesListe::class)->name('activites.index');
    Route::get('activites/creer', ActiviteFormulaire::class)->name('activites.creer');
    Route::get('activites/{activite}/modifier', ActiviteFormulaire::class)->name('activites.modifier');

    Route::post('logout', function (Logout $logout) {
        $logout();

        return redirect('/');
    })->name('logout');
});

require __DIR__.'/auth.php';

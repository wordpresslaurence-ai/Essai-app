<?php

use App\Livewire\Actions\Logout;
use App\Livewire\Activites\Formulaire as ActiviteFormulaire;
use App\Livewire\Activites\Liste as ActivitesListe;
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
    Route::get('contacts/{contact}', FicheDetail::class)->name('contacts.fiche');
    Route::get('contacts/{contact}/modifier', Formulaire::class)->name('contacts.modifier');
    Route::get('etiquettes', GestionEtiquettes::class)->name('etiquettes.index');

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

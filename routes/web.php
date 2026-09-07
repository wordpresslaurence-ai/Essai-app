<?php

use App\Livewire\Contacts\FicheDetail;
use App\Livewire\Contacts\Formulaire;
use App\Livewire\Contacts\GestionEtiquettes;
use App\Livewire\Contacts\ImportCsv;
use App\Livewire\Contacts\Liste;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::view('dashboard', 'dashboard')
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
});

require __DIR__.'/auth.php';

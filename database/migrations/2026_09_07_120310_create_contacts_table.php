<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table des contacts : personnes ET entreprises (modèle unique, cf. plan §1).
     */
    public function up(): void
    {
        Schema::create('contacts', function (Blueprint $table) {
            $table->id();

            // Discriminant personne / entreprise
            $table->string('type')->default('personne')->index();

            // Champs communs
            $table->string('nom'); // nom de personne OU raison sociale
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->text('notes')->nullable();

            // Champs propres à une personne
            $table->string('prenom')->nullable();
            $table->string('fonction')->nullable();
            // Rattachement personne -> entreprise (auto-référence).
            // Détachement automatique des personnes si l'entreprise est supprimée.
            $table->foreignId('entreprise_id')
                ->nullable()
                ->constrained('contacts')
                ->nullOnDelete();

            // Champs propres à une entreprise
            $table->string('site_web')->nullable();
            $table->string('numero_entreprise')->nullable(); // BCE / KBO
            $table->string('numero_tva')->nullable();
            $table->string('secteur')->nullable();

            // Adresse
            $table->string('adresse_rue')->nullable();
            $table->string('adresse_code_postal')->nullable();
            $table->string('adresse_ville')->nullable();
            $table->string('adresse_pays')->default('BE');

            // Archivage (distinct de la suppression)
            $table->timestamp('archived_at')->nullable();

            $table->timestamps();

            // Index pour la recherche et le tri (constitution art. 7.2)
            $table->index('nom');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contacts');
    }
};

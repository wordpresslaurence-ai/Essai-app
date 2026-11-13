<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Radar « sites web » (brique 016) : le dernier examen du site d'un contact,
     * pour repérer qui pourrait avoir besoin d'un site ou d'une refonte.
     */
    public function up(): void
    {
        Schema::create('radars_site', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->unique()->constrained('contacts')->cascadeOnDelete();
            $table->string('url', 500)->nullable();        // adresse examinée (celle de la fiche)
            $table->string('url_finale', 500)->nullable();  // après redirections
            $table->string('statut');                        // voir RadarSite::STATUTS
            $table->string('techno')->nullable();            // WordPress, Wix…
            $table->json('constats')->nullable();            // [{cle, texte}]
            $table->unsignedInteger('duree_ms')->nullable();
            $table->dateTime('analyse_le')->nullable();
            $table->dateTime('ecarte_le')->nullable();       // « pas pour moi » : sorti de la liste
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('radars_site');
    }
};

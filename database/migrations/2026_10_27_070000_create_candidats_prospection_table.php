<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Candidats d'une recherche : zone de préparation, DISTINCTE des contacts
        // du CRM. Rien n'entre dans le carnet tant que Laurence n'a pas importé.
        Schema::create('candidats_prospection', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recherche_prospection_id')->constrained('recherches_prospection')->cascadeOnDelete();

            // Les 40 champs tels que fournis par ChatGPT (bruts, non normalisés).
            $table->json('donnees');

            // Dénormalisé pour l'affichage et le futur dédoublonnage.
            $table->string('nom')->nullable();
            $table->string('email')->nullable();
            $table->string('telephone')->nullable();
            $table->string('url_profil')->nullable();
            $table->string('origine')->nullable();

            $table->string('statut')->default('nouveau');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('candidats_prospection');
    }
};

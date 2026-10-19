<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réglages de l'assistant (une seule ligne). Les secrets (mot de passe
     * d'application Gmail, clé API Claude) sont chiffrés au niveau du modèle.
     */
    public function up(): void
    {
        Schema::create('parametres', function (Blueprint $table) {
            $table->id();

            // Connexion e-mail (boîte synchronisée)
            $table->string('gmail_email')->nullable();
            $table->text('gmail_app_password')->nullable(); // chiffré (cast encrypted)

            // Fournisseur IA
            $table->text('anthropic_api_key')->nullable();  // chiffré (cast encrypted)
            $table->string('anthropic_modele')->nullable();

            // Comportement des relances
            $table->unsignedSmallInteger('relance_delai_jours')->default(5);

            // Liens Fluent Booking
            $table->string('lien_appel_visio')->nullable();
            $table->string('lien_seance_decouverte')->nullable();
            $table->string('lien_journee_aura')->nullable();

            // Suivi de synchronisation
            $table->dateTime('derniere_synchro_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametres');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Réservations reçues du site (brique 013) : journal des appels entrants
     * et jeton secret de l'adresse de réception.
     */
    public function up(): void
    {
        Schema::create('reservations_recues', function (Blueprint $table) {
            $table->id();
            $table->string('statut');            // inscrit | rdv | annule | ignore
            $table->string('resume')->nullable(); // phrase lisible : ce que le CRM en a fait
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->foreignId('seance_id')->nullable()->constrained('seances')->nullOnDelete();
            $table->longText('contenu')->nullable(); // données brutes reçues (JSON), pour diagnostic
            $table->timestamps();
        });

        Schema::table('parametres', function (Blueprint $table) {
            $table->string('webhook_jeton', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('webhook_jeton');
        });

        Schema::dropIfExists('reservations_recues');
    }
};

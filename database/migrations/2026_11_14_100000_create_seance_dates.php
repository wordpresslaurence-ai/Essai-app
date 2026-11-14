<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cycles à plusieurs dates (brique 017) :
     * - seance_dates : chaque date d'une session, avec son lien de visio et, après
     *   coup, le lien de l'enregistrement ;
     * - presences_date : présence d'un inscrit à une date, et envoi de l'enregistrement ;
     * - seances.date_fin : dernière date du cycle (null pour une session d'une seule date).
     * seances.date_debut reste la première date : rien ne change pour l'existant.
     */
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dateTime('date_fin')->nullable();
        });

        Schema::create('seance_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->dateTime('date_debut');
            $table->string('lien_visio', 500)->nullable();
            $table->string('lien_enregistrement', 500)->nullable();
            $table->dateTime('rappel_cree_le')->nullable();
            $table->timestamps();
            $table->index('date_debut');
        });

        Schema::create('presences_date', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_date_id')->constrained('seance_dates')->cascadeOnDelete();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->string('presence')->nullable(); // present | absent
            $table->dateTime('enregistrement_envoye_le')->nullable();
            $table->timestamps();
            $table->unique(['seance_date_id', 'inscription_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('presences_date');
        Schema::dropIfExists('seance_dates');

        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('date_fin');
        });
    }
};

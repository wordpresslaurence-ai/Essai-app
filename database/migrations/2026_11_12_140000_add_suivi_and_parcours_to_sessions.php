<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi après séance et parcours formation (brique 014) :
     * - inscriptions.presence : present | absent (null = non noté) ;
     * - inscriptions.suivi_cree_le / suivi_envoye_le : tâche de suivi créée, message envoyé ;
     * - seances.role_parcours : decouverte | formation (null = hors parcours).
     */
    public function up(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('presence')->nullable();
            $table->dateTime('suivi_cree_le')->nullable();
            $table->dateTime('suivi_envoye_le')->nullable();
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->string('role_parcours')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('role_parcours');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn(['presence', 'suivi_cree_le', 'suivi_envoye_le']);
        });
    }
};

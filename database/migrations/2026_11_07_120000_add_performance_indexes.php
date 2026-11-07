<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index de performance sur des colonnes très filtrées/triées. Sans effet visible
 * aujourd'hui (peu de données), utiles quand le volume grandit. Additif et sûr.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->index('archived_at');
        });

        Schema::table('activites', function (Blueprint $table) {
            $table->index('terminee_at');
            $table->index('echeance');
        });

        Schema::table('opportunites', function (Blueprint $table) {
            $table->index('date_cloture');
            $table->index('etape_changee_le');
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->index('date_debut');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->index('statut');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', fn (Blueprint $table) => $table->dropIndex(['archived_at']));
        Schema::table('activites', function (Blueprint $table) {
            $table->dropIndex(['terminee_at']);
            $table->dropIndex(['echeance']);
        });
        Schema::table('opportunites', function (Blueprint $table) {
            $table->dropIndex(['date_cloture']);
            $table->dropIndex(['etape_changee_le']);
        });
        Schema::table('seances', fn (Blueprint $table) => $table->dropIndex(['date_debut']));
        Schema::table('inscriptions', fn (Blueprint $table) => $table->dropIndex(['statut']));
    }
};

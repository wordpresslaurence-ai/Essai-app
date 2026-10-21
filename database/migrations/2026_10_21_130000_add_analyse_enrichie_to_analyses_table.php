<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analyse enrichie (brique 010) : intention, suggestions et infos extraites.
 * Colonnes additives — les analyses existantes restent valides.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->string('intention')->nullable()->after('verdict');
            $table->string('temperature_conseillee')->nullable()->after('intention');
            $table->string('etape_conseillee')->nullable()->after('temperature_conseillee');
            $table->json('infos_cles')->nullable()->after('etape_conseillee');
            $table->json('promesses')->nullable()->after('infos_cles');
            $table->json('rdv')->nullable()->after('promesses');
        });
    }

    public function down(): void
    {
        Schema::table('analyses', function (Blueprint $table) {
            $table->dropColumn([
                'intention', 'temperature_conseillee', 'etape_conseillee',
                'infos_cles', 'promesses', 'rdv',
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Suit la date de dernier changement d'étape d'une opportunité (brique 011),
 * pour repérer les opportunités « qui stagnent ». Colonne additive ; les lignes
 * existantes reprennent leur updated_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            $table->timestamp('etape_changee_le')->nullable()->after('etape');
        });

        // Reprise : initialise la colonne pour les opportunités existantes.
        DB::table('opportunites')->whereNull('etape_changee_le')->update([
            'etape_changee_le' => DB::raw('updated_at'),
        ]);
    }

    public function down(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            $table->dropColumn('etape_changee_le');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidats_prospection', function (Blueprint $table) {
            // Candidat écarté pour cette recherche (reste en historique, non importable).
            $table->boolean('ecarte')->default(false)->after('statut');
            $table->string('motif_ecart')->nullable()->after('ecarte');
        });
    }

    public function down(): void
    {
        Schema::table('candidats_prospection', function (Blueprint $table) {
            $table->dropColumn(['ecarte', 'motif_ecart']);
        });
    }
};

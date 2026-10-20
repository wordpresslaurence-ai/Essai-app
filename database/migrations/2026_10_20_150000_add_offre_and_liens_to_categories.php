<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Offre & liens au niveau de la CATÉGORIE (et non plus du pôle). « offre » est
     * une description libre (sans limite) ; « liens » est une liste de liens
     * (libellé + url). L'assistant s'en sert pour la prospection.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->longText('offre')->nullable()->after('couleur');
            $table->json('liens')->nullable()->after('offre');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn(['offre', 'liens']);
        });
    }
};

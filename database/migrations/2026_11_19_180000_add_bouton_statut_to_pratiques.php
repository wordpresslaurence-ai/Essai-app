<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Résultat de l'ajout du bouton « ✓ Fait » sous une pratique, pour le diagnostic. */
    public function up(): void
    {
        Schema::table('pratiques_telegram', function (Blueprint $table) {
            $table->boolean('bouton_ajoute')->default(false);
            $table->string('bouton_erreur', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pratiques_telegram', function (Blueprint $table) {
            $table->dropColumn(['bouton_ajoute', 'bouton_erreur']);
        });
    }
};

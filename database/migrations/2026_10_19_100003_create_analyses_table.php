<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Résultats de l'« Analyse & relances » produits par l'assistant IA pour
     * un contact. On conserve l'historique ; on affiche le plus récent.
     */
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->text('resume')->nullable();
            $table->text('verdict')->nullable();
            $table->string('delai_conseille')->nullable();
            $table->text('brouillon')->nullable();
            $table->string('modele')->nullable();
            $table->dateTime('faite_le');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};

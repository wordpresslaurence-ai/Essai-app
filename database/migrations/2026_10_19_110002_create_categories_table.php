<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catégories : subdivisions d'un pôle (Formation profs, Recherche de salle,
     * Sites web…), créées par l'utilisatrice. Nom unique au sein d'un pôle.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pole_id')->constrained('poles')->cascadeOnDelete();
            $table->string('nom');
            $table->string('couleur')->nullable();
            $table->unsignedInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['pole_id', 'nom']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

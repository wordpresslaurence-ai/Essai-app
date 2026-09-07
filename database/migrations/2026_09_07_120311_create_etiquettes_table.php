<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Étiquettes réutilisables pour classer les contacts (cf. EF-11).
     */
    public function up(): void
    {
        Schema::create('etiquettes', function (Blueprint $table) {
            $table->id();
            $table->string('nom')->unique();
            $table->string('couleur')->nullable(); // ex. "#2563eb" pour l'affichage
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('etiquettes');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Une « session » (séance datée) : promo de formation, journée aura, etc.
     * Nommée `seances` car `sessions` est réservé par Laravel.
     */
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->dateTime('date_debut')->nullable();
            $table->string('lieu')->nullable();
            $table->unsignedInteger('capacite')->nullable(); // null ou 0 = pas de limite
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};

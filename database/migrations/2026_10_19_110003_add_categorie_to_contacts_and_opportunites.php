<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Rattachement facultatif d'un contact et d'une opportunité à une catégorie
     * (le pôle en est déduit). Détachement, jamais suppression, si la catégorie part.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('categorie_id')
                ->nullable()
                ->after('temperature')
                ->constrained('categories')
                ->nullOnDelete();
        });

        Schema::table('opportunites', function (Blueprint $table) {
            $table->foreignId('categorie_id')
                ->nullable()
                ->after('contact_id')
                ->constrained('categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_id');
        });

        Schema::table('opportunites', function (Blueprint $table) {
            $table->dropConstrainedForeignId('categorie_id');
        });
    }
};

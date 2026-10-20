<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liens de l'offre propres à un pôle (pages de l'offre + liens Fluent Booking).
     * L'assistant s'en sert pour rédiger le mail de prospection selon le pôle du contact.
     */
    public function up(): void
    {
        Schema::table('poles', function (Blueprint $table) {
            $table->text('liens_offre')->nullable()->after('couleur');
        });
    }

    public function down(): void
    {
        Schema::table('poles', function (Blueprint $table) {
            $table->dropColumn('liens_offre');
        });
    }
};

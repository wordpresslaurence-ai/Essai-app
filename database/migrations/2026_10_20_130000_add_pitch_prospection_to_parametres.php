<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pitch de prospection : texte libre (présentation, offre, ton) qui sert de
     * matière à l'assistant pour rédiger les mails de prospection.
     */
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->text('pitch_prospection')->nullable()->after('lien_journee_aura');
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('pitch_prospection');
        });
    }
};

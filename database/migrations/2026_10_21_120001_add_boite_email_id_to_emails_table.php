<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trace la boîte d'origine de chaque e-mail importé (brique 009).
 * Colonne additive : les e-mails existants restent valides (valeur nulle).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->foreignId('boite_email_id')
                ->nullable()
                ->after('boite')
                ->constrained('boites_email')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('emails', function (Blueprint $table) {
            $table->dropConstrainedForeignId('boite_email_id');
        });
    }
};

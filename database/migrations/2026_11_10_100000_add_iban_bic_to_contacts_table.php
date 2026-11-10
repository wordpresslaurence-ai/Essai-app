<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Coordonnées bancaires (pour payer/rembourser une salle, un partenaire…).
            $table->string('iban', 40)->nullable()->after('tarif');
            $table->string('bic', 15)->nullable()->after('iban');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['iban', 'bic']);
        });
    }
};

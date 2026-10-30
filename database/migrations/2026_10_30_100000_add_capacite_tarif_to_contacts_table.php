<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Attributs « lieu / salle » : capacité d'accueil et tarif de location.
            // Renseignés à la main ou par le débrief d'appel.
            $table->unsignedInteger('capacite')->nullable()->after('notes');
            $table->string('tarif')->nullable()->after('capacite');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn(['capacite', 'tarif']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            // Rôle du contact pour CETTE opportunité : participant / partenaire / les deux.
            $table->string('role')->nullable()->after('date_creation');
        });
    }

    public function down(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Contact « à la une » : mis en avant sur le tableau de bord (étoile).
            $table->boolean('a_la_une')->default(false)->index()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('a_la_une');
        });
    }
};

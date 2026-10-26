<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etiquettes', function (Blueprint $table) {
            $table->boolean('masquer_par_defaut')->default(false)->after('couleur');
        });

        // Les étiquettes « dormant(es) » existantes sont masquées par défaut.
        DB::table('etiquettes')
            ->whereRaw('LOWER(nom) LIKE ?', ['dormant%'])
            ->update(['masquer_par_defaut' => true]);
    }

    public function down(): void
    {
        Schema::table('etiquettes', function (Blueprint $table) {
            $table->dropColumn('masquer_par_defaut');
        });
    }
};

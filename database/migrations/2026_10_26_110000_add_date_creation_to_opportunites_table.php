<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            // Date de création de l'opportunité (saisissable, pré-remplie au jour même).
            $table->date('date_creation')->nullable()->after('montant');
        });

        // Les opportunités existantes prennent leur date d'enregistrement.
        DB::table('opportunites')
            ->whereNull('date_creation')
            ->update(['date_creation' => DB::raw('date(created_at)')]);
    }

    public function down(): void
    {
        Schema::table('opportunites', function (Blueprint $table) {
            $table->dropColumn('date_creation');
        });
    }
};

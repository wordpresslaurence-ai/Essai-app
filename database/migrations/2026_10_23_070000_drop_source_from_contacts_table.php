<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Correspondance ancien « source » → nouvelle « origine ». */
    private const MAP = [
        'linkedin' => 'linkedin',
        'instagram' => 'instagram',
        'facebook' => 'facebook',
        'site_web' => 'site_formulaire',
        'email' => 'spontane',
        'telephone' => 'spontane',
        'autre' => 'autre',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('contacts', 'source')) {
            return;
        }

        // On recopie l'ancienne source dans « origine » quand celle-ci est vide,
        // pour ne perdre aucune information avant de supprimer la colonne.
        foreach (self::MAP as $source => $origine) {
            DB::table('contacts')
                ->where('source', $source)
                ->where(fn ($q) => $q->whereNull('origine')->orWhere('origine', ''))
                ->update(['origine' => $origine]);
        }

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('source')->nullable()->after('secteur');
        });
    }
};

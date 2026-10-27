<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('candidats_prospection', function (Blueprint $table) {
            // Lien campagne-candidat-contact + idempotence de l'import.
            $table->foreignId('contact_id')->nullable()->after('recherche_prospection_id')
                ->constrained('contacts')->nullOnDelete();
            $table->timestamp('importe_at')->nullable()->after('motif_ecart');
        });
    }

    public function down(): void
    {
        Schema::table('candidats_prospection', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropColumn('importe_at');
        });
    }
};

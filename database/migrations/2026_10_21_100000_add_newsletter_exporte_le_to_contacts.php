<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Date du dernier export « newsletter » d'un contact (vers FluentCRM via CSV).
     * Permet de n'exporter ensuite que les nouveaux.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dateTime('newsletter_exporte_le')->nullable()->after('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('newsletter_exporte_le');
        });
    }
};

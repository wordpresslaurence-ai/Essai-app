<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * - contacts.suivi_pause_le : « conversation terminée » — le contact sort des
     *   listes « réponse due » / « à relancer » jusqu'au prochain e-mail échangé.
     * - seances.lien_groupe : lien d'invitation au groupe / à la communauté WhatsApp.
     */
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dateTime('suivi_pause_le')->nullable();
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->string('lien_groupe', 500)->nullable()->after('lien_inscription');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('lien_groupe');
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn('suivi_pause_le');
        });
    }
};

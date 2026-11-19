<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accès aux canaux Telegram par trimestre (brique 019).
     * - canaux_telegram : les canaux (ou groupes) où le robot est administrateur,
     *   détectés automatiquement ;
     * - seances : canal relié, fin de l'accès (commune à tous), date limite d'entrée,
     *   date de création de la tâche de renouvellement ;
     * - inscriptions : lien d'accès personnel, envoi, entrée dans le canal, retrait.
     */
    public function up(): void
    {
        Schema::create('canaux_telegram', function (Blueprint $table) {
            $table->id();
            $table->string('chat_id', 32)->unique();
            $table->string('titre')->nullable();
            $table->string('type')->default('channel');
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->foreignId('canal_telegram_id')->nullable()->constrained('canaux_telegram')->nullOnDelete();
            $table->date('fin_acces')->nullable();
            $table->date('limite_entree')->nullable();
            $table->dateTime('renouvellement_cree_le')->nullable();
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->string('acces_lien')->nullable()->index();
            $table->dateTime('acces_lien_cree_le')->nullable();
            $table->dateTime('acces_envoye_le')->nullable();
            $table->string('acces_telegram_id', 32)->nullable();
            $table->dateTime('acces_rejoint_le')->nullable();
            $table->dateTime('acces_retire_le')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropIndex(['acces_lien']);
            $table->dropColumn(['acces_lien', 'acces_lien_cree_le', 'acces_envoye_le', 'acces_telegram_id', 'acces_rejoint_le', 'acces_retire_le']);
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('canal_telegram_id');
            $table->dropColumn(['fin_acces', 'limite_entree', 'renouvellement_cree_le']);
        });

        Schema::dropIfExists('canaux_telegram');
    }
};

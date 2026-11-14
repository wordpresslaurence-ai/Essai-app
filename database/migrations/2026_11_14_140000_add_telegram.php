<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telegram (brique 018) : un robot relié au CRM.
     * - parametres : jeton du robot (chiffré), secret du webhook, nom du robot ;
     * - contacts : compte Telegram relié, et jeton de son lien personnel ;
     * - messages_telegram : les messages reçus par le robot et les réponses envoyées.
     */
    public function up(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->text('telegram_jeton')->nullable();
            $table->string('telegram_secret')->nullable();
            $table->string('telegram_robot')->nullable();
        });

        Schema::table('contacts', function (Blueprint $table) {
            $table->string('telegram_id', 32)->nullable()->index();
            $table->string('telegram_nom')->nullable();
            $table->string('telegram_jeton_lien', 40)->nullable()->unique();
        });

        Schema::create('messages_telegram', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            $table->string('chat_id', 32)->index();
            $table->string('sens');                     // entrant | sortant
            $table->string('type')->default('texte');   // texte | vocal | photo | video | document | autre
            $table->text('texte')->nullable();
            $table->string('expediteur')->nullable();   // nom affiché dans Telegram
            $table->string('telegram_message_id', 32)->nullable();
            $table->dateTime('traite_le')->nullable();  // message entrant : vu / répondu
            $table->timestamps();
            $table->unique(['chat_id', 'sens', 'telegram_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages_telegram');

        Schema::table('contacts', function (Blueprint $table) {
            $table->dropUnique(['telegram_jeton_lien']);
            $table->dropIndex(['telegram_id']);
            $table->dropColumn(['telegram_id', 'telegram_nom', 'telegram_jeton_lien']);
        });

        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn(['telegram_jeton', 'telegram_secret', 'telegram_robot']);
        });
    }
};

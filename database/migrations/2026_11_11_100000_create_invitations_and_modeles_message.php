<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Campagne d'invitations à une session (brique 012) :
     * modèles de messages réutilisables, suivi des invitations par contact,
     * et lien d'inscription de la session.
     */
    public function up(): void
    {
        Schema::create('modeles_message', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('canal')->default('email'); // email | message
            $table->string('objet')->nullable();
            $table->text('corps');
            $table->timestamps();
        });

        Schema::create('invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->string('statut')->default('a_inviter'); // a_inviter | invitee | relancee | a_repondu | declinee
            $table->foreignId('modele_message_id')->nullable()->constrained('modeles_message')->nullOnDelete();
            $table->dateTime('invitee_le')->nullable();
            $table->dateTime('relancee_le')->nullable();
            $table->timestamps();

            $table->unique(['seance_id', 'contact_id']);
            $table->index('statut');
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->string('lien_inscription', 500)->nullable()->after('lieu_contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('lien_inscription');
        });

        Schema::dropIfExists('invitations');
        Schema::dropIfExists('modeles_message');
    }
};

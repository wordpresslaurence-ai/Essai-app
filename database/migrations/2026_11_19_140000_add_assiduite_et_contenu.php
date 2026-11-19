<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Assiduité et calendrier de contenu (brique 020).
     * - pratiques_telegram : publications marquées #pratique dans un canal ;
     * - pratiques_faites : clic sur « ✓ Fait » sous une pratique ;
     * - contenus : plan de publication d'un trimestre ;
     * - inscriptions.assiduite_vu_le : décrochage déjà signalé et vu ;
     * - parametres.telegram_branchement : version du branchement (pour le mettre à jour seul).
     */
    public function up(): void
    {
        Schema::create('pratiques_telegram', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canal_telegram_id')->constrained('canaux_telegram')->cascadeOnDelete();
            $table->string('message_id', 32);
            $table->string('titre')->nullable();
            $table->string('type')->default('texte');
            $table->dateTime('publie_le');
            $table->timestamps();
            $table->unique(['canal_telegram_id', 'message_id']);
            $table->index('publie_le');
        });

        Schema::create('pratiques_faites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pratique_telegram_id')->constrained('pratiques_telegram')->cascadeOnDelete();
            $table->string('telegram_user_id', 32);
            $table->foreignId('inscription_id')->nullable()->constrained('inscriptions')->nullOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->dateTime('fait_le');
            $table->timestamps();
            $table->unique(['pratique_telegram_id', 'telegram_user_id']);
            $table->index('telegram_user_id');
        });

        Schema::create('contenus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->date('date_prevue');
            $table->string('format')->default('vocal');
            $table->string('titre');
            $table->string('statut')->default('a_enregistrer');
            $table->text('notes')->nullable();
            $table->foreignId('pratique_telegram_id')->nullable()->constrained('pratiques_telegram')->nullOnDelete();
            $table->timestamps();
            $table->index('date_prevue');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dateTime('assiduite_vu_le')->nullable();
        });

        Schema::table('parametres', function (Blueprint $table) {
            $table->unsignedInteger('telegram_branchement')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('telegram_branchement');
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn('assiduite_vu_le');
        });

        Schema::dropIfExists('contenus');
        Schema::dropIfExists('pratiques_faites');
        Schema::dropIfExists('pratiques_telegram');
    }
};

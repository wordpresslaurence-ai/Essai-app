<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Suivi des paiements par stage et demande d'avis (brique 015) :
     * - seances.prix : prix du stage (à défaut, celui de la catégorie) ;
     * - inscriptions.montant_du : montant propre à une personne (tarif réduit…) ;
     * - paiements : les versements reçus pour une inscription (acompte, solde…) ;
     * - inscriptions.avis_* : demande d'avis envoyée, avis reçu, texte, accord de publication ;
     * - parametres.lien_avis : page où laisser un avis public.
     */
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->decimal('prix', 10, 2)->nullable();
        });

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->decimal('montant_du', 10, 2)->nullable();
            $table->dateTime('avis_demande_le')->nullable();
            $table->dateTime('avis_recu_le')->nullable();
            $table->text('avis_texte')->nullable();
            $table->boolean('avis_publiable')->default(false);
        });

        Schema::create('paiements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscription_id')->constrained('inscriptions')->cascadeOnDelete();
            $table->decimal('montant', 10, 2);
            $table->date('paye_le');
            $table->string('mode')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::table('parametres', function (Blueprint $table) {
            $table->string('lien_avis', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('parametres', function (Blueprint $table) {
            $table->dropColumn('lien_avis');
        });

        Schema::dropIfExists('paiements');

        Schema::table('inscriptions', function (Blueprint $table) {
            $table->dropColumn(['montant_du', 'avis_demande_le', 'avis_recu_le', 'avis_texte', 'avis_publiable']);
        });

        Schema::table('seances', function (Blueprint $table) {
            $table->dropColumn('prix');
        });
    }
};

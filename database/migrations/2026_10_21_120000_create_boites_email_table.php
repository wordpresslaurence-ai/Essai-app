<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Boîtes e-mail multiples (brique 009) : chaque boîte a un fournisseur
 * (gmail/hostinger/autre), ses identifiants chiffrés, et un pôle facultatif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('boites_email', function (Blueprint $table) {
            $table->id();
            $table->string('libelle')->nullable();
            $table->string('fournisseur')->default('gmail'); // gmail | hostinger | autre
            $table->string('email');
            $table->text('mot_de_passe')->nullable(); // chiffré (cast « encrypted »)
            $table->string('imap_host')->nullable();   // null => preset selon le fournisseur
            $table->unsignedInteger('imap_port')->default(993);
            $table->string('dossier_envoyes')->nullable(); // null => preset
            $table->foreignId('pole_id')->nullable()->constrained('poles')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamp('derniere_synchro_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('boites_email');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * E-mails synchronisés depuis la boîte, rattachés à un contact connu
     * (par adresse). Un mail d'adresse inconnue n'est pas stocké ici.
     */
    public function up(): void
    {
        Schema::create('emails', function (Blueprint $table) {
            $table->id();

            $table->foreignId('contact_id')
                ->constrained('contacts')
                ->cascadeOnDelete();

            $table->string('direction')->index(); // entrant | sortant
            $table->string('de');
            $table->text('a');
            $table->string('objet')->nullable();
            $table->text('extrait')->nullable();
            $table->longText('corps')->nullable();
            $table->dateTime('date_email')->index();
            $table->string('message_id')->unique(); // déduplication
            $table->string('fil_id')->nullable()->index();
            $table->string('boite'); // adresse de la boîte source
            $table->boolean('traite')->default(false); // réponse déjà apportée ?

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};

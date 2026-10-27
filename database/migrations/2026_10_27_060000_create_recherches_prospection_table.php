<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Campagnes de prospection (Option B : le CRM prépare le brief, ChatGPT
        // conversationnel fait la recherche, le CRM trie et importe le résultat).
        Schema::create('recherches_prospection', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            // L'« offre » = une catégorie du CRM. On garde une copie figée de sa
            // description, pour conserver le contexte même si l'offre change ensuite.
            $table->foreignId('categorie_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->text('offre_copie')->nullable();

            $table->json('sources')->nullable();       // ['web','linkedin','facebook','instagram']
            $table->unsignedSmallInteger('nombre')->default(10);
            $table->string('role')->nullable();         // participant / partenaire / les_deux

            $table->text('personnes_recherchees')->nullable();
            $table->text('zones')->nullable();
            $table->text('langues')->nullable();
            $table->text('criteres_obligatoires')->nullable();
            $table->text('criteres_souhaitables')->nullable();
            $table->text('profils_privilegier')->nullable();
            $table->text('profils_eviter')->nullable();
            $table->string('contact_requis')->nullable();
            $table->text('exclusions')->nullable();
            $table->boolean('enrichissement')->default(false);
            $table->text('instructions')->nullable();

            $table->string('statut')->default('brouillon');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recherches_prospection');
    }
};

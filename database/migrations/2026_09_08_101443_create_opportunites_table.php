<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Opportunités commerciales du pipeline (cf. spec 002-pipeline).
     */
    public function up(): void
    {
        Schema::create('opportunites', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            // Client lié ; conservé (détaché) si le contact est supprimé.
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->decimal('montant', 12, 2)->nullable();
            $table->string('etape')->default('nouveau')->index();
            $table->text('notes')->nullable();
            $table->date('date_cloture')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opportunites');
    }
};

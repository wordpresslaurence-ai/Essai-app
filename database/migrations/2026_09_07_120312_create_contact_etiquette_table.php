<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Table pivot : association N–N entre contacts et étiquettes (cf. EF-11).
     */
    public function up(): void
    {
        Schema::create('contact_etiquette', function (Blueprint $table) {
            $table->foreignId('contact_id')->constrained('contacts')->cascadeOnDelete();
            $table->foreignId('etiquette_id')->constrained('etiquettes')->cascadeOnDelete();
            $table->primary(['contact_id', 'etiquette_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_etiquette');
    }
};

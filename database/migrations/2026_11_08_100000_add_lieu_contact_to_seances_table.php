<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            // Contact (ex. salle/lieu) rattaché à la séance : donne son nom et sa ville.
            $table->foreignId('lieu_contact_id')
                ->nullable()
                ->after('lieu')
                ->constrained('contacts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('seances', function (Blueprint $table) {
            $table->dropConstrainedForeignId('lieu_contact_id');
        });
    }
};

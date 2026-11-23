<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Message « Une question ? » publié et épinglé par le robot dans chaque canal. */
    public function up(): void
    {
        Schema::table('canaux_telegram', function (Blueprint $table) {
            $table->string('message_epingle_id', 32)->nullable();
            $table->text('message_epingle_texte')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('canaux_telegram', function (Blueprint $table) {
            $table->dropColumn(['message_epingle_id', 'message_epingle_texte']);
        });
    }
};

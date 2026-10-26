<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Adresses e-mail secondaires d'un contact (l'adresse principale reste
        // sur contacts.email). Permet de reconnaître un contact qui écrit depuis
        // plusieurs adresses et d'éviter les faux « nouveaux prospects ».
        Schema::create('adresses_email', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained()->cascadeOnDelete();
            $table->string('adresse');
            $table->timestamps();

            $table->unique(['contact_id', 'adresse']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('adresses_email');
    }
};

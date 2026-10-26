<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            // Localisation
            $table->string('region')->nullable()->after('adresse_ville');
            // Présence en ligne
            $table->string('linkedin')->nullable()->after('site_web');
            $table->string('instagram')->nullable()->after('linkedin');
            $table->string('facebook')->nullable()->after('instagram');
            // Profil
            $table->string('langues')->nullable()->after('experience_yoga');
            $table->text('discipline_precision')->nullable()->after('discipline');
            $table->text('public_precision')->nullable()->after('langues');
            $table->text('formats_proposes')->nullable()->after('public_precision');
            // Prospection
            $table->string('role_commercial')->nullable()->after('angle_contact');
            $table->string('priorite_prospection')->nullable()->after('role_commercial');
            $table->text('interet_exprime')->nullable()->after('priorite_prospection');
            $table->text('besoin_exprime')->nullable()->after('interet_exprime');
            // Vérification
            $table->text('url_sources')->nullable()->after('besoin_exprime');
            $table->date('date_verification')->nullable()->after('url_sources');
            // RGPD
            $table->boolean('ne_plus_contacter')->default(false)->after('date_verification');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'region', 'linkedin', 'instagram', 'facebook', 'langues',
                'discipline_precision', 'public_precision', 'formats_proposes',
                'role_commercial', 'priorite_prospection', 'interet_exprime',
                'besoin_exprime', 'url_sources', 'date_verification', 'ne_plus_contacter',
            ]);
        });
    }
};

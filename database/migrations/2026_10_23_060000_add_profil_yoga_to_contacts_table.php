<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->string('discipline')->nullable()->after('secteur');
            $table->text('fait_observe')->nullable()->after('discipline');
            $table->text('angle_contact')->nullable()->after('fait_observe');
            $table->string('experience_yoga')->nullable()->after('angle_contact');
            $table->string('origine')->nullable()->after('source');
            $table->string('origine_precision')->nullable()->after('origine');
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropColumn([
                'discipline',
                'fait_observe',
                'angle_contact',
                'experience_yoga',
                'origine',
                'origine_precision',
            ]);
        });
    }
};

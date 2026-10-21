<?php

use App\Models\BoiteEmail;
use App\Models\Parametre;
use Illuminate\Database\Migrations\Migration;

/**
 * Reprise (brique 009) : si une adresse Gmail était configurée dans les anciens
 * réglages (Parametre), on crée automatiquement une boîte Gmail équivalente.
 * Sans perte de données ; ne fait rien si rien n'était configuré (cas production).
 */
return new class extends Migration
{
    public function up(): void
    {
        try {
            $p = Parametre::query()->first();

            if (! $p || blank($p->gmail_email)) {
                return;
            }

            if (BoiteEmail::where('email', $p->gmail_email)->exists()) {
                return;
            }

            BoiteEmail::create([
                'libelle' => 'Gmail',
                'fournisseur' => BoiteEmail::FOURNISSEUR_GMAIL,
                'email' => $p->gmail_email,
                'mot_de_passe' => $p->secret('gmail_app_password'),
                'active' => true,
                'derniere_synchro_at' => $p->derniere_synchro_at,
            ]);
        } catch (\Throwable) {
            // Reprise best-effort : on n'interrompt jamais la migration pour ça.
        }
    }

    public function down(): void
    {
        // Pas de retour en arrière destructif.
    }
};

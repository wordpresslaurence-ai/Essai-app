<?php

namespace Database\Seeders;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Etiquette;
use App\Models\Opportunite;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Données de démonstration (usage développement uniquement).
 * Lancer avec : php artisan db:seed --class=DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'demo@crm.be'],
            ['name' => 'Laurence', 'password' => bcrypt('password')]
        );

        $client = Etiquette::firstOrCreate(['nom' => 'Client'], ['couleur' => '#16a34a']);
        $prospect = Etiquette::firstOrCreate(['nom' => 'Prospect'], ['couleur' => '#2563eb']);
        Etiquette::firstOrCreate(['nom' => 'Fournisseur'], ['couleur' => '#d97706']);

        $acme = Contact::firstOrCreate(['nom' => 'ACME Belgium SPRL'], [
            'type' => Contact::TYPE_ENTREPRISE,
            'email' => 'contact@acme.be',
            'telephone' => '+32 2 123 45 67',
            'numero_entreprise' => '0403.170.701',
            'numero_tva' => 'BE0403.170.701',
            'site_web' => 'https://acme.be',
            'secteur' => 'Distribution',
            'adresse_rue' => 'Rue de la Loi 12',
            'adresse_code_postal' => '1000',
            'adresse_ville' => 'Bruxelles',
            'adresse_pays' => 'BE',
        ]);
        $acme->etiquettes()->syncWithoutDetaching([$client->id]);

        $novatech = Contact::firstOrCreate(['nom' => 'NovaTech SA'], [
            'type' => Contact::TYPE_ENTREPRISE,
            'email' => 'info@novatech.be',
            'telephone' => '+32 4 222 33 44',
            'secteur' => 'Informatique',
            'adresse_ville' => 'Liège',
            'adresse_pays' => 'BE',
            'source' => 'facebook',
            'temperature' => 'eleve',
        ]);
        $novatech->etiquettes()->syncWithoutDetaching([$prospect->id]);

        $marie = Contact::firstOrCreate(['nom' => 'Dubois', 'prenom' => 'Marie'], [
            'type' => Contact::TYPE_PERSONNE,
            'fonction' => 'Directrice achats',
            'email' => 'marie.dubois@acme.be',
            'telephone' => '+32 475 11 22 33',
            'entreprise_id' => $acme->id,
            'adresse_ville' => 'Bruxelles',
            'adresse_pays' => 'BE',
            'source' => 'linkedin',
            'temperature' => 'chaud',
        ]);
        $marie->etiquettes()->syncWithoutDetaching([$client->id]);

        $thomas = Contact::firstOrCreate(['nom' => 'Lambert', 'prenom' => 'Thomas'], [
            'type' => Contact::TYPE_PERSONNE,
            'fonction' => 'Directeur technique',
            'email' => 't.lambert@novatech.be',
            'entreprise_id' => $novatech->id,
        ]);

        $sophie = Contact::firstOrCreate(['nom' => 'Peeters', 'prenom' => 'Sophie'], [
            'type' => Contact::TYPE_PERSONNE,
            'fonction' => 'Indépendante',
            'email' => 'sophie.peeters@example.be',
            'telephone' => '+32 498 55 66 77',
            'source' => 'instagram',
            'temperature' => 'moyen',
        ]);

        // Opportunités de démonstration (pipeline)
        Opportunite::firstOrCreate(['titre' => 'Réassort trimestriel'], [
            'contact_id' => $acme->id, 'montant' => 4200, 'etape' => Opportunite::ETAPE_QUALIFIE,
        ]);
        Opportunite::firstOrCreate(['titre' => 'Audit technique'], [
            'contact_id' => $novatech->id, 'montant' => 1800, 'etape' => Opportunite::ETAPE_QUALIFIE,
        ]);
        Opportunite::firstOrCreate(['titre' => 'Accompagnement coaching'], [
            'contact_id' => $sophie->id, 'montant' => 950, 'etape' => Opportunite::ETAPE_PROPOSITION,
        ]);
        Opportunite::firstOrCreate(['titre' => 'Formation équipe'], [
            'contact_id' => $acme->id, 'montant' => 2400, 'etape' => Opportunite::ETAPE_GAGNE,
            'date_cloture' => now()->toDateString(),
        ]);
        Opportunite::firstOrCreate(['titre' => 'Prospection salon'], [
            'contact_id' => $novatech->id, 'montant' => 600, 'etape' => Opportunite::ETAPE_NOUVEAU,
        ]);

        // Activités de démonstration
        Activite::firstOrCreate(['titre' => 'Rappeler Marie Dubois'], [
            'type' => Activite::TYPE_APPEL, 'contact_id' => $marie->id, 'echeance' => now()->setTime(11, 0),
        ]);
        Activite::firstOrCreate(['titre' => 'Envoyer la proposition à NovaTech'], [
            'type' => Activite::TYPE_EMAIL, 'contact_id' => $novatech->id, 'echeance' => now()->setTime(14, 30),
        ]);
        Activite::firstOrCreate(['titre' => 'Café avec Sophie Peeters'], [
            'type' => Activite::TYPE_RDV, 'contact_id' => $sophie->id, 'echeance' => now()->addDay()->setTime(16, 0),
        ]);
        Activite::firstOrCreate(['titre' => 'Relancer devis réassort'], [
            'type' => Activite::TYPE_TACHE, 'contact_id' => $acme->id, 'echeance' => now()->subDay()->setTime(9, 0),
        ]);

        // E-mails de démonstration (assistant) — pour tester l'analyse et le brief
        $boite = 'laurencehomeharmony@gmail.com';

        // Marie : dernier message REÇU -> « tu dois une réponse »
        Email::firstOrCreate(['message_id' => 'demo-marie-1'], [
            'contact_id' => $marie->id, 'direction' => Email::DIRECTION_SORTANT,
            'de' => $boite, 'a' => $marie->email,
            'objet' => 'Votre réassort trimestriel',
            'extrait' => 'Bonjour Marie, voici notre proposition pour le réassort de ce trimestre. Je reste à votre écoute.',
            'date_email' => now()->subDays(9), 'boite' => $boite, 'traite' => true,
        ]);
        Email::firstOrCreate(['message_id' => 'demo-marie-2'], [
            'contact_id' => $marie->id, 'direction' => Email::DIRECTION_ENTRANT,
            'de' => $marie->email, 'a' => $boite,
            'objet' => 'Re: Votre réassort trimestriel',
            'extrait' => 'Merci beaucoup ! Pouvez-vous préciser les délais de livraison et voir si une petite remise est possible ?',
            'date_email' => now()->subDay(), 'boite' => $boite,
        ]);

        // Sophie : message REÇU récent -> « tu dois une réponse »
        Email::firstOrCreate(['message_id' => 'demo-sophie-1'], [
            'contact_id' => $sophie->id, 'direction' => Email::DIRECTION_ENTRANT,
            'de' => $sophie->email, 'a' => $boite,
            'objet' => 'Intéressée par un accompagnement',
            'extrait' => "Bonjour, j'aimerais en savoir plus sur votre accompagnement coaching. Avez-vous des disponibilités pour un premier échange ?",
            'date_email' => now()->subDays(2), 'boite' => $boite,
        ]);

        // Thomas : dernier message ENVOYÉ, ancien -> « à relancer »
        Email::firstOrCreate(['message_id' => 'demo-thomas-1'], [
            'contact_id' => $thomas->id, 'direction' => Email::DIRECTION_SORTANT,
            'de' => $boite, 'a' => $thomas->email,
            'objet' => "Proposition d'audit technique",
            'extrait' => 'Bonjour Thomas, suite à notre échange, voici notre proposition d’audit technique. Dites-moi ce que vous en pensez.',
            'date_email' => now()->subDays(12), 'boite' => $boite, 'traite' => true,
        ]);
    }
}

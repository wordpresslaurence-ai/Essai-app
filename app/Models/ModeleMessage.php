<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Modèle de message réutilisable (invitation, relance…), avec des variables
 * entre accolades remplacées par les données du contact et de la session.
 */
class ModeleMessage extends Model
{
    public const CANAL_EMAIL = 'email';

    public const CANAL_MESSAGE = 'message';

    public const CANAUX = [
        self::CANAL_EMAIL => 'E-mail',
        self::CANAL_MESSAGE => 'Message court (WhatsApp, message privé)',
    ];

    /** Variables disponibles => explication affichée dans l'écran de gestion. */
    public const VARIABLES = [
        'prenom' => 'Prénom du contact (ou son nom complet s’il n’a pas de prénom)',
        'nom' => 'Nom du contact',
        'ville' => 'Ville du contact',
        'entreprise' => 'Entreprise ou studio du contact',
        'session' => 'Titre de la session',
        'date' => 'Date de la session (ex. dimanche 13 décembre 2026)',
        'heure' => 'Heure de la session (ex. 10 h 30)',
        'lieu' => 'Lieu de la session',
        'lien' => 'Lien d’inscription de la session',
        'lien_groupe' => 'Lien du groupe ou de la communauté WhatsApp de la session',
        'moi' => 'Ton nom (celui de ton compte)',
    ];

    protected $table = 'modeles_message';

    protected $fillable = ['nom', 'canal', 'objet', 'corps'];

    public function estEmail(): bool
    {
        return $this->canal === self::CANAL_EMAIL;
    }

    public function libelleCanal(): string
    {
        return $this->canal === self::CANAL_EMAIL ? 'E-mail' : 'Message court';
    }

    /**
     * Rend l'objet et le corps pour un contact et une session donnés.
     *
     * @return array{objet: string, corps: string}
     */
    public function rendre(Contact $contact, Seance $seance, ?string $moi = null): array
    {
        $valeurs = self::valeurs($contact, $seance, $moi);

        return [
            'objet' => self::remplacer((string) $this->objet, $valeurs),
            'corps' => self::remplacer((string) $this->corps, $valeurs),
        ];
    }

    /**
     * Valeur de chaque variable pour ce contact et cette session.
     *
     * @return array<string, string>
     */
    public static function valeurs(Contact $contact, Seance $seance, ?string $moi = null): array
    {
        $date = $seance->date_debut;

        $heure = '';
        if ($date && $date->format('H:i') !== '00:00') {
            $heure = $date->format('G').' h'.($date->format('i') !== '00' ? ' '.$date->format('i') : '');
        }

        $lieu = (string) $seance->lieu;
        if ($seance->lieuContact) {
            $lieu = $seance->lieuContact->nomComplet()
                .(filled($seance->lieuContact->adresse_ville) ? ', '.$seance->lieuContact->adresse_ville : '');
        }

        return [
            'prenom' => filled($contact->prenom) ? (string) $contact->prenom : $contact->nomComplet(),
            'nom' => (string) $contact->nom,
            'ville' => (string) $contact->adresse_ville,
            'entreprise' => $contact->estEntreprise() ? (string) $contact->nom : (string) $contact->entreprise?->nom,
            'session' => (string) $seance->titre,
            'date' => $date ? $date->translatedFormat('l j F Y') : '',
            'heure' => $heure,
            'lieu' => $lieu,
            'lien' => (string) $seance->lien_inscription,
            'lien_groupe' => (string) $seance->lien_groupe,
            'moi' => (string) $moi,
        ];
    }

    /**
     * Remplace {variable} par sa valeur ; une variable connue mais vide devient
     * un repère « [variable] » pour que le manque se voie avant l'envoi.
     *
     * @param  array<string, string>  $valeurs
     */
    public static function remplacer(string $texte, array $valeurs): string
    {
        return preg_replace_callback('/\{([a-z_]+)\}/', function (array $m) use ($valeurs) {
            if (! array_key_exists($m[1], $valeurs)) {
                return $m[0]; // accolades inconnues : laissées telles quelles
            }

            return trim($valeurs[$m[1]]) !== '' ? $valeurs[$m[1]] : '['.$m[1].']';
        }, $texte) ?? $texte;
    }

    /**
     * Modèles de départ proposés par le bouton « Ajouter les modèles de départ ».
     *
     * @return array<int, array{nom: string, canal: string, objet: ?string, corps: string}>
     */
    public static function modelesDeDepart(): array
    {
        return [
            [
                'nom' => 'Invitation — e-mail à un professeur',
                'canal' => self::CANAL_EMAIL,
                'objet' => '{session}, {date}',
                'corps' => "Bonjour {prenom},\n\n"
                    ."Je suis {moi}. Je vous écris parce que vous enseignez à {ville}, et que [un détail précis : ce qui vous a plu dans son approche, son site, ses cours].\n\n"
                    ."[Deux phrases pour présenter le sujet et ce que la personne y gagne.]\n\n"
                    ."Je propose « {session} », {date} à {heure} ({lieu}) : une pratique guidée, un échange, et vos questions.\n\n"
                    ."Pour réserver : {lien}\n\n"
                    ."Si le sujet ne vous concerne pas, dites-le-moi simplement et je ne vous écrirai plus.\n\n"
                    ."Bien à vous,\n{moi}",
            ],
            [
                'nom' => 'Invitation — message court',
                'canal' => self::CANAL_MESSAGE,
                'objet' => null,
                'corps' => 'Bonjour {prenom}, je suis {moi}. J’organise « {session} », {date} à {heure} ({lieu}) : une pratique guidée et un temps d’échange. '
                    .'J’ai pensé à vous en voyant [un détail précis]. Voulez-vous que je vous envoie le lien ?',
            ],
            [
                'nom' => 'Invitation — e-mail à un studio',
                'canal' => self::CANAL_EMAIL,
                'objet' => 'Pour les professeurs de {entreprise} : {session}',
                'corps' => "Bonjour {prenom},\n\n"
                    ."Je suis {moi}.\n\n"
                    ."[Deux phrases pour présenter la formation et à qui elle s’adresse.]\n\n"
                    ."Pour la faire découvrir, je propose « {session} », {date} à {heure} ({lieu}). Accepteriez-vous de transmettre l’invitation aux professeurs de votre équipe ?\n\n"
                    ."Le lien de réservation : {lien}\n\n"
                    ."Bien à vous,\n{moi}",
            ],
            [
                'nom' => 'Relance — une seule fois',
                'canal' => self::CANAL_EMAIL,
                'objet' => 'Rappel : {session}, {date}',
                'corps' => "Bonjour {prenom},\n\n"
                    ."Un mot pour vous rappeler « {session} », {date} à {heure} ({lieu}). Il reste des places.\n\n"
                    ."Pour réserver : {lien}\n\n"
                    ."Si la date ne vous convient pas, répondez-moi et je vous préviendrai de la prochaine.\n\n"
                    ."Bien à vous,\n{moi}",
            ],
        ];
    }
}

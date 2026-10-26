<?php

namespace App\Services\Email;

use App\Models\Activite;
use App\Models\BoiteEmail;
use App\Models\Contact;
use App\Models\Email;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Récupère les e-mails de TOUTES les boîtes actives (brique 009), les rattache
 * aux contacts connus (par adresse, « ne rien rater » : sans filtrage par pôle),
 * marque le sens (entrant/sortant), évite les doublons (message_id global) et
 * crée une tâche « créer le prospect » pour un expéditeur inconnu.
 */
class SynchronisationEmails
{
    public function __construct(private LecteurEmails $lecteur) {}

    public function executer(): ResultatSynchronisation
    {
        $resultat = new ResultatSynchronisation;

        $boites = BoiteEmail::actives()->get();
        if ($boites->isEmpty()) {
            return $resultat;
        }

        // Index des contacts connus par adresse e-mail (partagé entre les boîtes).
        // On prend en compte l'adresse principale ET les adresses secondaires,
        // afin de reconnaître un contact qui écrit depuis plusieurs adresses.
        $contactsParEmail = collect();
        Contact::query()
            ->with('adressesEmail')
            ->where(fn ($q) => $q->whereNotNull('email')->orHas('adressesEmail'))
            ->get()
            ->each(function (Contact $c) use ($contactsParEmail) {
                foreach ($c->toutesAdressesEmail() as $adresse) {
                    $cle = $this->normaliser($adresse);
                    if ($cle !== '' && ! $contactsParEmail->has($cle)) {
                        $contactsParEmail->put($cle, $c);
                    }
                }
            });

        // Évite de recréer la même tâche « créer le prospect » plusieurs fois dans ce tour.
        $adressesTacheesCeTour = [];

        foreach ($boites as $boite) {
            $this->synchroniserBoite($boite, $contactsParEmail, $adressesTacheesCeTour, $resultat);
            $resultat->boitesLues++;
        }

        return $resultat;
    }

    /**
     * @param  \Illuminate\Support\Collection<string, Contact>  $contactsParEmail
     * @param  list<string>  $adressesTacheesCeTour
     */
    private function synchroniserBoite(
        BoiteEmail $boite,
        $contactsParEmail,
        array &$adressesTacheesCeTour,
        ResultatSynchronisation $resultat,
    ): void {
        $adresseBoite = $this->normaliser($boite->email);
        $messages = $this->lecteur->recupererDepuis($boite, $boite->derniere_synchro_at);

        foreach ($messages as $message) {
            // Déduplication globale (un même message présent dans deux boîtes = une fois).
            if (Email::where('message_id', $message->messageId)->exists()) {
                $resultat->ignores++;

                continue;
            }

            $expediteur = $this->normaliser($message->expediteur);
            $direction = $expediteur === $adresseBoite
                ? Email::DIRECTION_SORTANT
                : Email::DIRECTION_ENTRANT;

            // Adresses de « l'autre partie » (hors adresse de la boîte).
            $autres = collect([$message->expediteur, ...$message->destinataires])
                ->map(fn ($a) => $this->normaliser($a))
                ->filter(fn ($a) => $a !== '' && $a !== $adresseBoite)
                ->unique();

            // « Ne rien rater » : on rattache à toute fiche ayant cette adresse (sans filtre de pôle).
            $contact = $autres
                ->map(fn ($a) => $contactsParEmail->get($a))
                ->filter()
                ->first();

            if ($contact) {
                Email::create([
                    'contact_id' => $contact->id,
                    'direction' => $direction,
                    'de' => $message->expediteur,
                    'a' => implode(', ', $message->destinataires),
                    'objet' => $message->objet,
                    'extrait' => $message->extrait(),
                    'corps' => $message->texte,
                    'date_email' => $message->date,
                    'message_id' => $message->messageId,
                    'fil_id' => $message->filId,
                    'boite' => $boite->email,
                    'boite_email_id' => $boite->id,
                    'traite' => $direction === Email::DIRECTION_SORTANT,
                ]);
                $resultat->emailsImportes++;

                continue;
            }

            // Adresse inconnue : tâche « créer le prospect » (seulement entrant).
            if ($direction === Email::DIRECTION_ENTRANT && $expediteur !== '') {
                if (! in_array($expediteur, $adressesTacheesCeTour, true)
                    && $this->creerTacheProspect($expediteur, $message->objet)) {
                    $resultat->tachesCreees++;
                    $adressesTacheesCeTour[] = $expediteur;
                }
            }

            $resultat->ignores++;
        }

        $boite->update(['derniere_synchro_at' => Carbon::now()]);
    }

    /** Crée la tâche si aucune tâche ouverte ne vise déjà cette adresse. */
    private function creerTacheProspect(string $adresse, ?string $objet): bool
    {
        $titre = 'Créer le prospect : '.$adresse;

        $existe = Activite::query()
            ->where('type', Activite::TYPE_TACHE)
            ->whereNull('terminee_at')
            ->where('titre', $titre)
            ->exists();

        if ($existe) {
            return false;
        }

        Activite::create([
            'titre' => $titre,
            'type' => Activite::TYPE_TACHE,
            'notes' => $objet ? 'Objet du mail : '.Str::limit($objet, 120) : null,
        ]);

        return true;
    }

    private function normaliser(?string $adresse): string
    {
        return mb_strtolower(trim((string) $adresse));
    }
}

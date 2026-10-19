<?php

namespace App\Services\Email;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Email;
use App\Models\Parametre;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Récupère les e-mails via le lecteur, les rattache aux contacts connus (par
 * adresse), marque le sens (entrant/sortant), évite les doublons et crée une
 * tâche « créer le prospect » pour un expéditeur inconnu.
 */
class SynchronisationEmails
{
    public function __construct(private LecteurEmails $lecteur) {}

    public function executer(): ResultatSynchronisation
    {
        $p = Parametre::instance();
        $boite = $this->normaliser($p->gmail_email ?? '');
        $resultat = new ResultatSynchronisation;

        $messages = $this->lecteur->recupererDepuis($p->derniere_synchro_at);

        // Index des contacts connus par adresse e-mail.
        $contactsParEmail = Contact::query()
            ->whereNotNull('email')
            ->get()
            ->keyBy(fn (Contact $c) => $this->normaliser($c->email));

        $adressesTacheesCeTour = [];

        foreach ($messages as $message) {
            // Déduplication
            if (Email::where('message_id', $message->messageId)->exists()) {
                $resultat->ignores++;

                continue;
            }

            $expediteur = $this->normaliser($message->expediteur);
            $direction = $expediteur === $boite
                ? Email::DIRECTION_SORTANT
                : Email::DIRECTION_ENTRANT;

            // Adresses de « l'autre partie » (hors boîte synchronisée)
            $autres = collect([$message->expediteur, ...$message->destinataires])
                ->map(fn ($a) => $this->normaliser($a))
                ->filter(fn ($a) => $a !== '' && $a !== $boite)
                ->unique();

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
                    'boite' => $p->gmail_email,
                    'traite' => $direction === Email::DIRECTION_SORTANT,
                ]);
                $resultat->emailsImportes++;

                continue;
            }

            // Adresse inconnue : tâche « créer le prospect » (seulement entrant)
            if ($direction === Email::DIRECTION_ENTRANT && $expediteur !== '') {
                if (! in_array($expediteur, $adressesTacheesCeTour, true)
                    && $this->creerTacheProspect($expediteur, $message->objet)) {
                    $resultat->tachesCreees++;
                    $adressesTacheesCeTour[] = $expediteur;
                }
            }

            $resultat->ignores++;
        }

        $p->update(['derniere_synchro_at' => Carbon::now()]);

        return $resultat;
    }

    /** Crée la tâche si aucune tâche ouverte ne mentionne déjà cette adresse. */
    private function creerTacheProspect(string $adresse, ?string $objet): bool
    {
        $existe = Activite::query()
            ->where('type', Activite::TYPE_TACHE)
            ->whereNull('terminee_at')
            ->where('notes', 'like', '%'.$adresse.'%')
            ->exists();

        if ($existe) {
            return false;
        }

        Activite::create([
            'titre' => 'Créer le prospect',
            'type' => Activite::TYPE_TACHE,
            'notes' => 'Créer le prospect : '.$adresse.($objet ? ' — '.Str::limit($objet, 120) : ''),
        ]);

        return true;
    }

    private function normaliser(?string $adresse): string
    {
        return mb_strtolower(trim((string) $adresse));
    }
}

<?php

namespace App\Services\Reservations;

use App\Models\Activite;
use App\Models\Contact;
use App\Models\Inscription;
use App\Models\ReservationRecue;
use App\Models\Seance;
use Illuminate\Support\Carbon;

/**
 * Traite une réservation envoyée par le site (FluentBooking) : retrouve ou crée
 * le contact, puis l'inscrit à la session du même jour, ou note un rendez-vous.
 *
 * Le format exact dépend du réglage FluentBooking (« toutes les données » ou
 * « champs choisis ») : la lecture est donc tolérante, et chaque appel est
 * conservé dans un journal pour pouvoir ajuster.
 */
class TraitementReservation
{
    /** Fuseau des heures saisies dans le CRM (les réservations arrivent en UTC). */
    public const FUSEAU_LOCAL = 'Europe/Brussels';

    /** Noms de champs acceptés, par ordre de préférence. */
    private const CLES = [
        'email' => ['email', 'guest_email', 'attendee_email', 'invitee_email'],
        'prenom' => ['first_name', 'firstname', 'prenom'],
        'nom' => ['last_name', 'lastname', 'nom'],
        'nom_complet' => ['name', 'full_name', 'guest_name', 'attendee_name'],
        'telephone' => ['phone', 'phone_number', 'telephone'],
        'debut' => ['start_time', 'start', 'start_date_time', 'booking_start', 'start_date'],
        'statut' => ['status', 'booking_status'],
        'titre' => ['event_name', 'event_title', 'title', 'booking_title'],
    ];

    /**
     * @param  array<string, mixed>  $donnees  Corps reçu (JSON ou formulaire).
     */
    public function traiter(array $donnees, ?int $seanceForcee = null): ReservationRecue
    {
        $email = mb_strtolower(trim((string) $this->trouver($donnees, 'email')));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->journaliser(ReservationRecue::STATUT_IGNORE, 'Aucune adresse e-mail trouvée dans la réservation.', $donnees);
        }

        $contact = $this->contact($email, $donnees);
        $debut = $this->debut($donnees);
        $titre = trim((string) ($this->valeurDans($donnees['calendar_event'] ?? null, ['title'])
            ?? $this->trouver($donnees, 'titre'))) ?: 'Réservation';
        $annulation = (bool) preg_match('/cancel|reject|annul/i', (string) $this->trouver($donnees, 'statut'));

        $seance = $this->seance($seanceForcee, $debut, $titre);

        if ($seance) {
            return $annulation
                ? $this->annulerInscription($seance, $contact, $donnees)
                : $this->inscrire($seance, $contact, $donnees);
        }

        if ($annulation) {
            Activite::create([
                'titre' => 'Réservation annulée : '.$titre,
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $contact->id,
                'terminee_at' => now(),
                'notes' => $debut ? 'Créneau prévu le '.$debut->format('d/m/Y à H:i').'.' : null,
            ]);

            return $this->journaliser(ReservationRecue::STATUT_ANNULE, $contact->nomComplet().' a annulé « '.$titre.' ».', $donnees, $contact);
        }

        return $this->rendezVous($contact, $titre, $debut, $donnees);
    }

    // --- Contact -------------------------------------------------------------

    /** Retrouve le contact par son e-mail (principal ou secondaire), sinon le crée. */
    private function contact(string $email, array $donnees): Contact
    {
        $prenom = trim((string) $this->trouver($donnees, 'prenom'));
        $nom = trim((string) $this->trouver($donnees, 'nom'));

        if ($prenom === '' && $nom === '') {
            $morceaux = preg_split('/\s+/', trim((string) $this->trouver($donnees, 'nom_complet')), 2) ?: [];
            $prenom = $morceaux[0] ?? '';
            $nom = $morceaux[1] ?? '';
        }

        $telephone = trim((string) $this->trouver($donnees, 'telephone'));

        $contact = Contact::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->orWhereHas('adressesEmail', fn ($a) => $a->whereRaw('LOWER(adresse) = ?', [$email]))
            ->orderBy('id')
            ->first();

        if ($contact) {
            if (blank($contact->telephone) && $telephone !== '') {
                $contact->update(['telephone' => $telephone]);
            }

            return $contact;
        }

        return Contact::create([
            'type' => Contact::TYPE_PERSONNE,
            // Le nom est obligatoire : à défaut, le prénom ou le début de l'adresse.
            'nom' => $nom !== '' ? $nom : ($prenom !== '' ? $prenom : strstr($email, '@', true)),
            'prenom' => $nom !== '' && $prenom !== '' ? $prenom : null,
            'email' => $email,
            'telephone' => $telephone !== '' ? $telephone : null,
            'origine' => 'site_formulaire',
        ]);
    }

    // --- Session -------------------------------------------------------------

    /** Heure de début, convertie de l'UTC vers l'heure locale saisie dans le CRM. */
    private function debut(array $donnees): ?Carbon
    {
        $brut = trim((string) $this->trouver($donnees, 'debut'));
        if ($brut === '') {
            return null;
        }

        try {
            return Carbon::parse($brut, 'UTC')->setTimezone(self::FUSEAU_LOCAL);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Session concernée : celle imposée par l'adresse, sinon celle qui a lieu le
     * même jour que le créneau réservé (départage par l'heure puis par le titre).
     */
    private function seance(?int $forcee, ?Carbon $debut, string $titre): ?Seance
    {
        if ($forcee) {
            return Seance::find($forcee);
        }

        if (! $debut) {
            return null;
        }

        $duJour = Seance::whereDate('date_debut', $debut->toDateString())->orderBy('id')->get();

        if ($duJour->count() <= 1) {
            return $duJour->first();
        }

        return $duJour->first(fn (Seance $s) => $s->date_debut->format('H:i') === $debut->format('H:i'))
            ?? $duJour->first(fn (Seance $s) => mb_stripos($titre, $s->titre) !== false || mb_stripos($s->titre, $titre) !== false);
    }

    private function inscrire(Seance $seance, Contact $contact, array $donnees): ReservationRecue
    {
        $dejaInscrit = $seance->inscriptions()
            ->where('contact_id', $contact->id)
            ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
            ->exists();

        $inscription = $seance->inscrire($contact);

        if (! $dejaInscrit) {
            Activite::create([
                'titre' => 'Inscription via le site : '.$seance->titre,
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $contact->id,
                'terminee_at' => now(),
            ]);
        }

        $resume = $contact->nomComplet().($dejaInscrit ? ' était déjà inscrit(e) à « ' : ' inscrit(e) à « ').$seance->titre.' »'
            .($inscription->estEnAttente() ? ' (liste d’attente).' : '.');

        return $this->journaliser(ReservationRecue::STATUT_INSCRIT, $resume, $donnees, $contact, $seance);
    }

    private function annulerInscription(Seance $seance, Contact $contact, array $donnees): ReservationRecue
    {
        $inscription = $seance->inscriptions()
            ->where('contact_id', $contact->id)
            ->whereIn('statut', [Inscription::STATUT_CONFIRME, Inscription::STATUT_ATTENTE])
            ->first();

        if ($inscription) {
            $etaitConfirme = $inscription->estConfirme();
            $inscription->update(['statut' => Inscription::STATUT_ANNULE]);

            if ($etaitConfirme) {
                $seance->promouvoirSuivant();
            }

            Activite::create([
                'titre' => 'Inscription annulée depuis le site : '.$seance->titre,
                'type' => Activite::TYPE_NOTE,
                'contact_id' => $contact->id,
                'terminee_at' => now(),
            ]);
        }

        return $this->journaliser(
            ReservationRecue::STATUT_ANNULE,
            $contact->nomComplet().' a annulé son inscription à « '.$seance->titre.' ».',
            $donnees, $contact, $seance,
        );
    }

    // --- Rendez-vous ---------------------------------------------------------

    private function rendezVous(Contact $contact, string $titre, ?Carbon $debut, array $donnees): ReservationRecue
    {
        $titreActivite = 'Rendez-vous réservé : '.$titre;
        $echeance = $debut?->format('Y-m-d H:i:s');

        $existe = Activite::where('contact_id', $contact->id)
            ->where('titre', $titreActivite)
            ->when($echeance, fn ($q) => $q->where('echeance', $echeance), fn ($q) => $q->whereNull('echeance'))
            ->exists();

        if (! $existe) {
            Activite::create([
                'titre' => $titreActivite,
                'type' => Activite::TYPE_RDV,
                'contact_id' => $contact->id,
                'echeance' => $echeance,
                'notes' => 'Réservé depuis le site.',
            ]);
        }

        return $this->journaliser(
            ReservationRecue::STATUT_RDV,
            $contact->nomComplet().' a réservé « '.$titre.' »'.($debut ? ' le '.$debut->format('d/m/Y à H:i') : '').'.',
            $donnees, $contact,
        );
    }

    // --- Lecture tolérante ---------------------------------------------------

    /**
     * Cherche un champ : d'abord dans « booking », puis à la racine, puis partout.
     */
    private function trouver(array $donnees, string $champ): mixed
    {
        $cles = self::CLES[$champ];

        return $this->valeurDans($donnees['booking'] ?? null, $cles)
            ?? $this->valeurDans($donnees, $cles)
            ?? $this->valeurEnProfondeur($donnees, $cles);
    }

    /** @param  array<int, string>  $cles */
    private function valeurDans(mixed $tableau, array $cles): mixed
    {
        if (! is_array($tableau)) {
            return null;
        }

        foreach ($cles as $cle) {
            if (isset($tableau[$cle]) && is_scalar($tableau[$cle]) && trim((string) $tableau[$cle]) !== '') {
                return $tableau[$cle];
            }
        }

        return null;
    }

    /** @param  array<int, string>  $cles */
    private function valeurEnProfondeur(array $tableau, array $cles, int $niveau = 0): mixed
    {
        if ($niveau > 4) {
            return null;
        }

        foreach ($tableau as $valeur) {
            if (is_array($valeur)) {
                $trouve = $this->valeurDans($valeur, $cles) ?? $this->valeurEnProfondeur($valeur, $cles, $niveau + 1);
                if ($trouve !== null) {
                    return $trouve;
                }
            }
        }

        return null;
    }

    // --- Journal -------------------------------------------------------------

    private function journaliser(string $statut, string $resume, array $donnees, ?Contact $contact = null, ?Seance $seance = null): ReservationRecue
    {
        $ligne = ReservationRecue::create([
            'statut' => $statut,
            'resume' => mb_substr($resume, 0, 250),
            'contact_id' => $contact?->id,
            'seance_id' => $seance?->id,
            'contenu' => mb_substr((string) json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), 0, 20000),
        ]);

        // On ne garde que les dernières lignes du journal.
        $seuil = ReservationRecue::orderByDesc('id')->skip(ReservationRecue::CONSERVER)->take(1)->value('id');
        if ($seuil) {
            ReservationRecue::where('id', '<=', $seuil)->delete();
        }

        return $ligne;
    }
}

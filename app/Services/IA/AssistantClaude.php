<?php

namespace App\Services\IA;

use App\Models\Contact;
use App\Models\Parametre;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Assistant IA propulsé par l'API Claude (Anthropic), appelée en HTTP (sans
 * dépendance Composer). La clé et le modèle viennent des réglages (chiffrés).
 * L'assistant propose du texte ; il n'envoie jamais d'e-mail.
 */
class AssistantClaude implements AssistantIA
{
    private const URL = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    /** Modèle économique par défaut (modifiable dans les réglages). */
    private const MODELE_DEFAUT = 'claude-haiku-5-5';

    public function analyser(Contact $contact): ResultatAnalyse
    {
        $p = Parametre::instance();

        $systeme = <<<'TXT'
            Tu es l'assistant de suivi commercial de Laurence (coach bien-être « Prana Vidya »
            et créatrice de sites web). Tu analyses un fil d'e-mails avec un contact et tu aides
            Laurence à savoir quoi faire. N'invente AUCUN fait : appuie-toi sur le fil d'e-mails
            ET sur l'« Offre de référence » ci-dessous quand elle est fournie.
            Réponds UNIQUEMENT par un objet JSON avec les clés :
            - "resume" : 3 lignes max, où en est la relation.
            - "verdict" : faut-il relancer et pourquoi (1-2 phrases).
            - "delai" : quand relancer (ex. "sous 2 jours"), ou null.
            - "brouillon" : un message en français, chaleureux et professionnel, prêt à envoyer.
              Si le contact demande des infos (dates, tarifs, programme…), reprends-les depuis
              l'« Offre de référence » et insère ses liens utiles. Si une info manque dans l'offre,
              écris « [à compléter] » (n'invente jamais un prix ou une date).
            - "intention" : l'intention principale du dernier message du contact, UNE valeur parmi
              "nouveau_prospect", "question", "demande_devis", "prise_rdv", "reclamation",
              "remerciement", "paiement", "relance", "autre".
            - "temperature_conseillee" : chaleur du lead, UNE valeur parmi "chaud", "eleve",
              "moyen", "a_qualifier", ou null si on ne peut pas juger.
            - "etape_conseillee" : étape de pipeline suggérée d'après le contenu, UNE valeur parmi
              "nouveau", "qualifie", "proposition", "gagne", "perdu", ou null.
            - "infos_cles" : objet {"budget": texte|null, "delai": texte|null,
              "dates_rdv": [liste de dates/créneaux mentionnés], "decisions": texte|null}.
            - "promesses" : liste d'objets {"texte": ce que Laurence ou le contact s'est engagé à
              faire, "echeance": "AAAA-MM-JJ" si une date est déductible, sinon null}. [] si aucune.
            - "rdv" : objet {"demande": true/false s'il y a une demande/prise de RDV,
              "date": "AAAA-MM-JJ" si une date est fixée/proposée sinon null, "details": texte|null}.
            Laisse une valeur vide/nulle plutôt que d'inventer.
            TXT;

        $utilisateur = $this->contexteContact($contact, $p)."\n\n"
            .$this->contexteOffre($contact)
            .$this->transcription($contact);

        $texte = $this->envoyer($p, $systeme, $utilisateur, 1800);
        $json = $this->decoderJson($texte);

        return new ResultatAnalyse(
            resume: (string) ($json['resume'] ?? $texte),
            verdict: (string) ($json['verdict'] ?? ''),
            delaiConseille: isset($json['delai']) ? (string) $json['delai'] : null,
            brouillon: (string) ($json['brouillon'] ?? ''),
            modele: $this->modele($p),
            intention: $this->normaliserIntention($json['intention'] ?? null),
            temperatureConseillee: $this->valeurParmi($json['temperature_conseillee'] ?? null, ['chaud', 'eleve', 'moyen', 'a_qualifier']),
            etapeConseillee: $this->valeurParmi($json['etape_conseillee'] ?? null, ['nouveau', 'qualifie', 'proposition', 'gagne', 'perdu']),
            infosCles: is_array($json['infos_cles'] ?? null) ? $json['infos_cles'] : [],
            promesses: $this->normaliserPromesses($json['promesses'] ?? null),
            rdv: is_array($json['rdv'] ?? null) ? $json['rdv'] : [],
        );
    }

    /** Garde la valeur si elle fait partie de la liste autorisée, sinon null. */
    private function valeurParmi(mixed $valeur, array $autorisees): ?string
    {
        $valeur = is_string($valeur) ? strtolower(trim($valeur)) : null;

        return ($valeur !== null && in_array($valeur, $autorisees, true)) ? $valeur : null;
    }

    private function normaliserIntention(mixed $valeur): ?string
    {
        $valeur = is_string($valeur) ? strtolower(trim($valeur)) : null;

        return $valeur !== null && array_key_exists($valeur, \App\Models\Analyse::INTENTIONS)
            ? $valeur
            : ($valeur !== null && $valeur !== '' ? 'autre' : null);
    }

    /**
     * Normalise les promesses en liste de {texte, echeance}.
     *
     * @return list<array{texte: string, echeance: ?string}>
     */
    private function normaliserPromesses(mixed $valeur): array
    {
        if (! is_array($valeur)) {
            return [];
        }

        $out = [];
        foreach ($valeur as $item) {
            if (is_array($item) && filled($item['texte'] ?? null)) {
                $echeance = $item['echeance'] ?? null;
                $out[] = [
                    'texte' => (string) $item['texte'],
                    'echeance' => is_string($echeance) && trim($echeance) !== '' ? trim($echeance) : null,
                ];
            }
        }

        return $out;
    }

    public function redigerProspection(Contact $contact, ?string $angle = null): ResultatProspection
    {
        $p = Parametre::instance();

        $contact->loadMissing('categorie.pole');
        $cat = $contact->categorie;
        $aOffre = $cat && (filled($cat->offre) || ! empty($cat->liens));

        if (! $aOffre && blank($p->pitch_prospection)) {
            throw new RuntimeException('Renseigne l’« Offre & liens » de la catégorie de ce contact dans les Réglages (ou, à défaut, une note générale).');
        }

        $systeme = <<<'TXT'
            Tu es l'assistant de Laurence (« Prana Vidya »). Pour ce mail, tu combines l'expertise
            d'un rédacteur en copywriting, d'un spécialiste du marketing bienveillant et d'une
            formatrice de profs de yoga expérimentée. Rédige un brouillon de mail de PROSPECTION en
            français vers un prof de yoga, pour l'inviter à rejoindre la formation.
            Appuie-toi sur « l'offre & les liens » de la catégorie et le contexte du contact fournis
            ci-dessous : n'invente aucun fait, prix ou date qui n'y figure pas. Tu ne peux PAS ouvrir
            les liens : fonde-toi sur la description fournie et présente les liens comme des appels à
            l'action (« en savoir plus », « s'inscrire »). Style : expert, crédible et chaleureux,
            JAMAIS insistant ni trop commercial ; donne sincèrement envie de s'inscrire avec une
            invitation claire et motivante. Personnalise avec les infos du contact. Rédige un mail
            COMPLET et termine toujours tes phrases (ne t'arrête jamais au milieu d'une phrase).
            Réponds UNIQUEMENT par un objet JSON avec les clés "objet" (court et engageant) et
            "corps" (le mail complet, signé « Laurence »).
            TXT;

        $utilisateur = $this->contexteProspection($contact, $p, $angle);

        $texte = $this->envoyer($p, $systeme, $utilisateur, 2000);
        $json = $this->decoderJson($texte);

        return new ResultatProspection(
            objet: (string) ($json['objet'] ?? 'Découvrir la formation Prana Vidya'),
            corps: (string) ($json['corps'] ?? $texte),
            modele: $this->modele($p),
        );
    }

    public function suggestions(array $contexte): array
    {
        $p = Parametre::instance();

        $systeme = <<<'TXT'
            Tu es l'assistant de Laurence. À partir du contexte (chiffres et contacts à suivre),
            propose jusqu'à 5 suggestions d'actions concrètes pour sa journée, en français,
            une phrase chacune. Réponds UNIQUEMENT par un tableau JSON de chaînes.
            TXT;

        $texte = $this->envoyer($p, $systeme, json_encode($contexte, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}', 600);
        $json = $this->decoderJson($texte);

        if (array_is_list($json)) {
            return array_values(array_map('strval', $json));
        }

        return isset($json['suggestions']) && is_array($json['suggestions'])
            ? array_values(array_map('strval', $json['suggestions']))
            : [];
    }

    private function envoyer(Parametre $p, string $systeme, string $utilisateur, int $maxTokens): string
    {
        $cle = $p->secret('anthropic_api_key');

        if (blank($cle)) {
            throw new RuntimeException('Clé API Claude manquante ou illisible : (re)saisis-la dans les Réglages.');
        }

        $reponse = Http::withHeaders([
            'x-api-key' => $cle,
            'anthropic-version' => self::VERSION,
            'content-type' => 'application/json',
        ])->timeout(60)->post(self::URL, [
            'model' => $this->modele($p),
            'max_tokens' => $maxTokens,
            'system' => $systeme,
            'messages' => [
                ['role' => 'user', 'content' => $utilisateur],
            ],
        ]);

        $json = $reponse->json();

        if ($reponse->failed()) {
            $detail = data_get($json, 'error.message') ?? data_get($json, 'error.type') ?? $reponse->status();
            throw new RuntimeException('L’assistant IA a refusé la requête : '.$detail.' (modèle '.$this->modele($p).').');
        }

        // La réponse peut contenir plusieurs blocs (ex. « thinking » puis « text ») :
        // on récupère tout le texte des blocs de type « text ».
        $texte = collect(data_get($json, 'content', []))
            ->filter(fn ($bloc) => is_array($bloc) && ($bloc['type'] ?? '') === 'text')
            ->map(fn ($bloc) => (string) ($bloc['text'] ?? ''))
            ->implode('');

        if (trim($texte) === '') {
            $cause = data_get($json, 'error.message')
                ?? data_get($json, 'stop_reason')
                ?? 'réponse vide';
            throw new RuntimeException('Réponse inattendue de l’assistant ('.$cause.', modèle '.$this->modele($p).'). '
                .'Vérifie que ce modèle est disponible pour ta clé API, ou laisse le champ Modèle vide.');
        }

        return $texte;
    }

    private function modele(Parametre $p): string
    {
        return filled($p->anthropic_modele) ? $p->anthropic_modele : self::MODELE_DEFAUT;
    }

    /** @return array<mixed> */
    private function decoderJson(string $texte): array
    {
        // Isole le premier bloc JSON s'il est entouré de texte.
        if (preg_match('/[\{\[].*[\}\]]/s', $texte, $m)) {
            $texte = $m[0];
        }

        $donnees = json_decode($texte, true);

        return is_array($donnees) ? $donnees : [];
    }

    private function contexteContact(Contact $contact, Parametre $p): string
    {
        $lignes = [];
        $lignes[] = 'Contact : '.$contact->nomComplet().' ('.($contact->estEntreprise() ? 'entreprise' : 'personne').')';
        if (filled($contact->email)) {
            $lignes[] = 'E-mail : '.$contact->email;
        }
        $etiquettes = $contact->relationLoaded('etiquettes') ? $contact->etiquettes : $contact->etiquettes()->get();
        if ($etiquettes->isNotEmpty()) {
            $lignes[] = 'Étiquettes : '.$etiquettes->pluck('nom')->implode(', ');
        }
        $lignes[] = 'Liens de réservation disponibles : '
            .'appel visio = '.($p->lien_appel_visio ?: '—').' ; '
            .'séance découverte = '.($p->lien_seance_decouverte ?: '—').' ; '
            .'journée aura = '.($p->lien_journee_aura ?: '—');

        return implode("\n", $lignes);
    }

    /**
     * « Offre de référence » : l'offre et les liens de la catégorie du contact,
     * pour que le brouillon de réponse puisse répondre avec de vraies infos
     * (dates, tarifs, programme, liens). Chaîne vide si rien n'est renseigné.
     */
    private function contexteOffre(Contact $contact): string
    {
        $contact->loadMissing('categorie');
        $cat = $contact->categorie;

        if (! $cat || (blank($cat->offre) && empty($cat->liens))) {
            return '';
        }

        $lignes = ['### Offre de référence — catégorie « '.$cat->libelleComplet().' » (utilise ces faits, n\'invente rien)'];

        if (filled($cat->offre)) {
            $lignes[] = (string) $cat->offre;
        }

        $liens = collect($cat->liens ?? [])->filter(fn ($l) => filled($l['url'] ?? null));
        if ($liens->isNotEmpty()) {
            $lignes[] = 'Liens utiles (à insérer si pertinent) :';
            foreach ($liens as $lien) {
                $libelle = trim((string) ($lien['libelle'] ?? ''));
                $url = trim((string) $lien['url']);
                $lignes[] = '- '.($libelle !== '' ? $libelle.' : ' : '').$url;
            }
        }

        return implode("\n", $lignes)."\n\n";
    }

    private function contexteProspection(Contact $contact, Parametre $p, ?string $angle): string
    {
        $categorie = $contact->relationLoaded('categorie') ? $contact->categorie : $contact->categorie()->first();

        $lignes = [];

        // Source principale : l'offre & les liens de la catégorie du contact.
        if ($categorie && (filled($categorie->offre) || ! empty($categorie->liens))) {
            $lignes[] = '### Offre « '.$categorie->libelleComplet().' » (source principale)';
            if (filled($categorie->offre)) {
                $lignes[] = (string) $categorie->offre;
            }
            $liens = collect($categorie->liens ?? [])
                ->filter(fn ($l) => filled($l['url'] ?? null));
            if ($liens->isNotEmpty()) {
                $lignes[] = 'Liens à insérer dans le mail (comme appels à l\'action) :';
                foreach ($liens as $lien) {
                    $libelle = trim((string) ($lien['libelle'] ?? ''));
                    $url = trim((string) $lien['url']);
                    $lignes[] = '- '.($libelle !== '' ? $libelle.' : ' : '').$url;
                }
            }
        } elseif (filled($p->lien_seance_decouverte)) {
            $lignes[] = '### Lien disponible';
            $lignes[] = 'Séance découverte : '.$p->lien_seance_decouverte;
        }

        // Note générale facultative (ancien « pitch »).
        if (filled($p->pitch_prospection)) {
            $lignes[] = '';
            $lignes[] = '### Note générale de Laurence (contexte complémentaire)';
            $lignes[] = (string) $p->pitch_prospection;
        }

        $lignes[] = '';
        $lignes[] = '### Contact à prospecter';
        $lignes[] = 'Nom : '.$contact->nomComplet();
        if (filled($contact->notes)) {
            $lignes[] = 'Notes : '.$contact->notes;
        }
        if ($categorie) {
            $lignes[] = 'Catégorie : '.$categorie->libelleComplet();
        }
        $etiquettes = $contact->relationLoaded('etiquettes') ? $contact->etiquettes : $contact->etiquettes()->get();
        if ($etiquettes->isNotEmpty()) {
            $lignes[] = 'Étiquettes : '.$etiquettes->pluck('nom')->implode(', ');
        }
        if (filled($angle)) {
            $lignes[] = 'Angle souhaité par Laurence : '.$angle;
        }

        return implode("\n", $lignes);
    }

    private function transcription(Contact $contact): string
    {
        $emails = $contact->relationLoaded('emails')
            ? $contact->emails->sortBy('date_email')
            : $contact->emails()->orderBy('date_email')->get();

        if ($emails->isEmpty()) {
            return "Aucun e-mail échangé pour l'instant.";
        }

        $lignes = ["Fil d'e-mails (du plus ancien au plus récent) :"];
        foreach ($emails as $mail) {
            $sens = $mail->estEntrant() ? 'REÇU' : 'ENVOYÉ';
            $date = $mail->date_email?->format('d/m/Y') ?? '';
            $lignes[] = "- [$sens $date] ".($mail->objet ?: '(sans objet)').' : '.($mail->extrait ?? '');
        }

        return implode("\n", $lignes);
    }
}

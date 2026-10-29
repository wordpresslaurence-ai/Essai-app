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
            - "brouillon" : un message en français, chaleureux et professionnel, prêt à envoyer,
              et COURT : vise 150 mots, 220 au maximum. Réponds précisément à la demande SANS
              recopier tout le détail de l'offre : si on te demande le programme, donne un aperçu
              de 3-4 lignes (les grandes étapes, pas le détail des techniques) et renvoie vers le
              lien « programme » s'il existe. Reprends les faits (dates, tarifs) depuis l'« Offre
              de référence » ; si une info manque, écris « [à compléter] » (n'invente jamais un
              prix ou une date). Termine TOUJOURS tes phrases (ne t'arrête jamais au milieu d'une
              phrase) et garde le message assez court pour tenir en entier.
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

        // Grande marge : l'offre de référence peut allonger le brouillon. En cas de
        // coupure, decoderJson tente de réparer le JSON (voir plus bas).
        $texte = $this->envoyer($p, $systeme, $utilisateur, 4096);
        $json = $this->decoderJson($texte);

        // Repli propre : on n'affiche JAMAIS le texte brut (plein de \n et
        // d'accolades) ; un message clair invite à relancer si besoin.
        $resume = filled($json['resume'] ?? null)
            ? (string) $json['resume']
            : "L'analyse n'a pas pu être lue entièrement (réponse trop longue). Clique à nouveau sur « Analyser ».";

        return new ResultatAnalyse(
            resume: $resume,
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
            Tu es l'assistant de Laurence (coach bien-être « Prana Vidya »). Pour ce mail, tu combines
            l'expertise d'un rédacteur en copywriting et d'un spécialiste du marketing bienveillant.
            Rédige un brouillon de mail de PROSPECTION en français adressé au contact ci-dessous, pour
            lui présenter L'OFFRE DÉCRITE DANS LE CONTEXTE (section « offre & liens », source
            principale) et l'inviter à la découvrir. L'objet comme le corps doivent porter sur CETTE
            offre précise (celle de la catégorie du contact), jamais sur une autre offre, et ne la
            présente pas comme une formation si ce n'en est pas une.
            Appuie-toi uniquement sur « l'offre & les liens » et le contexte du contact fournis :
            n'invente aucun fait, prix ou date qui n'y figure pas. Tu ne peux PAS ouvrir les liens :
            fonde-toi sur la description fournie et présente les liens comme des appels à l'action
            (« en savoir plus », « s'inscrire »). Style : expert, crédible et chaleureux, JAMAIS
            insistant ni trop commercial ; donne sincèrement envie avec une invitation claire et
            motivante. Personnalise avec les infos du contact. Rédige un mail COMPLET et termine
            toujours tes phrases (ne t'arrête jamais au milieu d'une phrase). Réponds UNIQUEMENT par
            un objet JSON avec les clés "objet" (court, engageant et centré sur l'offre) et "corps"
            (le mail complet, signé « Laurence »).
            TXT;

        $utilisateur = $this->contexteProspection($contact, $p, $angle);

        $texte = $this->envoyer($p, $systeme, $utilisateur, 3000);
        $json = $this->decoderJson($texte);

        $objetDefaut = $cat ? 'Découvrir « '.$cat->libelleComplet().' »' : 'Une proposition pour vous';

        return new ResultatProspection(
            objet: (string) ($json['objet'] ?? $objetDefaut),
            corps: filled($json['corps'] ?? null)
                ? (string) $json['corps']
                : 'Le brouillon n’a pas pu être généré entièrement. Clique à nouveau sur « Rédiger ».',
            modele: $this->modele($p),
        );
    }

    public function redigerReponse(Contact $contact): ResultatProspection
    {
        $p = Parametre::instance();

        $contact->loadMissing('emails');
        $dernierRecu = $contact->emails
            ->filter(fn ($m) => $m->estEntrant())
            ->sortByDesc('date_email')
            ->first();

        if (! $dernierRecu) {
            throw new RuntimeException('Aucun e-mail reçu de ce contact : il n’y a rien à quoi répondre.');
        }

        $systeme = <<<'TXT'
            Tu es l'assistant de Laurence (coach bien-être « Prana Vidya »). Rédige, en son nom, un
            brouillon de RÉPONSE au DERNIER e-mail REÇU du contact, en français. Réponds réellement
            aux questions et points soulevés dans le fil ci-dessous. Style : chaleureux, professionnel,
            clair et concret, jamais insistant. N'invente AUCUN fait, prix, date ou engagement qui ne
            figure pas dans le fil : si une information manque, propose d'en convenir ensemble plutôt
            que de l'inventer. Rédige un mail COMPLET et termine toujours tes phrases, signé « Laurence ».
            Réponds UNIQUEMENT par un objet JSON avec les clés "objet" (commence par « Re: » si pertinent)
            et "corps" (le mail complet).
            TXT;

        $lignes = ['### Contact', 'Nom : '.$contact->nomComplet()];
        if (filled($contact->notes)) {
            $lignes[] = 'Notes internes : '.$contact->notes;
        }
        $lignes[] = '';
        $lignes[] = $this->transcription($contact);
        $utilisateur = implode("\n", $lignes);

        $texte = $this->envoyer($p, $systeme, $utilisateur, 2500);
        $json = $this->decoderJson($texte);

        $objetDefaut = 'Re: '.($dernierRecu->objet ?: 'votre message');

        return new ResultatProspection(
            objet: (string) ($json['objet'] ?? $objetDefaut),
            corps: filled($json['corps'] ?? null)
                ? (string) $json['corps']
                : 'Le brouillon n’a pas pu être généré entièrement. Clique à nouveau sur « Proposer une réponse ».',
            modele: $this->modele($p),
        );
    }

    public function devinerProspect(string $adresse, ?string $corps): array
    {
        $p = Parametre::instance();

        $systeme = <<<'TXT'
            Tu analyses un e-mail reçu d'une personne inconnue, pour aider à pré-remplir une fiche
            contact. À partir de l'ADRESSE e-mail et du MESSAGE (surtout la SIGNATURE en fin de mail),
            déduis UNIQUEMENT ce qui est explicitement présent : prénom, nom, entreprise/structure,
            fonction, téléphone. N'invente RIEN : laisse null tout ce qui n'est pas clairement indiqué.
            Ne devine pas un téléphone, et ne force pas un nom à partir de la seule adresse si ce n'est
            pas évident. Réponds UNIQUEMENT par un objet JSON avec les clés "prenom", "nom",
            "entreprise", "fonction", "telephone" (chaîne, ou null si inconnu).
            TXT;

        $utilisateur = 'Adresse e-mail : '.$adresse."\n\nMessage reçu :\n".($corps ?: '(aucun corps disponible)');

        $texte = $this->envoyer($p, $systeme, $utilisateur, 500);
        $json = $this->decoderJson($texte);

        $val = fn (string $cle): ?string => filled($json[$cle] ?? null) ? trim((string) $json[$cle]) : null;

        return [
            'prenom' => $val('prenom'),
            'nom' => $val('nom'),
            'entreprise' => $val('entreprise'),
            'fonction' => $val('fonction'),
            'telephone' => $val('telephone'),
        ];
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
        $texte = trim($texte);

        // Retire une éventuelle clôture markdown ```json … ```
        if (str_starts_with($texte, '```')) {
            $texte = (string) preg_replace('/^```[a-zA-Z]*\s*/', '', $texte);
            $texte = (string) preg_replace('/\s*```$/', '', $texte);
            $texte = trim($texte);
        }

        // Isole le bloc JSON s'il est entouré de texte, puis tente de le lire.
        $candidat = preg_match('/[\{\[].*[\}\]]/s', $texte, $m) === 1 ? $m[0] : $texte;
        $donnees = json_decode($candidat, true);
        if (is_array($donnees)) {
            return $donnees;
        }

        // Réponse probablement coupée : on referme le JSON pour récupérer au
        // moins les premiers champs (résumé, verdict, début du brouillon).
        $donnees = json_decode($this->reparerJsonTronque($texte), true);

        return is_array($donnees) ? $donnees : [];
    }

    /**
     * Répare un objet JSON coupé en pleine réponse : ferme la chaîne en cours
     * puis les tableaux/objets restés ouverts. Permet d'afficher un brouillon
     * (même légèrement tronqué) plutôt qu'un amas de texte brut.
     */
    private function reparerJsonTronque(string $texte): string
    {
        $debut = strpos($texte, '{');
        if ($debut === false) {
            return $texte;
        }

        $s = substr($texte, $debut);
        $pile = [];          // fermetures attendues : '}' ou ']'
        $dansChaine = false;
        $echap = false;

        for ($i = 0, $n = strlen($s); $i < $n; $i++) {
            $c = $s[$i];

            if ($dansChaine) {
                if ($echap) {
                    $echap = false;
                } elseif ($c === '\\') {
                    $echap = true;
                } elseif ($c === '"') {
                    $dansChaine = false;
                }

                continue;
            }

            match ($c) {
                '"' => $dansChaine = true,
                '{' => $pile[] = '}',
                '[' => $pile[] = ']',
                '}', ']' => array_pop($pile),
                default => null,
            };
        }

        $repare = rtrim($s);

        if ($dansChaine) {
            $repare .= '"'; // referme la chaîne coupée
        } else {
            $repare = (string) preg_replace('/,\s*$/', '', $repare); // virgule traînante
        }

        while (($fermeture = array_pop($pile)) !== null) {
            $repare .= $fermeture;
        }

        return $repare;
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
        if (filled($contact->discipline)) {
            $lignes[] = 'Discipline enseignée : '.$contact->discipline;
        }
        if (filled($contact->libelleExperienceYoga())) {
            $lignes[] = 'Expérience du yoga : '.$contact->libelleExperienceYoga();
        }
        if (filled($contact->fait_observe)) {
            $lignes[] = 'Fait observé (à exploiter pour personnaliser) : '.$contact->fait_observe;
        }
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

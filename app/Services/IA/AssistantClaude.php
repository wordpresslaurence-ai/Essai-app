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
            Laurence à savoir quoi faire. Réponds UNIQUEMENT par un objet JSON avec les clés :
            "resume" (3 lignes max), "verdict" (faut-il relancer et pourquoi, 1-2 phrases),
            "delai" (quand relancer, ex. "sous 2 jours"), "brouillon" (un message en français,
            chaleureux et professionnel, prêt à envoyer). Si un lien de réservation est utile,
            insère-le dans le brouillon. N'invente aucun fait.
            TXT;

        $utilisateur = $this->contexteContact($contact, $p)."\n\n"
            .$this->transcription($contact);

        $texte = $this->envoyer($p, $systeme, $utilisateur, 1200);
        $json = $this->decoderJson($texte);

        return new ResultatAnalyse(
            resume: (string) ($json['resume'] ?? $texte),
            verdict: (string) ($json['verdict'] ?? ''),
            delaiConseille: isset($json['delai']) ? (string) $json['delai'] : null,
            brouillon: (string) ($json['brouillon'] ?? ''),
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
        if (blank($p->anthropic_api_key)) {
            throw new RuntimeException('Clé API Claude manquante : renseigne-la dans les Réglages.');
        }

        $reponse = Http::withHeaders([
            'x-api-key' => $p->anthropic_api_key,
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

        if ($reponse->failed()) {
            throw new RuntimeException('L’assistant IA est indisponible ('.$reponse->status().'). Vérifie ta clé API.');
        }

        return (string) data_get($reponse->json(), 'content.0.text', '');
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

# Plan technique — Brique 1 : moteur de l'assistant

> Étape **plan** (Spec Kit), suite de `spec.md`. Décrit *comment* on construit la brique 1,
> en restant cohérent avec le CRM (Laravel 13 + Livewire + Pest + Pint). Fournisseur IA : **Claude**.

---

## 1. Vue d'ensemble

```
Gmail (IMAP, lecture seule)                 API Claude (Anthropic)
        │                                             │
        ▼                                             ▼
  SynchronisationEmails  ──► table `emails` ──►  AssistantClaude
        │                      (par contact)         │  (analyse, suggestions)
        │                                             ▼
        └──► adresse inconnue ──► Activité « tâche »   table `analyses`
                                  (contact nul)              │
                                                             ▼
   Écrans Livewire : Réglages · Fiche (fil + Analyse) · Brief du matin
```

- **Déclenché à la demande** (boutons) ; aucune tâche planifiée pour cette brique.
- Secrets (mot de passe Gmail, clé API) **chiffrés en base** (jamais dans le code ni Git).

## 2. Dépendances (composer)

- **Lecture IMAP** : `webklex/php-imap` (lib Laravel mûre pour IMAP). Repli possible : extension
  `php-imap` native. *Choix confirmé à l'implémentation selon disponibilité sur Hostinger.*
- **Appel Claude** : **SDK PHP officiel d'Anthropic** si disponible, sinon le **client HTTP de
  Laravel** (`Http::withToken(...)->post(...)`) sur l'API Messages. Dans les deux cas,
  encapsulé derrière **une interface** `AssistantIA` (voir §5) pour pouvoir **simuler** en test.
- Rien d'autre : on reste sur la stack existante.

## 3. Schéma de données (migrations)

### `parametres` (une seule ligne, valeurs chiffrées)
| colonne | type | note |
|---|---|---|
| `gmail_email` | string | boîte synchronisée |
| `gmail_app_password` | text **chiffré** | cast `encrypted` |
| `anthropic_api_key` | text **chiffré** | cast `encrypted` |
| `anthropic_modele` | string | défaut : modèle **économique** (famille Haiku) |
| `relance_delai_jours` | int | défaut **5** |
| `lien_appel_visio` / `lien_seance_decouverte` / `lien_journee_aura` | string | liens Fluent Booking |
| `derniere_synchro_at` | datetime | borne incrémentale |

> Chiffrement via les **casts `encrypted`** de Laravel (clé = `APP_KEY`, déjà fixée en prod).

### `emails`
| colonne | type | note |
|---|---|---|
| `contact_id` | FK nullable → contacts | association RF-1 |
| `direction` | enum `entrant`/`sortant` | RF-2 |
| `de` / `a` | string / text | adresses |
| `objet` | string | |
| `extrait` | text | aperçu ; `corps` complet optionnel (longtext) |
| `date_email` | datetime | date du message |
| `message_id` | string **unique** | déduplication RF-8 |
| `fil_id` | string index | regroupement par conversation |
| `boite` | string | adresse source |
| `traite` | bool | pour « réponse due » (RF-3b) |

### `analyses` (historique, on affiche la plus récente)
`contact_id` (FK), `resume` (text), `verdict` (text), `delai_conseille` (string),
`brouillon` (text), `modele` (string), `faite_le` (datetime).

### Tâche « Créer le prospect » — **pas de nouvelle table**
On réutilise le modèle **`Activite`** (son `contact_id` est déjà nullable) : type `tache`,
`contact_id = null`, `notes = "Créer le prospect : <adresse> — <objet>"`. **Anti-doublon** :
on ne recrée pas si une tâche *non terminée* mentionnant déjà cette adresse existe.

## 4. Services (logique métier, en français)

- **`App\Services\Email\ClientImap`** — ouvre la connexion IMAP à partir des `parametres`,
  liste les messages depuis `derniere_synchro_at` (ou 90 j à la 1ʳᵉ fois).
- **`App\Services\Email\SynchronisationEmails`** :
  1. récupère les messages ; 2. pour chaque message, **associe** par adresse (RF-1) et marque
  **entrant/sortant** (RF-2) ; 3. **insère sans doublon** (`message_id`) ; 4. adresse inconnue →
  **crée la tâche** (RF-4) ; 5. met à jour `derniere_synchro_at` et un **compte-rendu**
  (« X mails importés, Y tâches créées »).
- **`App\Services\IA\AssistantIA`** (interface) + **`AssistantClaude`** (implémentation) :
  - `analyserContact(Contact): Analyse` → résumé + verdict + brouillon (insère le bon lien
    Fluent Booking selon le contexte, RF-6) ;
  - `suggestionsDuJour(array $donnees): array` → les suggestions proactives du brief (RF-7.3).
  - Un **`AssistantFactice`** (fake) pour les tests, renvoyant des réponses déterministes.
- **`App\Services\Relances\CalculRelances`** — calcule localement (gratuit) « réponses dues » et
  « à relancer » (RF-3) pour le brief.

## 5. Écrans (Livewire + Blade, design « Or & Encre » existant)

- **Réglages** (`App\Livewire\Reglages\Assistant`, route `reglages.assistant`, nav « Réglages ») :
  formulaire pour coller Gmail + clé API + liens + délai ; bouton **« Tester la connexion »**.
  Les champs secrets ne réaffichent jamais la valeur en clair.
- **Bouton « Synchroniser mes mails »** (sur le Brief et le tableau de bord) → lance
  `SynchronisationEmails`, affiche le compte-rendu.
- **Fiche contact** (`FicheDetail`) : nouvelle section **« Emails »** (le fil, chronologique,
  entrant/sortant) + bouton **« Analyse & relances »** → appelle `AssistantClaude`, affiche et
  enregistre le résultat (résumé / verdict / brouillon avec bouton **copier**).
- **Brief du matin** (`App\Livewire\Assistant\Brief`, route `assistant.brief`, nav « Assistant ») :
  3 blocs (Réponses dues, À relancer, Suggestions du jour) + le bouton Synchroniser.

## 6. Appel Claude — principes

- Clé lue depuis `parametres` (chiffrée) ; **jamais** en dur.
- **Modèle économique par défaut** (famille Haiku), changeable en réglages.
- Appel **uniquement** sur action (bouton Analyse, génération du brief) → coût maîtrisé.
- Entrée = le fil d'emails du contact (texte) + contexte (étape pipeline, étiquettes, liens).
- Sortie = texte structuré (résumé / verdict / brouillon) que l'on parse et enregistre.
- Erreurs réseau/API : message clair à l'utilisatrice, rien n'est cassé (la synchro et le CRM
  restent utilisables sans IA).

## 7. Sécurité / RGPD

- Secrets **chiffrés** (`encrypted` casts) ; `.env` et secrets **jamais** committés.
- Seuls les **contacts connus** sont traités ; les mails inconnus ne stockent qu'une tâche.
- Emails stockés **sur l'hébergement de Laurence** (MySQL Hostinger en prod).
- Transmission à l'API Claude **uniquement** lors d'une analyse demandée.

## 8. Tests (Pest) & qualité

- **Synchronisation** : avec un **faux client IMAP** (messages en mémoire) → vérifie association,
  entrant/sortant, déduplication, création de tâche pour inconnu.
- **Relances** : jeux de dates → « réponse due » vs « à relancer » (RF-3).
- **Analyse** : via `AssistantFactice` → le résultat est enregistré, le bon lien est inséré.
- **Aucun test n'appelle les vraies API** (IMAP/Claude simulés).
- **Pint** avant chaque commit.

## 9. Déploiement

- Migrations jouées au démarrage (déjà le cas via `docker/entrypoint.sh` / `migrate --force`).
- En prod Hostinger : **MySQL** (données persistantes) + `APP_KEY` fixe (indispensable pour
  déchiffrer les secrets). Sur le test Railway (SQLite), les réglages peuvent se réinitialiser.
- Vérifier qu'**IMAP sortant** est autorisé sur l'hébergement (OK en principe).

## 10. Risques / décisions à l'implémentation

- Dispo de `webklex/php-imap` vs extension native sur Hostinger → on tranchera au moment du build.
- Nom exact du package / du modèle Claude → confirmés à l'implémentation (encapsulés derrière
  l'interface `AssistantIA`, donc sans impact sur le reste).
- Volume de la 1ʳᵉ synchro → borne 90 j + insertion par lots.

---

*Prochaine étape : `tasks.md` — découpage en tâches livrables et testables, dans l'ordre.*

# Tâches — Brique 1 : moteur de l'assistant

> Étape **tasks** (Spec Kit), suite de `plan.md`. Chaque tâche est **livrable** et **testable**.
> On avance dans l'ordre ; après chaque tâche : **tests Pest verts + Pint** avant de committer.
> Légende : ⬜ à faire · 🟡 en cours · ✅ fait.

---

## T1 — Fondations (base de données & modèles) ✅
- Migrations : `parametres` (valeurs chiffrées), `emails`, `analyses` (schéma de `plan.md` §3).
- Modèles `Parametre`, `Email`, `Analyse` (+ casts `encrypted` sur les secrets, relations
  `Email→Contact`, `Analyse→Contact`).
- Helper d'accès aux réglages (une seule ligne de `parametres`).
- **Tests** : création/chiffrement des réglages ; relations ; un secret n'est jamais stocké en clair.

## T2 — Écran Réglages ✅
- Composant `Reglages\Assistant` + route `reglages.assistant` + entrée de menu « Réglages ».
- Formulaire : Gmail, mot de passe d'application, clé API Claude, modèle, délai de relance (5),
  3 liens Fluent Booking (pré-remplis avec les valeurs connues). Secrets jamais réaffichés en clair.
- **Tests** : enregistrement, valeurs par défaut, champs secrets masqués.

## T3 — Lecture IMAP (dépendance + service) ✅
- Ajouter la librairie IMAP (`webklex/php-imap` ou extension native).
- Interface `LecteurEmails` + implémentation `ClientImap` (connexion depuis les réglages,
  liste des messages depuis `derniere_synchro_at`, borne 90 j à la 1ʳᵉ fois).
- `LecteurFactice` (messages en mémoire) pour les tests.
- Bouton **« Tester la connexion »** dans les Réglages (message clair si échec).
- **Tests** : via `LecteurFactice` (aucun accès réseau réel).

## T4 — Synchronisation ✅
- Service `SynchronisationEmails` : association par adresse (RF-1), marque entrant/sortant (RF-2),
  insertion **sans doublon** par `message_id` (RF-8), **tâche « Créer le prospect »** pour une
  adresse inconnue (RF-4, anti-doublon), **compte-rendu** (« X importés, Y tâches »).
- Bouton **« Synchroniser mes mails »** (provisoirement sur le tableau de bord).
- **Tests** : association, entrant/sortant, déduplication, tâche créée pour inconnu, compte-rendu.

## T5 — Fil des emails sur la fiche ✅
- Section **« Emails »** dans `FicheDetail` : liste chronologique, badge entrant/sortant,
  objet, date, aperçu. Style « Or & Encre » existant.
- **Tests** : la fiche affiche les emails du contact, dans l'ordre.

## T6 — Interface IA + client Claude ✅
- Interface `AssistantIA` (`analyserContact`, `suggestionsDuJour`).
- Implémentation `AssistantClaude` (SDK officiel ou client HTTP Laravel ; clé chiffrée ; modèle
  économique par défaut ; erreurs gérées proprement).
- `AssistantFactice` (réponses déterministes) pour les tests.
- Liaison dans le conteneur de services (fake en environnement de test).
- **Tests** : `AssistantFactice` renvoie une structure exploitable ; aucune vraie clé requise.

## T7 — Analyse & relances (sur la fiche) ⬜
- Bouton **« Analyse & relances »** sur `FicheDetail` → appelle `AssistantIA`, affiche
  **résumé / verdict / brouillon** et **enregistre** une `Analyse` (horodatée).
- Insertion du **bon lien Fluent Booking** selon le contexte (RF-6). Bouton **copier** le brouillon.
- **Tests** (avec `AssistantFactice`) : l'analyse est enregistrée et affichée ; lien inséré.

## T8 — Calcul des relances ⬜
- Service `CalculRelances` : « réponses dues » (RF-3b) et « à relancer » (RF-3a, délai réglable).
- **Tests** : jeux de dates couvrant les deux cas et les bornes.

## T9 — Brief du matin ⬜
- Composant `Assistant\Brief` + route `assistant.brief` + entrée de menu « Assistant ».
- 3 blocs : **Réponses dues**, **À relancer** (via `CalculRelances`), **Suggestions du jour**
  (via `AssistantIA`, un seul appel). Bouton **« Synchroniser »** intégré.
- **Tests** : les blocs listent les bons contacts ; suggestions via `AssistantFactice`.

## T10 — Navigation, finitions & docs ⬜
- Entrées de menu « Assistant » et « Réglages » dans la barre latérale (icônes, thème clair/sombre).
- Petites finitions UX (états vides, messages de synchro, chargement).
- Mettre à jour `REPRISE.md` (nouveau module) et cocher ce `tasks.md`.
- **Vérif finale** : toute la suite Pest verte + Pint.

---

## Jalon de test « en vrai » (après T1→T9)
Quand le code est prêt, **Laurence fournit** : le **mot de passe d'application Gmail** et la
**clé API Anthropic** (je la guide). On colle dans Réglages → **Tester la connexion** →
**Synchroniser** → ouvrir une fiche → **Analyse & relances** → voir le **Brief**.

## Hors périmètre (briques suivantes, rappel)
Taxonomie Pôle→Activité · webhooks Fluent Booking · agenda Google · transcriptions tl;dv ·
dépôt du brouillon dans Gmail · autres boîtes · création auto de prospect.

---

*Prochaine étape : coder **T1** (fondations), tests verts, commit, puis T2, etc.*

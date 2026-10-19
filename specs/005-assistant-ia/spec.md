# Spec — Brique 1 : moteur de l'assistant (emails, analyse & relances)

> Étape **specify** (Spec Kit). Première brique de l'assistant IA décrit dans `cadrage.md`.
> Fournisseur IA retenu : **Claude (API Anthropic)**. Langue : **français**.
> Statut : **à valider** avant `plan.md`.

---

## 1. Objectif

Donner à Laurence un assistant, **dans le CRM**, qui :
1. **récupère les emails** échangés avec ses contacts (envois **et** réponses),
2. les **affiche sur la fiche** du contact (le fil de conversation),
3. sur demande, **analyse** un contact et dit **qui / quand / pourquoi relancer**, avec un
   **brouillon** prêt à copier,
4. propose chaque jour un **brief** : « à relancer » et « tu dois une réponse ».

## 2. Périmètre de cette brique

**Inclus**
- Connexion d'**une seule boîte** : `laurencehomeharmony@gmail.com` (Gmail, IMAP).
- Synchronisation **à la demande** (bouton), pas d'automatique.
- Ne traiter que les mails liés à un **contact connu** (association par adresse email).
- Bouton **« Analyse & relances »** par contact (appel à l'API Claude).
- **Brief du matin** (écran unique).
- Réglages pour coller : identifiants Gmail (mot de passe d'application) + clé API Claude.

**Exclus (briques suivantes)**
- Taxonomie Pôle → Activité configurable (brique 2).
- Webhooks Fluent Booking, lecture de l'agenda Google, transcriptions tl;dv.
- Autres boîtes (`wordpresslaurence@`, Outlook), prospection sortante automatisée.
- Envoi d'emails depuis le CRM (l'assistant **rédige**, Laurence **envoie** depuis Gmail).

## 3. Utilisateur

Laurence, **seule utilisatrice** (compte unique). Pas de multi-utilisateur.

## 4. Récits utilisateur (user stories)

- **US-1** : En tant que Laurence, je colle une fois mes identifiants Gmail et ma clé API Claude
  dans les **Réglages**, pour activer l'assistant.
- **US-2** : Je clique **« Synchroniser mes mails »** ; le CRM récupère les derniers emails et
  les **rattache aux bonnes fiches** ; un mail dont l'adresse est inconnue est **ignoré**
  (ou proposé comme nouveau lead — voir RF-4).
- **US-3** : Sur une fiche contact, je vois le **fil des emails** (entrants/sortants, date, objet).
- **US-4** : Je clique **« Analyse & relances »** ; l'assistant me donne un **résumé**, un
  **verdict de relance** (faut-il relancer ? pourquoi ? quand ?) et un **brouillon** de message
  avec, si pertinent, le **bon lien Fluent Booking**.
- **US-5** : Chaque matin, j'ouvre le **brief** et je vois d'un coup d'œil **qui relancer** et
  **à qui je dois une réponse**.

## 5. Règles fonctionnelles

- **RF-1 — Association** : un email est rattaché à un contact si l'adresse `De` ou `À`
  correspond à l'`email` d'un contact. Sinon : non rattaché.
- **RF-2 — Deux sens** : chaque email est marqué **entrant** (reçu) ou **sortant** (envoyé par
  Laurence), déterminé par l'adresse de la boîte synchronisée.
- **RF-3 — Relance, 2 cas** :
  - *En attente de réponse* : le **dernier** message du fil est **sortant** et date de plus de
    **N jours** (N réglable, défaut **5**).
  - *Réponse due* : le **dernier** message est **entrant** et **non traité** (Laurence n'a pas
    répondu). ⚠️ prioritaire.
- **RF-4 — Mail inconnu** : au départ, simplement **ignoré** (journalisé dans un compteur
  « X mails non rattachés »). *(La création auto de lead viendra plus tard.)*
- **RF-5 — Analyse IA** : « Analyse & relances » envoie le fil du contact à l'API Claude et
  renvoie : (a) **résumé** court, (b) **verdict** (relancer oui/non + raison + délai conseillé),
  (c) **brouillon** en français, dans un ton chaleureux/pro, (d) **lien Fluent Booking** inséré
  selon le contexte (voir RF-6). Le résultat est **affiché** et **enregistré** sur la fiche
  (horodaté). **Aucun envoi** : Laurence copie/colle.
- **RF-6 — Liens Fluent Booking** (stockés en réglages) :
  - appel visio 1-to-1 : `…event=appel-visio1`
  - séance découverte gratuite : page `https://homeharmony.be/seance-prana-gratuite/`
  - journée nettoyage aura : page `https://homeharmony.be/nettoyage-aura-chakras`
- **RF-7 — Brief du matin** : liste « Réponses dues » (RF-3b) puis « À relancer » (RF-3a),
  triées par ancienneté. Chaque ligne : contact + dernier échange + bouton vers la fiche.
- **RF-8 — Synchronisation** : « Synchroniser » récupère les emails **depuis la dernière
  synchro** (ou les **90 derniers jours** à la première fois), **sans doublon** (clé = identifiant
  de message email).

## 6. Données (nouveaux éléments)

- **Table `emails`** (ou `messages`), rattachée à `contact` :
  `contact_id`, `direction` (entrant/sortant), `de`, `a`, `objet`, `extrait`/`corps`,
  `date_envoi`, `message_id` (unique), `fil_id` (regroupement), `boite` (adresse source),
  `traite` (bool, pour RF-3b).
- **Champs d'analyse** sur `contact` (ou table `analyses`) : `derniere_analyse_resume`,
  `derniere_analyse_verdict`, `derniere_analyse_brouillon`, `analyse_faite_le`.
- **Réglages** (table `parametres`, chiffrés au repos) : `gmail_email`, `gmail_app_password`,
  `anthropic_api_key`, `anthropic_modele` (défaut : modèle économique), `relance_delai_jours`
  (défaut 5), les 3 **liens Fluent Booking**.

## 7. Intégrations & technique

- **Gmail** : lecture **IMAP** avec **mot de passe d'application** (2FA à activer côté Google).
  Librairie IMAP côté Laravel. Lecture seule (aucun envoi).
- **Claude** : **API Anthropic** côté serveur (clé en réglages, jamais dans le code/Git).
  Appel au moment de l'analyse uniquement (coût maîtrisé). Modèle **économique par défaut**,
  changeable en réglages.
- **Sécurité / RGPD** : identifiants et clé **chiffrés** en base ; emails stockés **sur
  l'hébergement de Laurence** ; seuls les contacts connus sont traités ; aucun envoi
  automatique ; données jamais transmises à un tiers autre que l'API Claude pour l'analyse
  demandée.

## 8. Critères d'acceptation

1. Je peux enregistrer mes **réglages** (Gmail + clé API + liens) ; ils sont **chiffrés** et
   **jamais** visibles en clair dans le dépôt.
2. **« Synchroniser »** importe les emails des contacts connus **sans doublon** et les affiche
   sur les fiches, correctement marqués **entrant/sortant**.
3. La fiche affiche le **fil** par ordre chronologique.
4. **« Analyse & relances »** produit **résumé + verdict + brouillon**, insère le **bon lien**
   Fluent Booking quand c'est pertinent, et **enregistre** le résultat (horodaté). Rien n'est
   envoyé.
5. Le **brief** liste correctement « réponses dues » et « à relancer » selon RF-3.
6. Un mail d'une adresse **inconnue** n'est pas rattaché et n'empêche pas la synchro.
7. Les **tests** (Pest) passent et le code est **formaté** (Pint).

## 9. Prérequis à la charge de Laurence (je la guiderai pas-à-pas)

- Activer la **double authentification** sur `laurencehomeharmony@` et générer un
  **mot de passe d'application** Gmail.
- Créer un compte **API Anthropic** et une **clé API**.

## 10. Risques / points d'attention

- Gmail peut exiger la 2FA avant de donner un mot de passe d'application.
- Volume d'emails important à la 1ʳᵉ synchro → on borne à **90 jours** au départ.
- Qualité des brouillons → on affinera le **ton** avec quelques exemples de tes vrais mails.

---

*Prochaine étape (après validation) : `plan.md` (architecture technique, choix de librairie
IMAP, schéma des tables, écrans) puis `tasks.md` (découpage en tâches) et implémentation.*

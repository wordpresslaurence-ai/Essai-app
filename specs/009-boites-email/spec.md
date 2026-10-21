# Spec — Brique 9 : Boîtes e-mail multiples par pôle

> Étape **specify** (Spec Kit). Langue : français. Statut : **à valider** avant `plan.md`.
> Objet : permettre à l'assistant de lire **plusieurs boîtes e-mail** (Gmail et/ou Hostinger),
> **rattachées chacune à un pôle**, au lieu d'une seule boîte Gmail figée.

---

## 1. Objectif

Aujourd'hui, l'assistant lit **une seule boîte Gmail**, dont l'adresse du serveur est écrite
en dur dans le code. Laurence veut :

- brancher **plusieurs boîtes** (ex. pour le pôle **Prana** : `laurence@homeharmony.be`
  **+** une boîte Gmail) ;
- supporter **deux fournisseurs** : **Gmail** et **Hostinger** (et, en option, « autre » IMAP) ;
- **rattacher chaque boîte à un pôle** : selon le pôle, l'assistant va lire des boîtes différentes.

## 2. Récits utilisateur

- **US-1** : dans les Réglages, j'ajoute une **boîte e-mail** en choisissant son **type**
  (Gmail / Hostinger), son **adresse**, son **mot de passe**, et le **pôle** auquel elle appartient.
- **US-2** : j'ajoute **plusieurs boîtes** (ex. 2 pour Prana), je peux les **modifier**,
  les **désactiver** ou les **supprimer**.
- **US-3** : quand je lance la synchronisation, l'assistant lit **toutes les boîtes actives**
  et rattache les e-mails aux **fiches contacts** correspondantes.
- **US-4** : un e-mail se rattache à **toute fiche ayant cette adresse** (peu importe le pôle) —
  je ne rate aucun échange (**option « ne rien rater »** validée).
- **US-5** : sur une boîte Gmail, je peux tester la connexion avant de m'en servir.

## 3. Périmètre

**Inclus**
- Nouvelle section **« Boîtes e-mail »** dans les Réglages : liste des boîtes + ajout / modif /
  suppression / activation.
- Formulaire boîte : **libellé** (facultatif), **fournisseur** (Gmail / Hostinger / Autre),
  **adresse e-mail**, **mot de passe** (chiffré), **pôle** (facultatif), **active** (oui/non).
  - Les réglages techniques (serveur IMAP, port, dossier « Envoyés ») sont **pré-remplis
    automatiquement** selon le fournisseur ; modifiables uniquement en mode « Autre ».
- **Lecteur IMAP paramétrable** : lit n'importe quel serveur (plus seulement Gmail).
- **Synchronisation multi-boîtes** : parcourt toutes les boîtes actives, déduplique, marque
  le sens (entrant/sortant) et rattache aux contacts par adresse (**option 2**).
- **Bouton « Tester »** par boîte.
- Traçabilité : chaque e-mail importé garde la **boîte d'origine** (et donc son pôle).

**Exclus (plus tard)**
- **Envoi** d'e-mails (l'assistant continue de **seulement proposer** des brouillons).
- Filtrer/afficher les e-mails **par pôle** dans l'interface (la donnée est stockée, l'écran
  viendra plus tard si besoin).
- Restreindre le rattachement aux contacts d'un seul pôle (on a choisi « ne rien rater »).
- Détection de l'intention, température auto, multilingue, etc. (autres briques).

## 4. Règles fonctionnelles

- **RF-1** : une **boîte e-mail** a un **fournisseur** (`gmail`, `hostinger`, `autre`), une
  **adresse**, un **mot de passe** (stocké **chiffré**), un **pôle** facultatif, un indicateur
  **active**, et une date de **dernière synchro** propre.
- **RF-2** : presets techniques par fournisseur —
  - `gmail` → serveur `imap.gmail.com`, port `993`, dossier envoyés `[Gmail]/Sent Mail` ;
  - `hostinger` → serveur `imap.hostinger.com`, port `993`, dossier envoyés `INBOX.Sent` ;
  - `autre` → serveur, port et dossier **saisis** par Laurence.
- **RF-3** : la **synchronisation** lit **INBOX** + le **dossier « Envoyés »** de **chaque boîte
  active**, depuis sa propre **dernière synchro** (sinon 90 jours en arrière).
- **RF-4** : **sens** d'un message = **sortant** si l'expéditeur est l'adresse de la boîte,
  sinon **entrant**.
- **RF-5** : **rattachement** = on cherche une fiche contact dont l'adresse correspond à
  l'expéditeur **ou** à un destinataire (hors adresse de la boîte). **Toute** fiche qui
  correspond est reliée, **sans filtrage par pôle** (option « ne rien rater »).
- **RF-6** : **déduplication** par `message_id` **global** : un même message n'est importé
  qu'une fois, même s'il apparaît dans deux boîtes.
- **RF-7** : expéditeur **inconnu** sur un message **entrant** → création d'une **tâche
  « Créer le prospect »** (comportement actuel conservé, sans doublon).
- **RF-8** : chaque e-mail importé mémorise la **boîte d'origine** (adresse + référence interne),
  donc le **pôle** de provenance.
- **RF-9** : l'assistant est **configuré** pour la lecture dès qu'il existe **au moins une boîte
  active**. (La clé API Claude reste requise séparément pour l'analyse IA.)
- **RF-10** : supprimer une **boîte** n'efface **pas** les e-mails déjà importés (ils gardent
  juste une origine « boîte supprimée »). Supprimer un **pôle** ne supprime pas ses boîtes :
  elles repassent « sans pôle ».

## 5. Modèle de données

- Nouvelle table **`boites_email`** : `id`, `libelle` (nullable), `fournisseur`, `email`,
  `mot_de_passe` (chiffré), `imap_host` (nullable), `imap_port` (défaut 993),
  `dossier_envoyes` (nullable), `pole_id` (nullable, FK `poles`), `active` (défaut vrai),
  `derniere_synchro_at` (nullable), timestamps.
- Table **`emails`** : ajout d'une colonne `boite_email_id` (nullable, FK `boites_email`,
  `nullOnDelete`). La colonne existante `boite` (adresse) est conservée.
- **Relations** : `Pole hasMany BoiteEmail` ; `BoiteEmail belongsTo Pole` ;
  `Email belongsTo BoiteEmail`.

## 6. Compatibilité & migration (sans perte de données)

- **Migrations additives** uniquement (nouvelle table + nouvelle colonne). Aucune donnée
  existante supprimée.
- **Reprise de l'ancien réglage** : si une adresse Gmail est déjà renseignée dans les
  Réglages (`Parametre.gmail_email` / `gmail_app_password`), une **boîte Gmail est créée
  automatiquement** à partir de ces valeurs à la première mise à jour. (En production actuelle,
  rien n'est encore configuré → départ propre.)
- Les anciens champs `Parametre.gmail_*` et `derniere_synchro_at` restent en place le temps de
  la bascule ; ils pourront être retirés dans une version ultérieure.

## 7. Écrans

- **Réglages → Boîtes e-mail** : tableau des boîtes (libellé, adresse, fournisseur, pôle, état),
  boutons **Ajouter / Modifier / Tester / Activer-Désactiver / Supprimer**.
- **Brief / synchronisation** : inchangé visuellement ; le bouton « Synchroniser » lit désormais
  toutes les boîtes actives et affiche un résumé (n boîtes, x e-mails importés, y tâches créées).

## 8. Tests (Pest, CI)

- Presets IMAP corrects par fournisseur (gmail/hostinger/autre).
- Synchronisation multi-boîtes : 2 boîtes → e-mails des deux importés, dédoublonnés.
- Sens entrant/sortant correct selon la boîte.
- Rattachement « option 2 » : un e-mail reçu sur la boîte d'un pôle se rattache à une fiche
  d'un **autre** pôle si l'adresse correspond.
- Chiffrement du mot de passe de boîte (jamais en clair en base).
- Reprise : un `Parametre.gmail_email` existant crée bien une boîte Gmail.
- Un expéditeur inconnu crée une tâche « Créer le prospect » (sans doublon).

## 9. Sécurité

- Mots de passe de boîtes **chiffrés** en base (cast `encrypted`), jamais affichés en clair,
  jamais renvoyés par l'interface.
- Lecture IMAP en **lecture seule** (`OP_READONLY`), comme aujourd'hui.
- L'assistant **n'envoie jamais** d'e-mail.

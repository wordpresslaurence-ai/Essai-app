# Spec — Brique 12 : Campagne d'invitations à une session & modèles de messages

> Étape **specify** (Spec Kit). Langue : français. Statut : en cours.
> Sert le parcours **invitation → séance découverte → appel → inscription**.

## 1. Objectif

Avant chaque séance (ex. séance découverte gratuite), Laurence invite personnellement des
dizaines de contacts (professeurs, studios). Aujourd'hui c'est à la main, sans trace. On veut,
depuis la fiche d'une session : choisir les contacts, obtenir un **brouillon personnalisé par
personne** à partir d'un **modèle de message**, et **suivre** qui a été invité, relancé, a
répondu ou s'est inscrit.

Le CRM **n'envoie rien** : il prépare le texte (copier, ouvrir la messagerie, ouvrir WhatsApp)
et Laurence envoie elle-même.

## 2. Règles fonctionnelles

### Modèles de messages
- **RF-1** : un modèle a un **nom**, un **canal** (`email` ou `message` court), un **objet**
  (e-mail seulement) et un **corps**. Écran de gestion : Réglages → Modèles de messages.
- **RF-2** : le corps et l'objet acceptent des **variables** entre accolades :
  `{prenom}`, `{nom}`, `{ville}`, `{entreprise}`, `{session}`, `{date}`, `{heure}`, `{lieu}`,
  `{lien}`, `{moi}`. Une variable sans valeur est rendue comme un **repère entre crochets**
  (`[ville]`) pour que le manque se voie avant l'envoi. `{prenom}` se rabat sur le nom complet.
- **RF-3** : un bouton crée des **modèles de départ** (invitation e-mail, message court,
  e-mail studio, relance) s'il n'en existe aucun du même nom.

### Session
- **RF-4** : une session a un **lien d'inscription** optionnel (`lien_inscription`), repris par
  la variable `{lien}`.

### Campagne d'invitations
- **RF-5** : écran `sessions/{seance}/invitations`, accessible par un bouton **« Inviter »** sur
  la fiche de la session.
- **RF-6 — Choisir les contacts** : recherche + filtres (type, étiquette, pôle, catégorie, pays).
  Sont exclus : contacts **archivés**, **« ne plus contacter »**, et ceux **déjà dans la
  campagne**. Sélection multiple (dont « tout cocher ») puis ajout.
- **RF-7 — Statuts** : `a_inviter` → `invitee` → `relancee`, plus `a_repondu` et `declinee`.
  Un contact ayant une inscription active à la session est affiché **« Inscrit »**, quel que
  soit son statut d'invitation.
- **RF-8 — Préparer le message** : pour une invitation, choix du modèle → objet et corps rendus,
  **modifiables** avant envoi. Boutons : copier, ouvrir la messagerie (`mailto:`), ouvrir
  WhatsApp (si un numéro exploitable existe).
- **RF-9 — Marquer comme envoyé** : passe l'invitation à `invitee` (ou `relancee` si elle était
  déjà `invitee`), date l'envoi, et crée une **activité terminée** de type e-mail sur la fiche du
  contact avec le texte envoyé. L'invitation suivante « à inviter » s'ouvre automatiquement.
- **RF-10 — À relancer** : invitation `invitee` depuis **≥ 5 jours**, contact non inscrit.
- **RF-11** : depuis la campagne on peut **inscrire** le contact à la session, changer son
  statut, ou le **retirer** de la campagne.
- **RF-12** : une invitation est unique par couple (session, contact).

## 3. Hors périmètre
- Envoi d'e-mails ou de messages par le CRM.
- Personnalisation par IA du « détail précis » (à voir ensuite).
- Arrivée automatique des inscriptions du site (brique suivante, FluentBooking).

## 4. Données
- `modeles_message` : `nom`, `canal`, `objet`, `corps`.
- `invitations` : `seance_id`, `contact_id`, `statut`, `modele_message_id`, `invitee_le`,
  `relancee_le` ; unique (`seance_id`, `contact_id`).
- `seances.lien_inscription`.
- Les deux nouvelles tables sont incluses dans la sauvegarde.

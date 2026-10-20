# Spec — Brique : Assistant de prospection (profs de yoga)

> Étape **specify** (Spec Kit). Suite de l'assistant `specs/005-assistant-ia/`.
> Langue : français. Statut : **à valider** avant `plan.md`.

---

## 1. Objectif

Permettre à l'assistant de **rédiger un brouillon de mail de prospection** vers un prof de
yoga, pour l'inviter à découvrir la **formation de profs Prana Vidya**. Le brouillon est
**personnalisé**, **relu par Laurence** et **jamais envoyé automatiquement**.

## 2. Contexte

- Laurence **n'a pas de liste** de profs : elle en repère un (bouche‑à‑oreille, réseaux),
  l'ajoute comme **contact**, puis génère le mail de prospection depuis sa fiche.
- ~3 promos par an, 10 places. (La gestion des sessions & listes d'attente est une **autre
  brique**.)

## 3. Périmètre

**Inclus**
- **Offre & liens par catégorie** (Réglages, **source principale**) : accordéon par catégorie
  (groupées par pôle). Chaque catégorie a une **description d'offre sans limite** (on peut coller
  toute la page de vente) + une **liste de liens** (libellé + URL) qu'on **ajoute/supprime**.
  L'IA repère la **catégorie du contact** et s'appuie dessus. ⚠️ L'IA **ne peut pas ouvrir les
  liens** : elle se fonde sur la description et présente les liens comme des appels à l'action.
- Une **« note générale » facultative** (ancien pitch) : ton/contexte global qui s'applique à
  toute la prospection. Non obligatoire.
- L'IA combine les **bons métiers** (copywriting, marketing bienveillant, formatrice de profs
  expérimentée). Ton **expert, crédible et chaleureux, non insistant**, qui **donne envie de
  s'inscrire**. Le mail est **toujours complet** (pas coupé).
- Sur la **fiche contact** : un bouton **« Rédiger un mail de prospection »** qui génère un
  **objet + un corps** de mail via Claude, personnalisé à partir du contact (nom, notes,
  étiquettes, catégorie) et du pitch.
- Un champ **« angle / précision »** facultatif avant de générer (ex. « enseigne le vinyasa à
  Namur, rencontrée au salon »).
- Résultat affiché dans une **zone éditable** avec un bouton **« Copier »**. **Aucun envoi.**
- **Trace automatique** : à la génération, l'assistant crée (ou met à jour) **une tâche** sur le
  contact — *« Envoyer le mail de prospection à … »* avec le brouillon dans les notes. À faire =
  rappel d'envoyer ; cochée = trace de « déjà contacté ». (Dédupliquée : une seule tâche active
  par contact, mise à jour si on régénère.)

**Exclus (briques suivantes)**
- **Envoi** du mail (manuel, hors CRM pour l'instant ; dépôt en brouillon Gmail plus tard).
- **Sessions & listes d'attente** (promos datées, 10 places).
- Import de listes de profs, séquences de relance automatiques.

## 4. Récits utilisateur

- **US-1** : j'ajoute un prof comme contact, j'ouvre sa fiche, je clique « Rédiger un mail de
  prospection » → j'obtiens un brouillon personnalisé que je relis, j'ajuste, je copie.
- **US-2** : je renseigne une fois mon **pitch** dans les Réglages ; tous les mails s'appuient
  dessus (ton, offre, ce que je cherche) sans que l'IA invente.
- **US-3** : j'ajoute un **angle** (« elle fait du yin yoga ») pour orienter le mail.

## 5. Règles fonctionnelles

- **RF-1** : l'assistant **ne fait que proposer** : jamais d'envoi, jamais d'enregistrement
  d'e-mail « envoyé » sans action de Laurence.
- **RF-2** : si **ni l'offre du pôle ni une note générale** ne sont renseignées, un message
  invite à remplir l'offre du pôle d'abord (l'IA ne doit pas inventer l'offre).
- **RF-3** : si la **clé API** manque, message clair (comme pour l'analyse existante).
- **RF-4** : le brouillon est en **français**, chaleureux et non insistant ; il peut inclure le
  **lien de séance découverte** si pertinent.
- **RF-5** : la génération **ne modifie pas** le contact lui-même ; elle crée/met à jour une
  **tâche de trace** (cf. §3), jamais un e-mail « envoyé ».

## 6. Écrans / données

- **Réglages** : nouveau champ **« Pitch de prospection »** (texte long) → colonne
  `pitch_prospection` sur `parametres`.
- **Fiche contact** : bouton + champ « angle » + zone résultat (objet, corps, copier).
- **Service** : nouvelle méthode sur `AssistantIA`
  (`redigerProspection(Contact, ?string $angle): ResultatProspection`), implémentée par
  `AssistantClaude` et `AssistantFactice` ; DTO `ResultatProspection { objet, corps, modele }`.

## 7. Critères d'acceptation

1. Avec un pitch renseigné, le bouton génère un **objet + corps** cohérents et personnalisés.
2. Sans pitch → message d'invitation, pas d'appel IA.
3. Rien n'est envoyé ni enregistré automatiquement ; la zone est **éditable + copiable**.
4. **Tests** Pest (assistant factice, pas d'appel réseau) ; **CI verte** (PHP 8.4 & 8.5).

## 8. Découpage pressenti (tasks)

- **T1 ✅** — Réglages : champ `pitch_prospection` (migration + modèle + écran) + test.
- **T2 ✅** — Service : `redigerProspection` (interface + `AssistantClaude` + `AssistantFactice`
  + DTO `ResultatProspection`) + test (factice).
- **T3 ✅** — Fiche contact : bouton + angle + zone résultat (copier) + **tâche de trace** (upsert) + test.

---

*Prochaine étape (après validation) : `plan.md` puis implémentation tâche par tâche, CI verte
à chaque étape.*

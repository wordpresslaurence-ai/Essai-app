# Plan — Brique 9 : Boîtes e-mail multiples par pôle

> Étape **plan** (Spec Kit). Référence : `spec.md` (validé).

## Modèle & base
- Table `boites_email` (migration additive) + modèle `BoiteEmail` (secret chiffré,
  presets Gmail/Hostinger, helpers IMAP, scope `actives`).
- Colonne `emails.boite_email_id` (nullable, FK) pour la traçabilité.
- Migration de reprise : ancien réglage Gmail (`Parametre`) → une boîte Gmail (best-effort).
- Relations : `Pole hasMany BoiteEmail`, `Email belongsTo BoiteEmail`.

## Lecture & synchronisation
- `LecteurEmails` : méthodes `tester(BoiteEmail)` / `recupererDepuis(BoiteEmail, ?date)`.
- `ClientImap` : serveur/port/dossiers lus depuis la boîte (plus de Gmail figé).
- `SynchronisationEmails` : boucle sur `BoiteEmail::actives()`, dédoublonne globalement
  (message_id), rattache « option 2 » (toute fiche par adresse), met à jour la synchro par boîte.
- `ResultatSynchronisation` : ajoute le compteur de boîtes lues.

## Réglages (écran dédié)
- `Reglages\BoitesEmail` (+ vue) : liste, ajout/modif/suppression, activation, test par boîte.
  Presets masqués sauf « Autre ». Secret jamais réaffiché. Route `reglages.boites`.
- `Reglages\Assistant` : retrait de la connexion Gmail unique ; lien vers l'écran des boîtes ;
  « Synchroniser » lit désormais toutes les boîtes actives.
- `Parametre::estConfigure()` → « IA prête » (clé API présente) ; la lecture dépend des boîtes.

## Assistant (Brief)
- Filtre **par pôle** : `CalculRelances(?delai, ?poleId)` restreint via `categorie.pole_id` ;
  menu déroulant « Tous les pôles / … » dans la vue (live).

## Tests (Pest)
- Presets IMAP, CRUD boîte, chiffrement du mot de passe, synchro multi-boîtes + dédoublonnage,
  rattachement « ne rien rater » (pôle différent), filtre par pôle du Brief, test de connexion.

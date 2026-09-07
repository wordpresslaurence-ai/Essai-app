# Tâches — Module Contacts / Entreprises

> **Étape 3 (Spec Kit) — le découpage.**
> Traduit le [plan technique](./plan.md) en tâches concrètes, ordonnées et vérifiables,
> conformes à la [constitution](../../constitution.md). On implémente dans l'ordre ; une
> tâche `[P]` peut être faite en parallèle d'une autre `[P]` du même groupe.
>
> - **ID :** 001-contacts
> - **Statut :** prêt à implémenter
> - **Version :** 0.1.0
> - **Date :** 2026-09-07
>
> **Légende :** `[P]` = parallélisable · chaque tâche cite l'exigence (EF-xx) et/ou la
> section du plan qu'elle réalise · « ✅ Fait quand » = critère de fin.

---

## Phase 0 — Fondations du projet Laravel

- [x] **T001** — Initialiser le projet Laravel 13 (PHP 8.2+) dans le dépôt.
  ✅ Fait quand : `php artisan --version` renvoie 13.x et l'appli démarre en local.
- [x] **T002** — Configurer la base (SQLite en dev, MySQL en prod) et le `.env` (+ `.env.example` sans secret).
  ✅ Fait quand : la connexion DB fonctionne, `.env` est ignoré par Git. *(constitution art. 3.3)*
- [x] **T003** [P] — Installer et configurer **Laravel Pint** (PSR-12).
  ✅ Fait quand : `./vendor/bin/pint --test` passe sur le dépôt.
- [x] **T004** [P] — Installer **Pest** et lancer la suite vide.
  ✅ Fait quand : `./vendor/bin/pest` s'exécute sans erreur.
- [x] **T005** — Installer **Breeze (stack Livewire)** : authentification + Tailwind + Alpine.
  ✅ Fait quand : inscription/connexion fonctionnent, layout de base en place. *(plan §4, §7)*
- [x] **T006** [P] — Mettre en place le fichier de langue `lang/fr` et forcer la locale `fr`.
  ✅ Fait quand : les messages de validation s'affichent en français. *(constitution art. 4.3)*

---

## Phase 1 — Base de données (migrations)

- [ ] **T010** — Migration table **`contacts`** (tous les champs du plan §2, index sur
  `type`, `nom`, `email`, `entreprise_id` ; FK `entreprise_id → contacts.id`
  `onDelete('set null')` ; colonne `archived_at`).
  ✅ Fait quand : `migrate` passe ; la contrainte de détachement existe. *(EF-04, plan §2)*
- [ ] **T011** [P] — Migration table **`etiquettes`** (`nom` unique, `couleur`).
  ✅ Fait quand : `migrate` passe. *(EF-11)*
- [ ] **T012** [P] — Migration table pivot **`contact_etiquette`**.
  ✅ Fait quand : `migrate` passe ; clé composite en place. *(EF-11)*

---

## Phase 2 — Modèles Eloquent

- [ ] **T020** — Modèle **`Contact`** : `$fillable`, casts, relations `entreprise()`,
  `personnes()`, `etiquettes()`, accesseur `nomComplet`.
  ✅ Fait quand : les relations renvoient les bons enregistrements (vérifié par tinker/test). *(plan §3)*
- [ ] **T021** — Scopes du modèle `Contact` : `personnes`, `entreprises`, `actifs`,
  `archives`, `recherche($terme)` (insensible à la casse) + global scope « actifs ».
  ✅ Fait quand : `Contact::recherche('x')->actifs()` filtre correctement. *(EF-05, 09, 10)*
- [ ] **T022** [P] — Modèle **`Etiquette`** + relation `contacts()`.
  ✅ Fait quand : l'association N–N fonctionne. *(EF-11)*
- [ ] **T023** [P] — **Factories** `Contact` et `Etiquette` pour les tests.
  ✅ Fait quand : les factories produisent des données valides des deux types. *(plan §9)*

---

## Phase 3 — Règles de validation belges

- [ ] **T030** [P] — Règle **`NumeroEntrepriseBe`** (10 chiffres, préfixe 0/1, clé de
  contrôle modulo 97, normalisation des points/espaces).
  ✅ Fait quand : le **test unitaire T031** passe. *(EF-15, plan §5)*
- [ ] **T031** [P] — Test Pest de `NumeroEntrepriseBe` : cas valide, clé fausse, mauvais format.
  ✅ Fait quand : les 3 cas sont couverts et verts.
- [ ] **T032** [P] — Règle **`NumeroTvaBe`** (`BE` + n° d'entreprise valide ; cohérence
  avec `numero_entreprise` si présent).
  ✅ Fait quand : le **test unitaire T033** passe. *(EF-16, plan §5)*
- [ ] **T033** [P] — Test Pest de `NumeroTvaBe` : valide + incohérent.
  ✅ Fait quand : les cas sont couverts et verts.

---

## Phase 4 — Autorisations

- [ ] **T040** [P] — **`ContactPolicy`** et **`EtiquettePolicy`** (au MVP : l'utilisateur
  connecté a tous les droits, encapsulé pour évoluer).
  ✅ Fait quand : les policies sont enregistrées et appliquées aux composants. *(EF art. 6.4, plan §7)*

---

## Phase 5 — Interface Livewire

- [ ] **T050** — Composant **`Contacts\Liste`** : tableau paginé, recherche live, filtres
  (type / étiquette / actif-archivé), tri par nom et date.
  ✅ Fait quand : recherche + filtres + pagination fonctionnent à l'écran. *(EF-05, 09, 10 ; plan §4)*
- [ ] **T051** — Composant **`Contacts\Formulaire`** (création + édition) : champs
  conditionnels selon `type`, validation branchée (email obligatoires, BCE/TVA).
  ✅ Fait quand : on crée et modifie personne + entreprise avec messages d'erreur FR. *(EF-01,03,06,07,12,14)*
- [ ] **T052** — Détection de **doublons** dans `Formulaire` : avertissement non bloquant
  (même email, ou nom + entreprise).
  ✅ Fait quand : un doublon potentiel affiche un avertissement sans empêcher l'enregistrement. *(EF-13)*
- [ ] **T053** — Composant **`Contacts\FicheDetail`** : coordonnées, liste des personnes
  (si entreprise), emplacement réservé « historique activités/opportunités ».
  ✅ Fait quand : la fiche affiche les infos et les personnes rattachées. *(EF-02, 08, 22)*
- [ ] **T054** — Actions **archiver / désarchiver / supprimer** (suppression avec modale de
  confirmation Alpine).
  ✅ Fait quand : les 3 actions marchent ; l'archivé disparaît des listes actives. *(EF-04)*
- [ ] **T055** [P] — Composant **`Contacts\GestionEtiquettes`** (CRUD léger) + association
  d'étiquettes à un contact.
  ✅ Fait quand : on crée des étiquettes et on les associe à un contact. *(EF-11)*
- [ ] **T056** [P] — Passe **accessibilité/responsive** : `<label>` associés, focus visible,
  navigation clavier, contrastes AA, affichage mobile.
  ✅ Fait quand : les écrans Contacts respectent l'article 8 de la constitution.

---

## Phase 6 — Import CSV

- [ ] **T060** — Composant **`Contacts\ImportCsv`** : upload du fichier (stockage local),
  lecture du CSV.
  ✅ Fait quand : un CSV est chargé et ses lignes sont lues. *(EF-17, plan §6)*
- [ ] **T061** — Écran de **mapping** colonnes CSV → champs contact.
  ✅ Fait quand : l'utilisateur associe chaque colonne à un champ. *(EF-18)*
- [ ] **T062** — **Récapitulatif** avant écriture : lignes valides / en erreur / doublons.
  ✅ Fait quand : rien n'est écrit tant que l'utilisateur n'a pas confirmé. *(EF-19)*
- [ ] **T063** — **Traitement** : insertion des lignes valides (transaction), rapport des
  lignes invalides ignorées (réutilise les règles de la Phase 3/5).
  ✅ Fait quand : valides importées, invalides rapportées sans faire échouer l'import. *(EF-20)*

---

## Phase 7 — Routes et navigation

- [ ] **T070** — Déclarer les routes (`/contacts`, `/contacts/creer`,
  `/contacts/{contact}`, `/contacts/{contact}/modifier`, `/contacts/import`,
  `/etiquettes`) derrière le middleware `auth`, + entrées de menu.
  ✅ Fait quand : toutes les pages sont accessibles depuis la navigation. *(plan §8)*

---

## Phase 8 — Tests de parcours (feature)

- [ ] **T080** [P] — Test : créer personne + entreprise → visibles en liste. *(critère 1)*
- [ ] **T081** [P] — Test : rattacher une personne à une entreprise → visible sur la fiche
  entreprise. *(EF-08, critère 2)*
- [ ] **T082** [P] — Test : supprimer une entreprise avec personnes → personnes détachées,
  non supprimées. *(EF-04, critère... )*
- [ ] **T083** [P] — Test : recherche + filtres (type, étiquette, statut). *(EF-09/10)*
- [ ] **T084** [P] — Test : archiver puis désarchiver un contact. *(EF-04)*
- [ ] **T085** [P] — Test : import CSV avec lignes valides + invalides. *(EF-17→20)*
- [ ] **T086** — Vérifier que **toute la suite Pest est verte** et que **Pint** passe.
  ✅ Fait quand : `pest` vert + `pint --test` propre → module « fini » au sens constitution art. 5.

---

## Récapitulatif des dépendances entre phases

```
Phase 0 (fondations)
   └─> Phase 1 (migrations)
          └─> Phase 2 (modèles) ──> Phase 4 (policies)
                 ├─> Phase 3 (règles belges)   [peut démarrer dès Phase 2]
                 └─> Phase 5 (interface) ──> Phase 6 (import) ──> Phase 7 (routes)
                                                    └─────────> Phase 8 (tests feature)
```

Les tests unitaires (Phase 3) accompagnent leurs règles ; les tests feature (Phase 8)
viennent après l'interface et l'import.

---

## Historique

| Version | Date | Changement |
|---|---|---|
| 0.1.0 | 2026-09-07 | Découpage initial en tâches du module Contacts. |

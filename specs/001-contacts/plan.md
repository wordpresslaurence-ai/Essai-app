# Plan technique — Module Contacts / Entreprises

> **Étape 2 (Spec Kit) — le COMMENT.**
> Traduit la [spec Contacts v1.0](./spec.md) en architecture concrète, dans le respect
> strict de la [constitution](../../constitution.md) (Laravel 12, PHP 8.2+, MySQL, TALL,
> Pest, français côté métier).
>
> - **ID :** 001-contacts
> - **Statut :** à valider
> - **Version :** 0.1.0
> - **Date :** 2026-09-07

---

## 1. Décision d'architecture centrale : une seule table `contacts`

**Choix retenu :** un **modèle unique `Contact`** avec un champ `type` (`personne` /
`entreprise`), plutôt que deux tables séparées.

**Pourquoi :**
- C'est exactement le modèle d'Odoo (`res.partner` avec un drapeau `is_company`) —
  cohérent avec l'outil que l'utilisateur remplace.
- Personne et Entreprise partagent la majorité des champs (email, téléphone, adresse,
  étiquettes, notes, archivage).
- Le Pipeline et les Activités pourront pointer vers **un seul type d'entité**
  (`Contact`), quel que soit personne ou entreprise. Beaucoup plus simple que gérer deux
  cibles.
- Conforme à l'article 1.5 de la constitution (« simplicité d'abord »).

**Contrepartie assumée :** quelques colonnes ne concernent qu'un type (ex. `prenom`,
`fonction` pour une personne ; `numero_entreprise`, `numero_tva` pour une entreprise).
Elles restent simplement nulles pour l'autre type. Acceptable au vu du gain de
simplicité.

---

## 2. Modèle de données (migrations)

### Table `contacts`
| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint auto | clé primaire |
| `type` | enum(`personne`,`entreprise`) | discriminant, indexé |
| `nom` | string | obligatoire (nom de personne **ou** raison sociale) |
| `prenom` | string, nullable | personnes uniquement |
| `fonction` | string, nullable | poste de la personne |
| `entreprise_id` | bigint, nullable, FK → `contacts.id` | rattachement personne → entreprise (auto-référence) |
| `email` | string, nullable | validé si présent |
| `telephone` | string, nullable | |
| `site_web` | string, nullable | entreprises |
| `numero_entreprise` | string, nullable | BCE/KBO, validé (voir §5) |
| `numero_tva` | string, nullable | validé (voir §5) |
| `secteur` | string, nullable | entreprises |
| `adresse_rue` | string, nullable | |
| `adresse_code_postal` | string, nullable | |
| `adresse_ville` | string, nullable | |
| `adresse_pays` | string, default `BE` | |
| `notes` | text, nullable | |
| `archived_at` | timestamp, nullable | archivage (≠ suppression) |
| `created_at` / `updated_at` | timestamps | traçabilité (EF-21) |

- **Contrainte FK** `entreprise_id` → `contacts.id`, `onDelete('set null')` :
  couvre nativement la règle EF-04 / §6 (à la suppression d'une entreprise, les
  personnes sont **détachées**).
- **Index** sur `type`, `nom`, `email`, `entreprise_id` (recherche/filtre, EF-09/10,
  conforme constitution art. 7.2).

### Table `etiquettes`
| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint auto | |
| `nom` | string, unique | |
| `couleur` | string, nullable | pour l'affichage |
| timestamps | | |

### Table pivot `contact_etiquette`
| `contact_id` FK → contacts | `etiquette_id` FK → etiquettes | clé primaire composite |

*(Relation N–N, EF-11.)*

**Archivage :** on utilise le **SoftDeletes-like via `archived_at`** géré par un
*global scope* « actifs » (et non les SoftDeletes de Laravel, réservés à la vraie
suppression). Ainsi archivage et suppression restent deux notions distinctes (EF-04).

---

## 3. Modèles Eloquent et relations

- **`App\Models\Contact`**
  - `entreprise()` → `belongsTo(Contact::class, 'entreprise_id')` (la société de la personne)
  - `personnes()` → `hasMany(Contact::class, 'entreprise_id')` (les personnes de la société, EF-08)
  - `etiquettes()` → `belongsToMany(Etiquette::class)`
  - Scopes : `scopePersonnes`, `scopeEntreprises`, `scopeActifs`, `scopeArchives`,
    `scopeRecherche($terme)` (EF-09).
  - Casts, `$fillable`, accesseur `nomComplet` (prénom + nom pour une personne).
- **`App\Models\Etiquette`**
  - `contacts()` → `belongsToMany(Contact::class)`

**EF-08b (une seule entreprise par personne)** : garanti par la simple colonne
`entreprise_id` (pas de table pivot) — impossible d'en avoir plusieurs.

---

## 4. Interface — composants Livewire (stack TALL)

Toutes les pages sont rendues côté serveur, en français (constitution art. 2.3 / 4.3).

| Composant Livewire | Rôle | Exigences couvertes |
|---|---|---|
| `Contacts\Liste` | Tableau paginé, recherche live, filtres (type / étiquette / actif-archivé), tri | EF-05, 09, 10 |
| `Contacts\Formulaire` | Création + édition (mêmes composant), champs conditionnels selon `type` | EF-01, 03, 06, 07 |
| `Contacts\FicheDetail` | Vue détail : coordonnées, personnes liées (si entreprise), emplacement historique activités/opportunités | EF-02, 08, 22 |
| `Contacts\GestionEtiquettes` | CRUD léger des étiquettes | EF-11 |
| `Contacts\ImportCsv` | Assistant : upload → mapping colonnes → récap/erreurs → confirmation | EF-17 à 20 |

- **Détection de doublons (EF-13)** : à la saisie de l'email (ou nom+entreprise), le
  composant `Formulaire` interroge en direct et affiche un **avertissement non
  bloquant**.
- **Archivage (EF-04)** : boutons *Archiver / Désarchiver / Supprimer* (suppression avec
  modale de confirmation).
- **Alpine.js + Tailwind** pour les interactions légères (modales, menus) et le style ;
  focus visible et navigation clavier (constitution art. 8).

---

## 5. Validation

- **Form Requests** (ou règles Livewire) pour tous les formulaires (constitution art. 6.1).
- **Champs obligatoires** : `nom` toujours ; conditionnels selon `type` (EF-14).
- **Email** : règle `email` de Laravel (EF-12).
- **Règle personnalisée `App\Rules\NumeroEntrepriseBe`** (EF-15) : 10 chiffres, format
  `0XXX.XXX.XXX` (préfixe 0 ou 1), **clé de contrôle modulo 97** (les 2 derniers chiffres
  = 97 − (les 8 premiers mod 97)). Normalise l'entrée (retire points/espaces) avant
  contrôle.
- **Règle personnalisée `App\Rules\NumeroTvaBe`** (EF-16) : `BE` + numéro d'entreprise
  valide ; si le `numero_entreprise` est aussi renseigné, cohérence vérifiée.
- Les deux numéros restent **facultatifs** mais validés s'ils sont présents.

---

## 6. Import CSV (EF-17 → EF-20)

- **Upload** d'un fichier CSV via le composant `ImportCsv` (stockage temporaire dans
  `storage/`, driver local — conforme Hostinger, constitution art. 3.2).
- **Lecture** avec la librairie standard `league/csv` (dépendance justifiée : parsing CSV
  robuste ; art. 2.6) — ou lecture native si suffisante. *À confirmer au moment du code.*
- **Mapping** : l'utilisateur associe chaque colonne du CSV à un champ (EF-18).
- **Prévisualisation** : récap (lignes valides / en erreur / doublons potentiels) avant
  toute écriture (EF-19).
- **Traitement** : les lignes valides sont insérées dans une **transaction** ; les lignes
  invalides sont collectées dans un rapport et ignorées, sans faire échouer l'ensemble
  (EF-20).
- Réutilise les **mêmes règles de validation** que le formulaire (§5) pour la cohérence.

---

## 7. Autorisations (Policies)

- **`ContactPolicy`** et **`EtiquettePolicy`**, même en usage solo (constitution art. 6.4)
  → garantit l'extensibilité multi-utilisateurs future sans refactorer.
- Au MVP : l'utilisateur authentifié a tous les droits ; les policies encapsulent cette
  règle pour pouvoir la restreindre plus tard.

---

## 8. Pages / routes

- `/contacts` — liste (`Contacts\Liste`)
- `/contacts/creer` et `/contacts/{contact}/modifier` — formulaire
- `/contacts/{contact}` — fiche détail
- `/contacts/import` — assistant d'import CSV
- `/etiquettes` — gestion des étiquettes

Toutes derrière le middleware `auth` (Breeze).

---

## 9. Plan de tests (Pest — constitution art. 5)

**Feature tests (parcours critiques) :**
1. Créer une personne, une entreprise → visibles en liste.
2. Rattacher une personne à une entreprise → la personne apparaît sur la fiche entreprise (EF-08).
3. Supprimer une entreprise ayant des personnes → les personnes subsistent, détachées (EF-04).
4. Rechercher / filtrer par type, étiquette, statut (EF-09/10).
5. Archiver puis désarchiver un contact (EF-04).
6. Import CSV : fichier avec lignes valides + invalides → valides importées, invalides
   rapportées et ignorées (EF-17→20).

**Tests unitaires (règles de gestion) :**
7. `NumeroEntrepriseBe` : cas valide + clé de contrôle fausse + mauvais format (EF-15).
8. `NumeroTvaBe` : valide + incohérent avec le numéro d'entreprise (EF-16).
9. Validation email et champs obligatoires, cas nominal + cas d'erreur (EF-12/14).
10. Détection de doublon signalée sans blocage (EF-13).

Objectif : chaque exigence fonctionnelle a au moins un test ; suite verte avant tout
déploiement (constitution art. 5.4).

---

## 10. Dépendances envisagées (à garder minimales, art. 2.6)

| Dépendance | Justification | Statut |
|---|---|---|
| `laravel/framework` 12.x | imposé | requis |
| `livewire/livewire` | interface TALL | requis |
| `laravel/breeze` (Livewire) | authentification | requis |
| `pestphp/pest` | tests | requis (dev) |
| `laravel/pint` | formatage PSR-12 | requis (dev) |
| `league/csv` | import CSV robuste | **à confirmer** au moment du code |

Aucune autre dépendance sans mise à jour de ce plan.

---

## 11. Vérification de conformité à la constitution

| Règle constitution | Respectée par |
|---|---|
| Art. 2 (stack Laravel/MySQL/TALL) | §1–4, §10 |
| Art. 3 (Hostinger, storage local, .env) | §6 |
| Art. 4 (PSR-12, conventions, français) | §3, §4, §10 |
| Art. 5 (tests Pest) | §9 |
| Art. 6 (validation, CSRF, Policies) | §5, §7 |
| Art. 7 (index, pagination, anti N+1) | §2, §3, §4 |
| Art. 8 (accessibilité, responsive) | §4 |

---

## 12. Étapes suivantes

Après validation de ce plan → **étape 3 (`/speckit.tasks`)** : découpage en tâches
concrètes et ordonnées (migrations → modèles → composants → validation → import →
tests), prêtes à être implémentées une par une.

---

## Historique

| Version | Date | Changement |
|---|---|---|
| 0.1.0 | 2026-09-07 | Plan technique initial du module Contacts. |

# Document de reprise — CRM « Laurence B. »

> À lire en premier par toute personne (ou IA) qui reprend ce projet.
> Ce fichier décrit **ce qu'est l'application, comment elle est faite, comment la lancer,
> et ce qu'il reste à faire**. Il se lit seul, sans contexte extérieur.

---

## 1. En une phrase

Un **CRM web personnel** (gestion de la relation client) pour un usage **solo**, en **Belgique**,
destiné à **remplacer Odoo** pour le suivi des contacts, des opportunités et des activités.
**La facturation reste sur Odoo** (obligations Peppol belges) — ce CRM ne facture jamais.

Nom de marque : **« Laurence B. »**. Langue de l'interface : **français**.

---

## 2. Méthode de travail (importante)

Le projet suit la méthode **Spec Kit** (développement piloté par la spécification). Pour **chaque
fonctionnalité**, on passe par les étapes, et les documents sont versionnés dans `specs/` :

```
constitution → specify (spec.md) → plan (plan.md) → tasks (tasks.md) → implement
```

- `constitution.md` (racine) = les règles non négociables du projet (stack, conventions, sécurité…).
- `specs/00X-*/` = un dossier par fonctionnalité, contenant `spec.md`, `plan.md`, `tasks.md`
  (et parfois `checklist.md`, `analyze.md`).

👉 **Pour ajouter une fonctionnalité, reprendre cette méthode** : écrire la spec, puis le plan,
puis les tâches, puis implémenter. Ne pas coder « à l'arrache » sans spec.

Le code **métier** (noms de classes, méthodes, variables du domaine) est **en français**
(ex. `Contact`, `Opportunite`, `estEntreprise()`, `temperatureInfo()`). L'interface aussi.

---

## 3. Stack technique

| Élément | Choix | Remarque |
|---|---|---|
| Framework | **Laravel 13** (`^13.17`) | PHP |
| **Version PHP** | **8.4+ obligatoire** | ⚠️ Les dépendances verrouillées (Symfony 8.1) exigent **PHP ≥ 8.4.1**. Le `composer.json` indique `^8.3` mais en pratique **8.4 minimum**. |
| Front | **TALL stack** : Tailwind CSS v3 + Alpine.js + Laravel + Livewire | Composants Livewire en classes |
| Auth | **Laravel Breeze** (stack Livewire) | + route `logout` ajoutée manuellement |
| Volt | `livewire/volt` | utilisé par Breeze |
| Tests | **Pest** | 53 tests, tous verts |
| Formatage | **Laravel Pint** (PSR-12) | `vendor/bin/pint` |
| Build assets | **Vite** (`npm run build`) | |
| Base de données | **SQLite** en dev et sur l'hébergement de test ; **MySQL** visé en production | Les migrations sont agnostiques (compatibles les deux) |

### Pourquoi ces choix
- **Laravel sur Hostinger** retenu pour le coût (vs Vercel/Supabase jugés trop chers).
- **SQLite en dev** car l'environnement de dev n'a pas de serveur MySQL. En prod, passer à MySQL.

---

## 4. Lancer le projet en local

```sh
# Prérequis : PHP 8.4+, Composer, Node 22+
composer install
npm install
cp .env.example .env
php artisan key:generate

# Base SQLite + données de démonstration
touch database/database.sqlite          # si absente
php artisan migrate --seed               # ou : php artisan db:seed --class=DemoSeeder

# Lancer
npm run build         # ou `npm run dev` pour le hot-reload
php artisan serve
```

⚠️ Le `.env.example` est en anglais par défaut (`APP_LOCALE=en`). En local, mettre
`APP_LOCALE=fr` et `APP_FALLBACK_LOCALE=fr` (la config `config/app.php` est déjà en `fr`).

**Compte de démonstration** (créé par `DemoSeeder`) : `demo@crm.be` / `password`.

### Commandes utiles
```sh
php artisan test            # lancer les tests (Pest)
vendor/bin/pint             # formater le code (PSR-12)
```

---

## 5. Architecture & modèle de données

Tout le code applicatif vit dans `app/`. Les écrans sont des **composants Livewire**
(`app/Livewire/...`) rendus dans des vues Blade (`resources/views/livewire/...`).

### Modèles (`app/Models/`)

- **`Contact`** — modèle central, en **table unique** (comme `res.partner` d'Odoo). Un champ
  `type` discrimine `personne` / `entreprise`. Une personne peut être rattachée à une entreprise
  (`entreprise_id`). Contient aussi les champs « lead » : `source` et `temperature`.
  - Constantes : `TYPE_PERSONNE`, `TYPE_ENTREPRISE`, `SOURCES`, `TEMPERATURES`
    (chaque entrée = `[libellé, couleur]`).
  - Relations : `entreprise()`, `personnes()`, `etiquettes()` (pivot), `opportunites()`, `activites()`.
  - Scopes : `typePersonne`, `typeEntreprise`, `actifs`, `archives`, `recherche`, `leads`.
  - Helpers : `estEntreprise()`, `estPersonne()`, `estArchive()`, `estLead()`, `archiver()`,
    `desarchiver()`, `nomComplet()`, `initiales()`, `couleurAvatar()`, `sourceInfo()`,
    `temperatureInfo()`.
- **`Opportunite`** — pipeline commercial. Étapes : `nouveau`, `qualifie`, `proposition`,
  `gagne`, `perdu`. Scopes `enCours`/`gagnees`/`perdues`/`etape`, méthode `changerEtape()`
  (pose la `date_cloture`). Relation `activites()`.
- **`Activite`** — tâches/rendez-vous. Types : `appel`, `rdv`, `tache`, `email`, `note`.
  Scopes `aFaire`/`terminees`/`duJourOuEnRetard`, méthodes `basculer()`, `enRetard()`.
- **`Etiquette`** — étiquettes libres (nom + couleur), liées aux contacts en many-to-many
  (table pivot `contact_etiquette`).
- **`User`** — authentification (Breeze).

### Règles de validation belges (`app/Rules/`)
- **`NumeroEntrepriseBe`** — numéro d'entreprise BCE (validation modulo-97).
- **`NumeroTvaBe`** — numéro de TVA belge (modulo-97).

### Migrations (`database/migrations/`)
`users`, `cache`, `jobs`, `contacts`, `etiquettes`, `contact_etiquette`, `opportunites`,
`activites`, puis `add_lead_fields_to_contacts` (ajout `source` + `temperature`).

---

## 6. Modules livrés (écrans)

Tous protégés par authentification. Routes dans `routes/web.php`.

| Module | Composants Livewire | Rôle |
|---|---|---|
| **Tableau de bord** | `TableauBord` | Accueil : chiffres clés, pipeline, activités du jour. Route `/` y redirige. |
| **Contacts** | `Contacts\Liste`, `Contacts\Formulaire`, `Contacts\FicheDetail`, `Contacts\GestionEtiquettes`, `Contacts\ImportCsv` | Liste + recherche, création/édition, fiche détaillée, gestion des étiquettes, import CSV. |
| **Pipeline** | `Pipeline\Tableau`, `Pipeline\Formulaire` | Opportunités vue kanban par étape. |
| **Activités** | `Activites\Liste`, `Activites\Formulaire` | Tâches, appels, rendez-vous, rappels. |
| **Leads** | (champs `source`/`temperature` sur `Contact`) | Qualification des contacts entrants. |

### Les « étiquettes » affichées sur une fiche contact
Attention, ce qui ressemble à des étiquettes sur une fiche vient de **4 sources différentes** :
1. Le **type** (`Personne`/`Entreprise`) — badge.
2. La **température** (`🔥 Client chaud`…) — champ lead.
3. La **source** (`LinkedIn`…) — champ lead.
4. Les **vraies étiquettes** (ex. `Client`) — relation many-to-many, cochables dans le
   formulaire du contact ; création/suppression dans l'écran **Étiquettes**.
   (⚠️ Renommer/recolorer une étiquette existante n'est **pas encore** possible : voir §9.)

---

## 7. Identité visuelle / design

- Système de design validé : **« Or & Encre »**, style **neumorphique** (ombres douces),
  fond clair, thème **clair + sombre** (bascule via `window.lbToggleTheme()`, préférence
  mémorisée en `localStorage`).
- Fichiers : `design/design-system.md` (tokens, palette, règles) et `design/maquette.html`
  (maquette statique validée).
- CSS : `resources/css/app.css` — tokens en variables CSS (`:root`, `@media dark`,
  `[data-theme="dark"]`) + classes `.lb-*` (`.lb-shell`, `.lb-card`, `.lb-btn`, `.lb-badge`,
  `.lb-tag`, `.lb-field`, etc.).
- Logo réel de la cliente : `public/images/prana-vidya-logo-epais-transparent.png`.
- Polices : titres **Cormorant Garamond** (serif), corps **Figtree**.
- Layouts : `resources/views/layouts/app.blade.php` (coquille avec barre latérale),
  `layouts/guest.blade.php` (connexion).

---

## 8. Déploiement

### Hébergement de test actuel : Railway (gratuit)
- Déploiement **par Docker** : `Dockerfile` (base **`php:8.4-cli-bookworm`**, extensions
  `pdo_mysql zip intl gd bcmath`, Node 22, Composer ; `composer install --no-dev`, `npm ci`,
  `npm run build`) + `docker/entrypoint.sh` (clé d'app auto, SQLite, `migrate --force`,
  seed démo si `SEED_DEMO=true`, caches, `php artisan serve`).
- **Redéploiement automatique à chaque `git push`** sur la branche.
- `bootstrap/app.php` fait confiance au proxy (`trustProxies(at: '*')`) pour les URL HTTPS.
- URL de test (peut changer) : une adresse en `*.up.railway.app`.
- Guide pas-à-pas détaillé : **`DEPLOIEMENT.md`**.

### Variables d'environnement utiles (toutes optionnelles pour le test)
`APP_KEY` (à fixer pour garder les sessions), `APP_DEBUG`, `DB_CONNECTION` (`sqlite`|`mysql`),
`SEED_DEMO` (`true`/`false`), `APP_URL`.

### Production visée : Hostinger
VPS (même Dockerfile + MySQL) **ou** mutualisé (sans Docker, pointer le domaine sur `public/`,
créer une base MySQL, renseigner `.env`). Détails dans `DEPLOIEMENT.md`.

⚠️ **Données** : sur le test Railway, la base SQLite est **dans le conteneur** et peut se
réinitialiser à un redéploiement. Pour des données persistantes : brancher **MySQL**
(`DB_CONNECTION=mysql` + identifiants) — c'est la prochaine étape importante.

---

## 9. État actuel & pistes suivantes

### Fait
- ✅ Modules Contacts, Pipeline, Activités, Leads + import CSV + étiquettes.
- ✅ Design « Or & Encre » appliqué à tous les écrans, thème clair/sombre.
- ✅ 53 tests Pest verts. CI GitHub Actions (PHP 8.4 & 8.5).
- ✅ En ligne sur Railway (SQLite de test).

### À faire / idées
- 🔜 **Base MySQL persistante** (Railway puis Hostinger) pour de vraies données.
- 🔜 **Édition d'une étiquette** (renommer / changer la couleur) — aujourd'hui on ne peut que
  créer/supprimer.
- 🔜 Déploiement **Hostinger** définitif.
- 💡 Glisser-déposer (drag & drop) dans le kanban du pipeline.
- 💡 Toute nouvelle fonctionnalité → **repartir de la méthode Spec Kit** (§2).

---

## 10. Conventions & contraintes à respecter

- **Code métier et interface en français.**
- **Tests Pest** pour toute nouvelle logique ; **Pint** avant de committer.
- **La facturation ne doit jamais être implémentée ici** (elle reste sur Odoo / Peppol).
- **Ne jamais committer** `.env` ni de secrets.
- Migrations **compatibles SQLite et MySQL**.
- Développement sur la branche de travail courante ; ne pas pousser ailleurs sans accord.

---

## 11. Points d'entrée pour explorer le code

```
constitution.md                     # règles du projet
specs/                              # specs des fonctionnalités (spec/plan/tasks)
design/design-system.md            # système de design
routes/web.php                     # toutes les routes
app/Models/                        # Contact, Opportunite, Activite, Etiquette, User
app/Livewire/                      # tous les écrans (composants)
resources/views/livewire/          # vues Blade des composants
resources/css/app.css              # design system (classes .lb-*)
database/seeders/DemoSeeder.php    # jeu de données de démonstration
Dockerfile + docker/entrypoint.sh  # déploiement
DEPLOIEMENT.md                     # guide de mise en ligne
```

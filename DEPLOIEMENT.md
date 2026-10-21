# Mettre l'application en ligne

Ce dossier contient tout le nécessaire pour déployer l'application. Deux cas :

- **A. Test rapide** sur un hébergeur gratuit (**Railway**) — un vrai lien à cliquer, sans
  rien installer. Idéal pour essayer et montrer.
- **B. Production** sur **Hostinger** (voir la fin).

L'application se construit toute seule à partir du **`Dockerfile`** : installation de PHP,
des dépendances, compilation des assets, migrations et jeu de démonstration au démarrage.

---

## A. Test gratuit sur Railway (≈ 5 minutes, 4 étapes)

### 1. Créer un compte
- Va sur **https://railway.com** → **Login** → « Login with GitHub » (le plus simple).

### 2. Nouveau projet depuis GitHub
- Clique **New Project** → **Deploy from GitHub repo**.
- Autorise Railway à accéder à tes dépôts si demandé, puis choisis **`wordpresslaurence-ai/Essai-app`**.
- Dans les réglages du service, choisis la branche **`claude/crm-site-constitution-utfvnb`**.
- Railway détecte le **Dockerfile** et lance la construction automatiquement.

### 3. Générer l'adresse publique
- Ouvre le service → onglet **Settings** → section **Networking** → **Generate Domain**.
- Railway te donne une URL du type `https://essai-app-production.up.railway.app`.

### 4. (Recommandé) Fixer la clé d'application
- Onglet **Variables** → **New Variable** :
  - `APP_KEY` = *(voir ci-dessous pour l'obtenir)*
- Sans cette variable, l'app fonctionne quand même (une clé est générée à chaque démarrage),
  mais tu seras déconnecté à chaque redéploiement. La fixer évite ça.

**Obtenir une APP_KEY** : dis-moi « génère-moi une APP_KEY » et je t'en fournis une à coller.

C'est tout ! 🎉 Ouvre l'URL : tu arrives sur la page de connexion.
- **Compte de démonstration** : `demo@crm.be` / `password`
  (créé automatiquement, avec des contacts, opportunités, activités et leads d'exemple).

> 💡 **À chaque `git push`** sur la branche, Railway **redéploie tout seul** : tes nouvelles
> fonctionnalités apparaissent en ligne sans rien refaire.

### Bon à savoir (test gratuit)
- La base est en **SQLite dans le conteneur** : simple, mais les données **peuvent se
  réinitialiser** à chaque redéploiement. Parfait pour tester, pas pour de vraies données.
- Pour des données **persistantes** sur Railway : ajoute un service **MySQL** (New → Database →
  MySQL) puis, dans les Variables du service web, mets `DB_CONNECTION=mysql` et les
  identifiants MySQL fournis (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
  Dis-moi si tu veux, je te guide.

---

## Variables d'environnement utiles (toutes optionnelles pour le test)

| Variable | Rôle | Défaut |
|---|---|---|
| `APP_KEY` | Clé de chiffrement (à fixer pour garder les sessions) | générée au démarrage |
| `APP_DEBUG` | Afficher les erreurs détaillées (mets `true` pour débugger) | `false` |
| `DB_CONNECTION` | `sqlite` (test) ou `mysql` (persistant) | `sqlite` |
| `SEED_DEMO` | Recharger les données de démo au démarrage (`true`/`false`) | `true` |
| `APP_URL` | L'adresse publique de ton app | — |

---

## B. Production sur Hostinger — check-list de déploiement

Cette partie est la **check-list à suivre le jour où on passe en production**. Elle est pensée
pour que tes **vraies données soient en sécurité** (textes d'offre, contacts, opportunités…) :
une fois en ligne sur Hostinger, une mise à jour du code **n'efface jamais** la base.

### 🔒 L'essentiel à retenir sur tes données

Sur Hostinger, **le code et les données sont séparés** :

- Le **code** (les fichiers de l'app) est remplacé quand on met à jour.
- Les **données** (tes contacts, offres, liens, etc.) vivent dans une base **MySQL persistante**,
  qui n'est **pas** touchée par une mise à jour du code.

Trois garde-fous intégrés à l'application :

1. **Migrations additives** — les mises à jour de structure *ajoutent* des colonnes/tables,
   elles ne suppriment pas de données. On lance toujours `php artisan migrate --force`,
   **jamais** `migrate:fresh` ni `db:wipe` (qui, eux, videraient tout).
2. **Jeu de démo désactivé en prod** — on met `SEED_DEMO=false` pour ne pas injecter les
   contacts d'exemple sur tes vraies données. (Et même activé, le seeder utilise `firstOrCreate` :
   il ne réécrit pas l'existant.)
3. **Sauvegarde avant chaque grosse mise à jour** — voir le point 6 ci-dessous.

> ⚠️ Différence avec Railway (test gratuit) : là-bas la base est en **SQLite dans le conteneur**
> et peut se réinitialiser à chaque redéploiement. Sur Hostinger avec **MySQL**, ce n'est pas le cas.

---

### Deux options selon ton offre Hostinger

- **Hébergement mutualisé** (PHP/MySQL classique, l'offre la plus courante) → suis la
  check-list ci-dessous (déploiement **sans Docker**).
- **VPS Hostinger** → on peut installer Docker et lancer le même `Dockerfile` qu'en test,
  avec une base MySQL. Dis-le-moi et je te guide pour cette variante.

---

### Check-list — hébergement mutualisé (à faire ensemble)

**1. Prérequis côté Hostinger**
- [ ] Offre avec **PHP 8.4** (sélectionnable dans hPanel → *Avancé → Version PHP*).
- [ ] Pour la **lecture des e-mails Gmail** (assistant) : activer l'**extension PHP `imap`**
      dans hPanel (*PHP → Extensions*). C'est ce qui débloque la vraie lecture IMAP, impossible
      sur Railway.
- [ ] Une **base de données MySQL** créée dans hPanel (*Bases de données → MySQL*) : note bien
      le **nom de la base**, l'**utilisateur**, le **mot de passe** et l'**hôte** (souvent `localhost`).

**2. Envoyer le code**
- [ ] Déposer le code dans le dossier du site (via le *Gestionnaire de fichiers* hPanel, ou Git
      si l'offre le propose).
- [ ] Faire pointer le domaine sur le sous-dossier **`public/`** (racine web = `public/`).

**3. Configurer le `.env`** (fichier de réglages, **jamais** versionné)
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] `APP_KEY=` une **clé fixe** (demande-moi « génère-moi une APP_KEY »). Ne pas la changer
      ensuite, sinon les sessions et données chiffrées deviennent illisibles.
- [ ] `APP_URL=https://ton-domaine.be`
- [ ] `DB_CONNECTION=mysql`
- [ ] `DB_HOST=`, `DB_PORT=3306`, `DB_DATABASE=`, `DB_USERNAME=`, `DB_PASSWORD=` (ceux du point 1)
- [ ] `SEED_DEMO=false` **(important en prod)**
- [ ] `SESSION_DRIVER=database`

**4. Installer et préparer l'application** (via le terminal SSH Hostinger, ou on le fait ensemble)
- [ ] `composer install --no-dev --optimize-autoloader`
- [ ] `npm install && npm run build` (compile le CSS/JS — à refaire à chaque mise à jour du design)
- [ ] `php artisan migrate --force` (crée les tables **sans toucher aux données existantes**)
- [ ] `php artisan config:cache && php artisan route:cache && php artisan view:cache`
- [ ] `php artisan storage:link`

**5. Réglages à faire une fois en ligne (dans l'app, écran Réglages)**
- [ ] Renseigner l'**adresse Gmail** et le **mot de passe d'application** (pour la synchro e-mails).
- [ ] Vérifier les **offres et liens par catégorie** (prospection) et le **pitch**.

**6. 🛟 Avant chaque future mise à jour (routine de sécurité)**
- [ ] **Sauvegarder la base** : hPanel → *Bases de données → phpMyAdmin → Exporter* (ou la
      sauvegarde automatique Hostinger). Garde le fichier `.sql`.
- [ ] (Au besoin) **Exporter tes contacts newsletter** en CSV depuis l'app.
- [ ] Mettre à jour le code, puis relancer uniquement :
      `composer install --no-dev -o`, `npm run build`, `php artisan migrate --force`,
      puis les trois `*:cache`.
- [ ] **Ne jamais** lancer `php artisan migrate:fresh`, `migrate:refresh` ni `db:wipe` en prod.

---

### Variante VPS (Docker)

Sur un **VPS Hostinger**, on installe Docker et on lance le **même `Dockerfile`** qu'en test
(il enchaîne install, build des assets, `migrate --force` et caches tout seul). On fournit les
mêmes variables qu'au point 3 (`DB_CONNECTION=mysql` + identifiants, `APP_KEY` fixe,
`SEED_DEMO=false`, `APP_ENV=production`). Dis-moi si c'est ton cas et je détaille.

<!-- Redéploiement manuel : 2026-10-19T09:49Z -->

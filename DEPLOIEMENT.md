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

## B. Production sur Hostinger

Deux options selon ton offre Hostinger :

- **VPS Hostinger** : installe Docker et lance ce même `Dockerfile` (identique à Railway),
  avec une base **MySQL** (`DB_CONNECTION=mysql` + identifiants). Je te guiderai.
- **Hébergement mutualisé Hostinger** (PHP/MySQL classique) : on déploie **sans Docker** —
  on envoie le code, on fait pointer le domaine sur le dossier `public/`, on crée une base
  MySQL et on renseigne le `.env`. Étapes détaillées à faire ensemble le moment venu.

Dans les deux cas, pense à mettre `APP_ENV=production`, `APP_DEBUG=false`, une `APP_KEY` fixe,
et à faire tourner `php artisan migrate --force` (le `Dockerfile` le fait automatiquement).

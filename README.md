# CRM — mon application de gestion de contacts

Application CRM personnelle (remplacement d'Odoo pour l'usage courant), construite avec
**Laravel** et la stack **TALL** (Tailwind, Alpine, Laravel, Livewire). La facturation
reste sur Odoo (conformité Peppol en Belgique).

> 👋 **Tu débutes ?** Pas d'inquiétude : cette page explique tout, pas à pas.

## Ce que fait (fera) l'application

- **Contacts / Entreprises** — carnet de tes clients, prospects, fournisseurs
- **Pipeline commercial** — suivi de tes opportunités de vente *(à venir)*
- **Activités** — rappels, rendez-vous, tâches *(à venir)*

## Comment le projet est organisé

Ce projet suit une méthode « spec-driven » : on réfléchit **avant** de coder. Tout est
écrit dans des documents que tu peux relire :

| Document | À quoi ça sert |
|---|---|
| [`constitution.md`](./constitution.md) | Les **règles du jeu** du projet (stack, style, tests, sécurité). |
| [`specs/001-contacts/spec.md`](./specs/001-contacts/spec.md) | **Ce que** fait le module Contacts (fonctionnalités). |
| [`specs/001-contacts/plan.md`](./specs/001-contacts/plan.md) | **Comment** c'est construit techniquement. |
| [`specs/001-contacts/tasks.md`](./specs/001-contacts/tasks.md) | La **liste des tâches** de développement, cochées au fur et à mesure. |

## Démarrer l'application en local (sur ton ordinateur)

Il te faut **PHP 8.2+**, **Composer** et **Node.js** installés.

```bash
# 1. Installer les dépendances (une seule fois)
composer install
npm install

# 2. Préparer la configuration (une seule fois)
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate

# 3. Lancer l'application (à chaque fois que tu veux travailler)
composer run dev
```

Puis ouvre **http://localhost:8000** dans ton navigateur. Crée ton compte via la page
« S'inscrire » et connecte-toi. 🎉

> 💡 `composer run dev` démarre en même temps le serveur web, la file d'attente et la
> compilation automatique du style. Pour tout arrêter : `Ctrl + C`.

## Base de données

- **En local (développement)** : **SQLite**, un simple fichier
  (`database/database.sqlite`). Rien à installer, rien à configurer.
- **En production (Hostinger)** : **MySQL**. Il suffira de renseigner les identifiants
  MySQL dans le fichier `.env` — le code ne change pas.

## Lancer les tests

Le projet est couvert par des tests automatiques (avec **Pest**). Pour vérifier que tout
fonctionne :

```bash
./vendor/bin/pest
```

Pour vérifier le style du code (avec **Pint**) :

```bash
./vendor/bin/pint --test   # signale les écarts
./vendor/bin/pint          # corrige automatiquement
```

## Langue

L'interface est **entièrement en français** (fichiers de traduction dans `lang/`).

---

*Projet personnel — construit étape par étape avec la méthode Spec Kit.*

# Constitution du projet — CRM (remplacement d'Odoo)

> Ce document pose **les règles du jeu permanentes** du projet. Toute spécification,
> tout plan technique et toute ligne de code doivent le respecter. En cas de conflit,
> **la constitution prime**. On la modifie volontairement rarement, et chaque
> modification est datée dans l'historique en bas de page.
>
> - **Version :** 1.0.0
> - **Dernière mise à jour :** 2026-09-07
> - **Objectif du projet :** construire un CRM sur mesure, simple et maintenable en
>   solo, pour remplacer l'usage actuel d'Odoo.

---

## Article 1 — Périmètre et philosophie

1.1. **Le projet remplace Odoo pour un usage personnel/solo.** On ne cherche PAS à
recopier tout Odoo. On construit uniquement ce dont l'utilisateur a réellement besoin.

1.2. **MVP (première version livrable), et rien de plus :**
- **Contacts / Entreprises** — fiches personnes et sociétés, coordonnées, liens
  entre elles.
- **Pipeline commercial** — opportunités passant par des étapes (ex. *Nouveau →
  Qualifié → Proposition → Gagné / Perdu*), avec montant prévisionnel.
- **Tâches / Activités** — rappels, rendez-vous et journal d'activité rattachés à un
  contact ou une opportunité.

1.3. **La facturation reste sur Odoo — décision permanente.** La facturation
électronique **Peppol** est obligatoire en Belgique ; Odoo la gère déjà gratuitement
et en conformité. Le CRM **ne produit donc ni devis ni factures** et ne réimplémente
pas la comptabilité. Une éventuelle passerelle vers Odoo (export de contacts /
d'opportunités gagnées) pourra être étudiée plus tard, mais jamais une refonte de la
facturation dans ce projet.

1.4. **Hors périmètre du MVP** (à envisager plus tard, jamais avant que le MVP soit
stable) : facturation/comptabilité (voir 1.3, restent sur Odoo), stock, e-commerce,
marketing automation, multi-société.

1.5. **Principe directeur : simplicité d'abord.** Face à deux solutions, on choisit
la plus simple à comprendre et à maintenir par une seule personne. On n'ajoute pas
d'abstraction, de dépendance ou de fonctionnalité « au cas où ».

---

## Article 2 — Stack technique imposée

2.1. **Langage & framework :** **PHP 8.2+** et **Laravel** (dernière version stable,
12.x). Aucun autre framework backend.

2.2. **Base de données :** **MySQL / MariaDB** (compatible avec l'hébergement
Hostinger). Tout accès aux données passe par **Eloquent** (l'ORM de Laravel) et par
des **migrations** versionnées — jamais de SQL écrit à la main pour modifier le
schéma.

2.3. **Interface :** stack **TALL** — **Blade + Livewire + Alpine.js + Tailwind CSS**.
Pas de SPA séparée (React/Vue), pas d'API REST/GraphQL tant que le MVP n'a qu'un seul
utilisateur. L'interface est rendue côté serveur.

2.4. **Authentification :** **Laravel Breeze** (starter kit officiel, version
Livewire). Simple, mais la structure des rôles/permissions doit rester **extensible**
pour ajouter plusieurs utilisateurs plus tard sans tout réécrire.

2.5. **Build des assets :** **Vite** (fourni par Laravel). Les assets compilés sont
générés en local puis déployés ; l'hébergement mutualisé Hostinger n'a pas besoin de
faire tourner Node.js.

2.6. **Gestion des dépendances :** **Composer** (PHP) et **npm** (assets front)
uniquement. Toute nouvelle dépendance doit être justifiée : préférer une fonction
native de Laravel à une librairie tierce.

2.7. **Toute dérogation à cette stack doit d'abord être inscrite dans cette
constitution** (nouvelle version) avant d'écrire le code correspondant.

---

## Article 3 — Hébergement et déploiement

3.1. **Cible d'hébergement : Hostinger.** Le code et sa configuration doivent rester
compatibles avec l'hébergement web Hostinger (PHP/MySQL, sans démon Node.js
permanent).

3.2. **Contraintes qui en découlent :**
- Pas de service qui exige un processus Node.js permanent en production.
- Les tâches planifiées reposent sur le **scheduler Laravel** déclenché par un **cron**
  Hostinger (une seule entrée cron).
- Les fichiers uploadés sont stockés sur le disque de l'hébergement (driver `local`),
  organisés via `storage/` et le lien symbolique `storage:link`.

3.3. **Secrets et configuration :** toutes les valeurs sensibles (identifiants base de
données, clés d'application, mots de passe SMTP) vivent dans le fichier `.env`, qui
**n'est jamais commité**. Le dépôt contient uniquement `.env.example` à jour.

3.4. **Aucun secret, identifiant ou donnée client réelle ne doit apparaître dans le
dépôt Git**, ni dans le code, ni dans les migrations, ni dans les seeders.

---

## Article 4 — Style de code et conventions

4.1. **Norme de style :** **PSR-12** pour le PHP. Le formatage est automatisé avec
**Laravel Pint** ; aucun code n'est fusionné s'il n'est pas « pint-clean ».

4.2. **Conventions Laravel respectées à la lettre :**
- Modèles au **singulier** (`Contact`, `Opportunite`, `Activite`).
- Tables au **pluriel** en `snake_case` (`contacts`, `opportunites`, `activites`).
- Contrôleurs suffixés `Controller`, composants Livewire dans `app/Livewire`.
- Une migration = un changement de schéma, nommée de façon descriptive.

4.3. **Langue :**
- **Code** (noms de variables, classes, méthodes, commentaires techniques) en
  **français** clair et cohérent, OU en anglais — mais **on choisit une seule langue
  et on s'y tient** dans tout le projet. Choix retenu : **français** pour le domaine
  métier (Contact, Opportunité, Activité), anglais toléré pour les termes techniques
  standards de Laravel.
- **Interface utilisateur** entièrement en **français**, via les fichiers de
  traduction Laravel (`lang/fr`) — jamais de texte en dur non traduisible.

4.4. **Lisibilité avant astuce.** Un code compréhensible par un développeur débutant
prime sur un code « malin ». Méthodes courtes, noms explicites, pas de sur-ingénierie.

4.5. **Commits :** messages clairs et concis décrivant le *pourquoi* du changement.
Un commit = une intention cohérente.

---

## Article 5 — Exigences de tests

5.1. **Framework de test :** **Pest** (au-dessus de PHPUnit), fourni avec Laravel.

5.2. **Ce qui DOIT être couvert par des tests avant d'être considéré « fini » :**
- Chaque **parcours métier critique** du MVP a au moins un *feature test* :
  création/édition d'un contact, déplacement d'une opportunité entre étapes,
  création et clôture d'une tâche.
- Toute **règle de gestion** (validation, calcul de montant prévisionnel, transitions
  d'étape autorisées) a un test qui vérifie le cas nominal **et** au moins un cas
  d'erreur.

5.3. **Règle de non-régression :** un bug corrigé donne d'abord lieu à un test qui
échoue, puis à la correction qui le fait passer.

5.4. **La suite de tests doit passer intégralement** avant tout déploiement. Aucun
test n'est désactivé ou ignoré pour « faire passer » le build ; un test qui gêne est
corrigé, pas supprimé.

---

## Article 6 — Sécurité

6.1. **Validation systématique** de toute donnée entrante via les *Form Requests* /
règles de validation Laravel. Aucune donnée utilisateur n'atteint la base sans
validation.

6.2. **Protections natives Laravel toujours actives :** protection CSRF sur tous les
formulaires, échappement automatique Blade contre le XSS, requêtes préparées via
Eloquent contre les injections SQL. On ne contourne jamais ces protections
(`{!! !!}`, requêtes brutes) sans justification explicite et échappement manuel.

6.3. **Mots de passe** hachés via le hachage natif de Laravel (bcrypt/argon). Jamais
de stockage en clair, jamais de hachage maison.

6.4. **Autorisations :** l'accès aux données passe par des **Policies** Laravel, même
en solo — cela garantit que l'ajout futur d'utilisateurs multiples ne crée pas de
faille.

6.5. **HTTPS obligatoire** en production. Les cookies de session sont marqués
`secure` et `httpOnly`.

6.6. **Dépendances à jour :** on applique les correctifs de sécurité de Laravel et des
paquets Composer sans laisser traîner les versions vulnérables.

---

## Article 7 — Performance

7.1. **Cible :** l'application reste fluide pour un usage solo à quelques milliers de
contacts/opportunités sur l'hébergement mutualisé Hostinger.

7.2. **Règles anti-lenteur :**
- Pas de requête N+1 : on utilise le *eager loading* (`with()`) dès qu'on affiche des
  relations dans une liste.
- Les listes sont **paginées** (jamais un `all()` qui charge toute une table dans une
  vue).
- Index de base de données posés sur les colonnes de recherche et de tri fréquentes.

7.3. On n'optimise pas prématurément : on mesure d'abord (temps de réponse, requêtes)
avant d'ajouter du cache ou de la complexité.

---

## Article 8 — Accessibilité et ergonomie

8.1. **HTML sémantique** (vrais `<button>`, `<label>` associés aux champs, structure
de titres cohérente). Tailwind ne dispense pas d'un balisage correct.

8.2. **Navigable au clavier** : tous les éléments interactifs sont atteignables et
utilisables sans souris ; focus visible.

8.3. **Contrastes de couleur** suffisants (viser le niveau **WCAG AA**).

8.4. **Interface responsive** : utilisable confortablement sur ordinateur portable et
sur mobile.

8.5. **Messages clairs** : erreurs de formulaire explicites en français, confirmations
d'action, états de chargement visibles.

---

## Article 9 — Gouvernance

9.1. **Cette constitution est la référence supérieure du projet.** Toute spécification
(`/specify`), tout plan (`/plan`) et toute implémentation doit y être conforme.

9.2. **Processus de modification :** une règle ne se change pas au milieu du code. On
modifie d'abord ce document, on incrémente la version (voir 9.3), on note le changement
dans l'historique, **puis** on adapte le code.

9.3. **Versionnage sémantique de la constitution :**
- **MAJEUR** : suppression ou remplacement d'un principe imposé (ex. changement de
  stack).
- **MINEUR** : ajout d'un nouveau principe ou d'une nouvelle contrainte.
- **CORRECTIF** : clarification, reformulation, correction sans changement de fond.

9.4. **En cas de doute**, on tranche toujours dans le sens de : *plus simple, plus sûr,
plus facile à maintenir en solo.*

---

## Historique des versions

| Version | Date       | Changement                                             |
|---------|------------|--------------------------------------------------------|
| 1.0.0   | 2026-09-07 | Version initiale : stack Laravel/Hostinger, MVP CRM (contacts, pipeline, activités), règles de style, tests, sécurité, perf, accessibilité. |

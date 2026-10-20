# Spec — Brique 2 : Pôles & Catégories (organisation configurable)

> Étape **specify** (Spec Kit). Suite logique du cadrage `specs/005-assistant-ia/cadrage.md` (§5.bis).
> Langue : français. Statut : **à valider** avant `plan.md`.

---

## 1. Objectif

Organiser le CRM en **deux niveaux que Laurence gère elle-même** :

```
PÔLE (ex. Prana, Digital)   →   CATÉGORIE (ex. Formation profs, Recherche de salle, Sites web…)
```

Et pouvoir **filtrer** contacts et pipeline par Pôle → Catégorie (des « onglets par type »).

## 2. Exigence clé : extensibilité par l'utilisatrice

- Laurence peut **créer / renommer / supprimer** des **Pôles** ET des **Catégories**
  **à tout moment**, depuis un écran dédié — notamment quand elle lance une **nouvelle offre ou
  un nouveau produit** (ex. un nouveau type d'atelier, une nouvelle prestation).
- Une nouvelle Catégorie (ou un nouveau Pôle) apparaît **immédiatement** comme onglet de filtre.
- **Aucune intervention de développement** nécessaire pour ajouter une « section ».

## 3. Périmètre

**Inclus**
- Écran **« Pôles & Catégories »** : gérer pôles et catégories (nom + couleur), les catégories
  étant rattachées à un pôle.
- Un champ **Catégorie** sur les **contacts** et sur les **opportunités** (le Pôle est déduit de
  la catégorie). Champ **facultatif**.
- **Filtres Pôle → Catégorie** en haut de la **liste des contacts** et du **tableau Pipeline**.
- **Pré-remplissage** des pôles/catégories connus + affectation des données de démo.

**Exclus (briques suivantes)**
- Étapes de pipeline **différentes par catégorie** (pour l'instant, mêmes étapes partout).
- **Sessions & listes d'attente** (promos de 10, journées datées avec places).
- Taxonomie à 3 niveaux, droits multi-utilisateurs.

## 4. Vocabulaire

- **Pôle** : grand domaine d'activité (Prana, Digital…).
- **Catégorie** : subdivision d'un pôle (Formation profs, Journée aura, Recherche de salle,
  Sites web, PDF interactifs…). *(On évite « Activité », déjà utilisé pour les tâches/RDV.)*

## 5. Récits utilisateur

- **US-1** : je crée un **Pôle** (nom + couleur) et, dessous, des **Catégories**.
- **US-2** : quand je lance une **nouvelle offre**, j'ajoute une **Catégorie** (ou un **Pôle**)
  en 2 clics ; elle devient filtrable aussitôt.
- **US-3** : sur un **contact** / une **opportunité**, je choisis sa **Catégorie**.
- **US-4** : sur **Contacts** et sur **Pipeline**, je filtre par **Pôle**, puis par **Catégorie**,
  pour ne voir que le domaine qui m'intéresse.
- **US-5** : je peux **renommer** ou **supprimer** un pôle/une catégorie ; les contacts/opportunités
  concernés sont simplement **détachés** (jamais supprimés).

## 6. Règles fonctionnelles

- **RF-1** : une **Catégorie** appartient à **un Pôle**. Le **Pôle d'un contact/opportunité** est
  celui de sa catégorie.
- **RF-2** : Catégorie **facultative** (un contact peut ne pas en avoir → « Sans catégorie »).
- **RF-3** : suppression d'un pôle → ses catégories et les affectations sont **détachées**
  (contacts/opportunités conservés). Suppression d'une catégorie → affectations détachées.
- **RF-4** : les **filtres** affichent les pôles, puis les catégories du pôle choisi ; un choix
  « Tous » affiche tout. Le filtre actif est visible.
- **RF-5** : création d'une opportunité **pré-remplit** la catégorie avec celle du contact lié.
- **RF-6** : noms de pôle **uniques** ; noms de catégorie **uniques au sein d'un pôle**.

## 7. Données (nouveaux éléments)

- **Table `poles`** : `nom` (unique), `couleur`, `ordre`, timestamps.
- **Table `categories`** : `pole_id` (FK, cascade à la suppression du pôle), `nom`, `couleur`,
  `ordre`, timestamps ; unicité (`pole_id`, `nom`).
- **`categorie_id`** (FK nullable, `nullOnDelete`) ajouté à **`contacts`** et à **`opportunites`**.

## 8. Écrans

- **« Pôles & Catégories »** (composant Livewire, style « Or & Encre ») : liste des pôles avec
  leurs catégories ; ajout/renommage/suppression ; choix de couleur. Accessible depuis la barre
  de filtres (« Gérer ») et/ou une entrée de menu.
- **Contacts/Formulaire** et **Pipeline/Formulaire** : un **sélecteur de Catégorie** (groupé par
  pôle).
- **Contacts/Liste** et **Pipeline/Tableau** : **barre de filtres** Pôle → Catégorie.
- **Fiche contact** : afficher le pôle/catégorie en badge.

## 9. Pré-remplissage (seed)

- Pôle **Prana** → *Formation profs de yoga*, *Journée aura & chakras*, *Recherche de salle*.
- Pôle **Digital** → *Sites web*, *PDF interactifs*.
- Affecter quelques contacts/opportunités de démo à ces catégories.

## 10. Critères d'acceptation

1. Je crée un **Pôle** et des **Catégories** ; ils apparaissent comme **filtres** sur Contacts et Pipeline.
2. J'affecte une **Catégorie** à un contact et à une opportunité ; le **Pôle** s'en déduit.
3. Le **filtre** Pôle → Catégorie restreint correctement les listes.
4. **Renommer/supprimer** un pôle/catégorie fonctionne sans supprimer de contacts/opportunités.
5. Les **tests** (Pest) passent ; code **formaté** (Pint) ; CI verte (PHP 8.4 & 8.5).

## 11. Découpage pressenti (tasks)

- **T1 ✅** — Données : tables `poles`, `categories` + `categorie_id` sur contacts/opportunités + modèles + tests. *(CI verte, commit 2ed361d)*
- **T2 ✅** — Écran **Pôles & Catégories** (gestion CRUD + couleurs) + tests. *(CI verte, commit f19ceab)*
- **T3 ✅** — **Sélecteur de Catégorie** dans les formulaires contact & opportunité + tests. *(CI verte, commit 018ca8e)*
- **T4 ✅** — **Filtres** Pôle → Catégorie sur Contacts et Pipeline + tests. *(CI verte, commit 1efcf24)*
- **T5 ✅** — Badge sur la fiche + **renommage** (pôles/catégories) + **seed** (pôles/catégories + démo) + docs.

---

*Prochaine étape (après validation) : `plan.md` puis implémentation tâche par tâche, avec
validation CI à chaque étape (comme pour la brique 1).*

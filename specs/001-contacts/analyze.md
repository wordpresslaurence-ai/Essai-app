# Analyse de cohérence — Module Contacts / Entreprises

> **Étape 3bis (Spec Kit) — cohérence spec ↔ plan ↔ tasks.**
> Rattrape les incohérences **avant** de coder : chaque exigence de la spec doit être
> couverte par le plan et par au moins une tâche ; aucune tâche ne doit sortir du
> périmètre.
>
> - **ID :** 001-contacts
> - **Date :** 2026-09-07

---

## 1. Matrice de traçabilité (exigence → plan → tâche)

| Exigence (spec) | Couverte dans le plan | Tâche(s) |
|---|---|---|
| EF-01 créer personne/entreprise | §2, §4 (Formulaire) | T010, T020, T051 |
| EF-02 consulter fiche | §4 (FicheDetail) | T053 |
| EF-03 modifier | §4 (Formulaire) | T051 |
| EF-04 archiver/désarchiver/supprimer + détachement | §2 (FK set null, archived_at) | T010, T054 |
| EF-05 liste paginée triable | §2 (index), §4 (Liste) | T021, T050 |
| EF-06 champs personne | §2 (table contacts) | T010, T051 |
| EF-07 champs entreprise (BCE, TVA…) | §2 (table contacts) | T010, T051 |
| EF-08 personnes d'une entreprise | §3 (relation personnes) | T020, T053 |
| EF-08b une seule entreprise/personne | §3 (colonne entreprise_id) | T010, T020 |
| EF-09 recherche | §3 (scopeRecherche) | T021, T050 |
| EF-10 filtres type/étiquette/statut | §3 (scopes), §4 (Liste) | T021, T050 |
| EF-11 étiquettes N–N | §2 (pivot), §3 | T011, T012, T022, T055 |
| EF-12 email valide | §5 | T051 |
| EF-13 doublons signalés | §4 (Formulaire) | T052 |
| EF-14 champs obligatoires | §5 | T051 |
| EF-15 validation BCE (modulo 97) | §5 (NumeroEntrepriseBe) | T030, T031 |
| EF-16 validation TVA | §5 (NumeroTvaBe) | T032, T033 |
| EF-17 import CSV | §6 | T060 |
| EF-18 mapping colonnes | §6 | T061 |
| EF-19 récap avant écriture | §6 | T062 |
| EF-20 lignes invalides ignorées + rapport | §6 | T063 |
| EF-21 dates création/modif | §2 (timestamps) | T010 |
| EF-22 emplacement historique | §4 (FicheDetail) | T053 |

**Résultat : 100 % des exigences (EF-01 → EF-22) sont couvertes par le plan ET par au
moins une tâche.** Aucune exigence orpheline.

## 2. Vérification inverse (aucune tâche hors périmètre)

Chaque tâche de `tasks.md` retombe sur une exigence de la spec ou une règle de la
constitution (fondations Phase 0, tests, style). **Aucune tâche ne crée de
fonctionnalité non spécifiée** (pas de sur-ingénierie — conforme art. 1.5).

## 3. Incohérences détectées et corrigées

| # | Point | Décision |
|---|---|---|
| 1 | La spec/plan mentionnait **Laravel 12** ; l'environnement a installé **Laravel 13** (dernière stable). | Corrigé : constitution v1.1, tasks T001. Conforme à « dernière version stable ». |
| 2 | Le plan citait **MySQL** ; en dev on utilise **SQLite**. | Clarifié : constitution art. 2.2 (SQLite dev / MySQL prod, migrations agnostiques). Aucune incohérence de fond. |
| 3 | Dépendance **`league/csv`** marquée « à confirmer » (Phase 6). | Laissée ouverte volontairement : décision au moment de coder l'import, sans bloquer les Phases 1–5. |

## 4. Risques résiduels (surveillés, non bloquants)

- **Import CSV** (Phase 6) : format des fichiers Odoo exportés à vérifier avec un vrai
  export au moment venu (encodage, séparateur, noms de colonnes).
- **Numéro BCE/TVA** : la validation par clé de contrôle est stricte ; prévoir un
  message d'aide clair si l'utilisateur saisit un format inhabituel.
- **Historique activités/opportunités** (EF-22) : l'emplacement est réservé mais son
  contenu dépend des futurs modules Pipeline et Activités.

---

## Verdict

✅ **Cohérence spec ↔ plan ↔ tasks validée.** Traçabilité complète, aucune exigence
orpheline, aucune tâche hors périmètre, incohérences mineures corrigées.

**Feu vert pour poursuivre l'implémentation (Phase 1 et suivantes).**

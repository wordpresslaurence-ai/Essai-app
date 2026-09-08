# Tâches — Module Activités

> **Étape 3 (Spec Kit).** Conforme au [plan](./plan.md). `[P]` = parallélisable.
> - **ID :** 003-activites · **Statut :** terminé

- [x] **T01** — Migration `activites` (champs + FK contact/opportunité nullOnDelete + index type). *(EF-01)*
- [x] **T02** — Modèle `Activite` (types, relations, scopes, helpers basculer/enRetard). *(EF-02,04,05)*
- [x] **T03** [P] — Relations `activites()` sur Contact et Opportunite + factory.
- [x] **T04** [P] — `ActivitePolicy`.
- [x] **T05** — Composant `Activites\Liste` (filtre, ajout rapide, cocher, supprimer). *(EF-03,04,06)*
- [x] **T06** — Composant `Activites\Formulaire` (créer/éditer/supprimer). *(EF-06)*
- [x] **T07** — Routes `/activites*` + lien sidebar « Activités ».
- [x] **T08** — Tableau de bord : section « Mes tâches du jour » cochables. *(EF-07)*
- [x] **T09** — Fiche contact : activités du contact + opportunités. *(EF-08)*
- [x] **T10** — Tests Pest + seeder démo enrichi.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Découpage initial. |

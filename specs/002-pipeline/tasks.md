# Tâches — Module Pipeline

> **Étape 3 (Spec Kit).** Conforme au [plan](./plan.md). `[P]` = parallélisable.
>
> - **ID :** 002-pipeline · **Statut :** terminé

- [x] **T01** — Migration `opportunites` (champs + FK contact nullOnDelete + index étape). *(EF-01)*
- [x] **T02** — Modèle `Opportunite` (constantes étapes, relation contact, scopes, helpers, changerEtape). *(EF-02,08)*
- [x] **T03** [P] — Relation `opportunites()` sur `Contact` + factory `OpportuniteFactory`.
- [x] **T04** [P] — `OpportunitePolicy`.
- [x] **T05** — Composant `Pipeline\Tableau` (kanban : colonnes, compte + total, changement d'étape, bascule perdus). *(EF-03,04,05,06)*
- [x] **T06** — Composant `Pipeline\Formulaire` (créer/éditer/supprimer). *(EF-07)*
- [x] **T07** — Routes `/pipeline`, `/pipeline/creer`, `/pipeline/{opportunite}/modifier` + lien sidebar « Pipeline ».
- [x] **T08** — Tableau de bord enrichi (chiffres pipeline + aperçu par étape). *(EF-10)*
- [x] **T09** — Tests Pest (création, changement d'étape + date clôture, totaux, perdus masqués, suppression). *(critères §7)*
- [x] **T10** — Style conforme (colonnes en creux, cartes en relief) + `db:seed` démo enrichi.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Découpage initial. |

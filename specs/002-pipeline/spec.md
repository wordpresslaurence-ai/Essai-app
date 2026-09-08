# Spécification — Module Pipeline commercial

> **Étape 1 (Spec Kit) — le QUOI.** Conforme à la [constitution](../../constitution.md) et
> au [design system](../../design/design-system.md).
>
> - **ID :** 002-pipeline · **Statut :** validée · **Version :** 1.0.0 · **Date :** 2026-09-08

## 1. Objectif
Suivre les **opportunités commerciales** (deals) de leur ouverture jusqu'à la conclusion,
visualisées sous forme de **tableau kanban** par étape. S'appuie sur le module Contacts.

## 2. Personas
- **L'utilisateur (solo)** : crée des opportunités liées à un contact, les fait avancer
  d'étape en étape, voit d'un coup d'œil son chiffre en cours et gagné.

## 3. Scénarios (priorisés)
- **P1** — Créer une opportunité (titre, contact, montant) et la voir dans la colonne « Nouveau ».
- **P2** — Faire avancer une opportunité d'une étape à l'autre (jusqu'à Gagné ou Perdu).
- **P3** — Voir, par étape, le nombre d'opportunités et le montant total.
- **P4** — Modifier / supprimer une opportunité ; consulter les opportunités perdues à part.

## 4. Exigences fonctionnelles
- **EF-01** — Une opportunité possède : **titre** (obligatoire), **contact** lié (facultatif),
  **montant** (€, facultatif), **étape**, **notes**, **date de clôture** (auto à Gagné/Perdu).
- **EF-02** — Les **étapes** sont : *Nouveau → Qualifié → Proposition → Gagné* et *Perdu*.
- **EF-03** — Un **tableau kanban** affiche une colonne par étape active (Nouveau, Qualifié,
  Proposition, Gagné) avec les cartes d'opportunités.
- **EF-04** — Chaque carte montre **titre + contact + montant** et permet de **changer d'étape**.
- **EF-05** — Chaque colonne affiche le **nombre** d'opportunités et le **montant total**.
- **EF-06** — Les opportunités **Perdues** sont masquées du tableau par défaut, consultables
  via une bascule.
- **EF-07** — On peut **créer, modifier, supprimer** une opportunité.
- **EF-08** — Passer une opportunité à **Gagné** ou **Perdu** enregistre la **date de clôture**.
- **EF-09** — Depuis la fiche d'un contact, on voit ses opportunités (à venir ; on prévoit la place).
- **EF-10** — Le **tableau de bord** affiche un aperçu du pipeline (par étape) et les chiffres
  clés (opportunités en cours, gagnées, montant en cours).

## 5. Entités
- **Opportunité** — un deal, rattaché à un **Contact**, positionné sur une **Étape**.

## 6. Cas limites
- Opportunité **sans contact** ni montant → autorisée (brouillon).
- Contact supprimé → l'opportunité est conservée, simplement détachée (contact nul).
- Montant négatif → refusé.

## 7. Critères d'acceptation
1. Je crée une opportunité et elle apparaît dans « Nouveau ».
2. Je la fais passer à « Qualifié », « Proposition », « Gagné ».
3. Chaque colonne affiche le bon compte et le bon total.
4. Une opportunité passée à « Perdu » quitte le tableau et reste consultable.
5. Chaque parcours critique est couvert par un test (constitution art. 5).

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Spécification initiale du module Pipeline. |

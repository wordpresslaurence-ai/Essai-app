# Spécification — Module Activités (tâches & rappels)

> **Étape 1 (Spec Kit) — le QUOI.** Conforme à la [constitution](../../constitution.md) et au
> [design system](../../design/design-system.md).
>
> - **ID :** 003-activites · **Statut :** validée · **Version :** 1.0.0 · **Date :** 2026-09-08

## 1. Objectif
Suivre les **tâches, rappels et rendez-vous** de l'utilisateur, liés à un contact et/ou une
opportunité, et voir en un coup d'œil **ce qu'il y a à faire aujourd'hui**.

## 2. Personas
- **L'utilisateur (solo)** : note ce qu'il doit faire (rappeler, envoyer une proposition,
  rendez-vous), coche quand c'est fait, ne rate pas une échéance.

## 3. Scénarios (priorisés)
- **P1** — Créer une activité (titre, type, échéance, contact) et la voir dans « À faire ».
- **P2** — **Cocher** une activité comme terminée (et la décocher).
- **P3** — Voir les activités **du jour / en retard** sur le tableau de bord.
- **P4** — Voir les activités **d'un contact** sur sa fiche ; modifier / supprimer.

## 4. Exigences fonctionnelles
- **EF-01** — Une activité possède : **titre** (obligatoire), **type**, **contact** lié
  (facultatif), **opportunité** liée (facultatif), **échéance** (date/heure, facultative),
  **terminée** (oui/non), **notes**.
- **EF-02** — Les **types** sont : *Appel, Rendez-vous, Tâche, E-mail, Note*.
- **EF-03** — Liste des activités filtrable : **À faire**, **Terminées**, **Toutes** ;
  triée par échéance.
- **EF-04** — On peut **cocher/décocher** une activité comme terminée (EF-P2).
- **EF-05** — Une échéance **passée** et non terminée est signalée « en retard ».
- **EF-06** — Créer, modifier, supprimer une activité.
- **EF-07** — Le **tableau de bord** affiche les activités **à faire aujourd'hui ou en
  retard**, cochables directement.
- **EF-08** — La **fiche contact** affiche les activités et opportunités de ce contact.
- **EF-09** — Contact ou opportunité supprimé → l'activité est conservée (lien détaché).

## 5. Entités
- **Activité** — une tâche/rappel, rattachée à un **Contact** et/ou une **Opportunité**.

## 6. Cas limites
- Activité **sans échéance** → autorisée (n'apparaît pas dans « du jour »).
- Activité **sans contact** → autorisée.

## 7. Critères d'acceptation
1. Je crée une activité avec échéance aujourd'hui → visible dans « À faire » et sur le
   tableau de bord.
2. Je la coche → elle passe en « Terminées » et quitte le tableau de bord.
3. Une échéance d'hier non faite est marquée « en retard ».
4. La fiche d'un contact liste ses activités.
5. Chaque parcours critique est couvert par un test (constitution art. 5).

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Spécification initiale du module Activités. |

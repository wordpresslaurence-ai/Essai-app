# Spécification — Leads (source & température)

> **Étape 1 (Spec Kit) — le QUOI.** Conforme constitution + design system.
> - **ID :** 004-leads · **Statut :** validée · **Version :** 1.0.0 · **Date :** 2026-09-08

## 1. Objectif
Qualifier les **prospects entrants** : d'où ils viennent (**source**) et à quel point ils
sont chauds (**température**), pour prioriser le suivi. Un lead est un **contact** enrichi
de ces deux informations.

## 2. Scénarios (priorisés)
- **P1** — Renseigner la **source** et la **température** d'un contact.
- **P2** — Voir la **liste des leads** (contacts ayant une température) et filtrer les contacts par température.
- **P3** — Voir sur le **tableau de bord** les **derniers leads** avec source + température.
- **P4** — La source et la température apparaissent sur la **fiche** et les **cartes** de contact.

## 3. Exigences fonctionnelles
- **EF-01** — Un contact peut avoir une **source** : LinkedIn, Instagram, Facebook, E-mail,
  Site web, Téléphone, Autre (facultatif).
- **EF-02** — Un contact peut avoir une **température** : Client chaud, Intérêt élevé,
  Intérêt moyen, À qualifier (facultatif). Un contact avec une température est un **lead**.
- **EF-03** — Source et température sont **éditables** dans le formulaire de contact.
- **EF-04** — La **liste des contacts** peut être **filtrée par température** (dont
  « Leads uniquement »).
- **EF-05** — Le **tableau de bord** affiche les **derniers leads** (cartes avec source +
  température).
- **EF-06** — Source (pastille de couleur de la plateforme) et température (couleur selon
  le niveau) s'affichent sur la fiche et les cartes de contact.

## 4. Cas limites
- Contact **sans source ni température** → simple contact, pas un lead.
- Un lead peut être **converti** : il suffit de retirer la température ou d'ajuster (pas de
  workflow rigide au MVP).

## 5. Critères d'acceptation
1. Je renseigne source + température sur un contact → il apparaît comme lead.
2. Je filtre la liste sur « Client chaud » → seuls ces contacts s'affichent.
3. Le tableau de bord liste les derniers leads avec leurs badges.
4. Chaque parcours critique est couvert par un test.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Spécification initiale. |

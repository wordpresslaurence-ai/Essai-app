# Plan technique — Leads

> **Étape 2 (Spec Kit).** Étend le module Contacts (pas de nouvelle entité).
> - **ID :** 004-leads · **Version :** 1.0.0

## 1. Migration — ajout à `contacts`
- `source` string nullable · `temperature` string nullable (indexée).

## 2. Modèle `Contact` (enrichi)
- Constantes + libellés **sources** (linkedin, instagram, facebook, email, site_web,
  telephone, autre) avec **couleur de pastille** (couleurs de marque).
- Constantes + libellés **températures** (chaud, eleve, moyen, a_qualifier) avec **couleur**
  (chaud→terra, eleve→mint, moyen→lav, a_qualifier→neutre).
- `scopeLeads` = whereNotNull('temperature'). Helper `estLead()`.
- Ajout de `source`, `temperature` au `$fillable`.

## 3. Formulaire contact
- Nouvelle section « Lead » : selects **Source** et **Température** (facultatifs), pour les
  deux types de contact.

## 4. Liste des contacts
- Filtre **température** (`#[Url] public $temperature`) : Toutes / Leads uniquement /
  Client chaud / Intérêt élevé / Intérêt moyen / À qualifier.
- Cartes : afficher la pastille **source** et le badge **température** quand présents.

## 5. Fiche contact
- Afficher source (avec pastille) et température (badge coloré) si présents.

## 6. Tableau de bord
- Section « Derniers leads » : `Contact::leads()->actifs()->latest()->take(4)` en cartes
  (avatar, nom, source, température).

## 7. Tests (Pest)
- Enregistrer source+température → estLead. Filtre par température. Dashboard voit un lead.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Plan initial. |

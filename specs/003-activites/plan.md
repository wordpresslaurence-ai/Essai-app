# Plan technique — Module Activités

> **Étape 2 (Spec Kit).** Conforme constitution (Laravel/MySQL/TALL/Pest) et design system.
> - **ID :** 003-activites · **Version :** 1.0.0

## 1. Migration `activites`
| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint auto | |
| `titre` | string | obligatoire |
| `type` | string, défaut `tache`, indexé | appel/rdv/tache/email/note |
| `contact_id` | bigint nullable, FK → contacts `nullOnDelete` | EF-01/09 |
| `opportunite_id` | bigint nullable, FK → opportunites `nullOnDelete` | EF-01/09 |
| `echeance` | datetime nullable | EF-01 |
| `terminee_at` | timestamp nullable | null = à faire ; date = terminée (EF-04) |
| `notes` | text nullable | |
| timestamps | | |

## 2. Modèle `Activite`
- Constantes de types + libellés + icône/couleur par type.
- Relations `contact()`, `opportunite()` (belongsTo).
- Scopes : `aFaire` (terminee_at null), `terminees`, `duJourOuEnRetard` (echeance <= fin de
  journée, à faire), `avenir`. Tri par échéance.
- Helpers : `estTerminee()`, `enRetard()`, `basculer()` (coche/décoche → terminee_at).
- Sur Contact et Opportunite : relation `activites()` hasMany.
- Factory.

## 3. Interface Livewire
- **`Activites\Liste`** (`/activites`) : filtre (À faire / Terminées / Toutes), **ajout
  rapide** (titre + type + échéance + contact), lignes avec **case à cocher**, badge de type,
  contact, échéance (rouge si en retard), liens éditer/supprimer.
- **`Activites\Formulaire`** (`/activites/creer`, `/activites/{activite}/modifier`) :
  titre, type, contact, opportunité, échéance (datetime-local), notes ; suppression.

## 4. Validation
- `titre` requis ; `type` in:... ; `contact_id`/`opportunite_id` nullable exists ;
  `echeance` nullable date.

## 5. Autorisations
- `ActivitePolicy` (droits complets en solo, extensible).

## 6. Tableau de bord (EF-07)
- Section « Mes tâches du jour » : `Activite::duJourOuEnRetard()` avec case à cocher.

## 7. Fiche contact (EF-08)
- Remplace le placeholder par la liste des activités du contact + ses opportunités.

## 8. Tests (Pest)
- Créer (échéance aujourd'hui) → À faire + tableau de bord. Cocher → Terminées. En retard.
  Fiche liste les activités. Suppression. Détachement à la suppression du contact.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Plan initial. |

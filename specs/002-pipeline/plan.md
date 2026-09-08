# Plan technique — Module Pipeline

> **Étape 2 (Spec Kit) — le COMMENT.** Conforme constitution (Laravel/MySQL/TALL/Pest) et
> design system (style neumorphique, colonnes en creux, cartes en relief).
>
> - **ID :** 002-pipeline · **Statut :** à implémenter · **Version :** 1.0.0

## 1. Modèle de données — migration `opportunites`
| Colonne | Type | Notes |
|---|---|---|
| `id` | bigint auto | |
| `titre` | string | obligatoire |
| `contact_id` | bigint nullable, FK → `contacts.id` `nullOnDelete` | client lié (EF-01, cas limite) |
| `montant` | decimal(12,2) nullable | € |
| `etape` | string, défaut `nouveau`, indexé | nouveau/qualifie/proposition/gagne/perdu |
| `notes` | text nullable | |
| `date_cloture` | date nullable | posée à Gagné/Perdu (EF-08) |
| timestamps | | |

## 2. Modèle `App\Models\Opportunite`
- Constantes d'étapes + libellés + ordre + couleur d'accent (mint/lav/gold/terra).
- Relation `contact()` → belongsTo(Contact).
- Scopes : `enCours` (ni gagné ni perdu), `gagnees`, `perdues`, `etape($e)`.
- Helpers : `estGagnee()`, `estPerdue()`, `estTerminee()`, `changerEtape($e)` (gère `date_cloture`).
- Sur Contact : relation `opportunites()` hasMany (EF-09).
- Factory `OpportuniteFactory`.

## 3. Interface Livewire
- **`Pipeline\Tableau`** (route `/pipeline`) : kanban. Colonnes = étapes actives (Nouveau,
  Qualifié, Proposition, Gagné), + bascule « voir les perdus » ajoutant une colonne Perdu.
  Chaque colonne : titre + compte + total (€) ; cartes en relief ; chaque carte a un
  `<select>` d'étape (`wire:change`) pour la déplacer (EF-04). *(Le glisser-déposer pourra
  être ajouté plus tard via SortableJS.)*
- **`Pipeline\Formulaire`** (routes `/pipeline/creer`, `/pipeline/{opportunite}/modifier`) :
  titre, contact (select), montant, étape, notes ; validation ; suppression avec modale.

## 4. Validation
- `titre` requis ; `montant` `nullable|numeric|min:0` ; `contact_id` `nullable|exists` ;
  `etape` `in:...`.

## 5. Autorisations
- `OpportunitePolicy` (droits complets en solo, extensible) — comme ContactPolicy.

## 6. Tableau de bord (EF-10)
- `TableauBord` enrichi : opportunités en cours (compte), gagnées (compte), montant en cours ;
  aperçu mini-pipeline (compte par étape active). Remplace le placeholder « à venir ».

## 7. Tests (Pest)
- Créer une opportunité → visible en « Nouveau ».
- Changer d'étape → déplacement + date de clôture à Gagné/Perdu.
- Totaux par colonne corrects.
- Perdu masqué du tableau par défaut, visible via bascule.
- Suppression.

## 8. Conformité design
Colonnes `.lb-inset`, cartes `.lb-card-sm`, montants en serif doré, pastilles d'étape,
boutons `.lb-btn`. Réutilise avatars/badges du design system.

## Historique
| Version | Date | Changement |
|---|---|---|
| 1.0.0 | 2026-09-08 | Plan initial. |

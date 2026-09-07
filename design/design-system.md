# Design System — CRM

> Document de référence pour l'apparence et l'ergonomie de l'application.
> Objectif : une interface **conviviale, claire et facile à prendre en main**, avec une
> vraie identité (fini le look Laravel par défaut). Reste conforme à la
> [constitution](../constitution.md) — notamment l'accessibilité (art. 8) et la stack
> Tailwind (art. 2.3).
>
> - **Statut :** proposition à valider
> - **Version :** 0.1.0 (brouillon)
> - **Date :** 2026-09-07

---

## 1. Principes de design

1. **Clarté avant décoration** — chaque écran a un but évident ; on réduit le bruit visuel.
2. **Convivialité** — arrondis doux, couleurs chaleureuses, messages humains en français.
3. **Rapidité de lecture** — l'info importante saute aux yeux (avatars, badges, hiérarchie).
4. **Cohérence** — les mêmes composants partout (un seul style de bouton, de carte, de badge).
5. **Accessible par défaut** — contrastes AA, navigation clavier, focus visible (art. 8).
6. **Léger** — que du Tailwind, pas de grosse librairie UI (art. 2.6).

---

## 2. Identité de marque *(à valider)*

- **Nom de l'application :** _à définir_ (proposition : un nom court et mémorisable —
  ex. « Carnet », « Relations », « Rolodex », ou ton propre nom de marque).
- **Logo :** un **monogramme** simple (1–2 lettres dans un carré arrondi coloré) généré en
  CSS/SVG — pas de fichier lourd. Remplace le logo Laravel partout.
- **Ton de voix :** amical et direct, tutoiement possible dans l'aide, phrases courtes.

---

## 3. Couleurs

Palette pensée pour le **mode clair et le mode sombre**. Les couleurs sont définies comme
**jetons** (variables) réutilisables. La couleur primaire est **à valider** (§ Questions).

### Proposition par défaut : « Émeraude & ardoise » (fraîche, professionnelle, chaleureuse)

| Rôle | Clair | Sombre | Usage |
|---|---|---|---|
| **Primaire** | `emerald-600 #059669` | `emerald-500 #10b981` | Boutons d'action, liens, éléments actifs |
| **Primaire (survol)** | `emerald-700` | `emerald-400` | État survol |
| **Fond de page** | `#f8fafc` (slate-50) | `#0f172a` (slate-900) | Arrière-plan général |
| **Surface (cartes)** | `#ffffff` | `#1e293b` (slate-800) | Cartes, tableaux |
| **Bordure** | `#e2e8f0` (slate-200) | `#334155` (slate-700) | Séparateurs, contours |
| **Texte principal** | `#0f172a` | `#f1f5f9` | Titres, contenu |
| **Texte secondaire** | `#64748b` (slate-500) | `#94a3b8` | Libellés, métadonnées |
| **Succès** | `#16a34a` | `#22c55e` | Confirmations |
| **Alerte** | `#d97706` | `#f59e0b` | Doublons, avertissements |
| **Erreur** | `#dc2626` | `#ef4444` | Erreurs, suppression |
| **Info** | `#2563eb` | `#3b82f6` | Informations neutres |

### Couleurs d'accent pour les avatars et étiquettes
Un jeu de 8 teintes douces (emerald, sky, violet, amber, rose, teal, indigo, orange),
attribuées automatiquement selon le nom, pour des **avatars à initiales** colorés et cohérents.

*(Alternatives de couleur primaire proposées dans les questions : Indigo, Bleu océan, Violet.)*

---

## 4. Typographie

- **Police :** `Figtree` (déjà chargée par Breeze) ou `Inter` — moderne, très lisible.
  Repli : `system-ui, sans-serif`.
- **Échelle :**
  - Titre de page : 24 px / 600 (semibold)
  - Sous-titre / section : 18 px / 600
  - Corps : 14 px / 400
  - Métadonnée / libellé : 12 px / 500, souvent en MAJUSCULES espacées pour les en-têtes
- **Interligne** confortable (1.5 pour le corps), pas de texte trop dense.

---

## 5. Espacements, rayons, ombres

- **Rayon des coins :** `rounded-lg` (8 px) pour cartes/champs, `rounded-full` pour badges/avatars.
  → arrondis doux = convivial.
- **Ombres :** légères (`shadow-sm`), jamais lourdes. Les cartes se distinguent par la surface + une ombre discrète.
- **Rythme d'espacement :** multiples de 4 px (gap-3 = 12 px, gap-6 = 24 px). Respiration entre les blocs.
- **Largeur de contenu :** listes et tableaux `max-w-7xl` ; formulaires et fiches `max-w-3xl/4xl` (lecture confortable).

---

## 6. Composants

### Boutons
- **Primaire :** fond couleur primaire, texte blanc, `rounded-lg`, `px-4 py-2`, survol plus foncé.
- **Secondaire :** fond surface, bordure, texte principal.
- **Danger :** contour/texte rouge, fond rouge plein seulement dans les confirmations.
- **Icône + texte** quand ça aide (ex. « + Nouveau contact »).
- Focus visible (anneau) sur tous.

### Champs de formulaire
- Libellé au-dessus, champ `rounded-lg`, bordure douce, anneau de focus coloré.
- Champ obligatoire marqué d'un `*`. Message d'erreur en rouge sous le champ.
- Formulaires **sectionnés** en cartes (Identité, Coordonnées, Adresse, Notes) plutôt qu'une longue liste.
- **Barre d'enregistrement** discrète en bas (Annuler / Enregistrer).

### Cartes
- Surface blanche/sombre, `rounded-lg`, `shadow-sm`, padding généreux (`p-6`).
- En-tête de carte optionnel (titre + action).

### Tableau (liste des contacts)
- Lignes aérées, survol qui surligne, en-têtes cliquables pour le tri (flèche).
- **Colonne Nom = avatar à initiales + nom** (+ e-mail en petit dessous sur mobile).
- **Type = badge coloré** (Personne / Entreprise) plutôt que du texte brut.
- **Étiquettes affichées** en petites pastilles.
- Sur mobile : le tableau devient une **liste de cartes** empilées.

### Badges & étiquettes
- `rounded-full`, `px-2 py-0.5`, `text-xs`, fond teinté clair + texte foncé de la même teinte.
- Type de contact, statut (archivé), et étiquettes utilisateur.

### Avatars
- Cercle avec **initiales** (1–2 lettres), couleur dérivée du nom. Icône « bâtiment » pour les entreprises.

### Navigation
- Barre supérieure avec **logo/monogramme + nom**, liens (Tableau de bord, Contacts, Étiquettes),
  menu utilisateur à droite, **bouton mode clair/sombre**.
- Lien actif souligné en couleur primaire.

### États vides
- Illustration légère (icône) + message encourageant + bouton d'action
  (« Aucun contact pour l'instant — créez le premier »).

### Notifications (toasts)
- Message flash en haut à droite, coloré selon le type (succès/erreur), disparaît tout seul.

---

## 7. Iconographie

- Jeu d'icônes **Heroicons** (SVG inline, léger, cohérent avec Tailwind ; pas de dépendance JS).
- Icônes discrètes : e-mail, téléphone, bâtiment, personne, étiquette, recherche, filtre, tri.
- E-mail et téléphone **cliquables** (`mailto:` / `tel:`) avec leur icône.

---

## 8. Mise en page & responsive

- **Desktop :** barre de nav en haut, contenu centré, tableaux larges.
- **Mobile :** menu repliable, tableaux → cartes, boutons pleine largeur, cibles tactiles ≥ 44 px.
- Le corps de page ne défile jamais horizontalement (contenu large dans un conteneur scrollable).

---

## 9. Accessibilité (rappel constitution art. 8)

- Contrastes **WCAG AA** vérifiés sur la palette.
- Tous les champs ont un `<label>` associé ; focus visible partout.
- Navigation complète au clavier ; modales fermables au clavier (Échap).
- Les couleurs ne portent jamais seules l'information (toujours doublées d'un texte/icône).

---

## 10. Inspirations

- **Attio / Linear** — épure, densité maîtrisée, typographie soignée.
- **Pipedrive / HubSpot** — convivialité, couleurs d'accent, pipeline visuel.
- **Notion** — approche douce, arrondie, accessible aux débutants.

On vise un croisement : **la propreté de Linear + la convivialité de Pipedrive**.

---

## 11. Améliorations concrètes, écran par écran

| Écran | Améliorations proposées |
|---|---|
| **Marque** | Remplacer le logo Laravel par un monogramme + le nom de l'app, partout. |
| **Tableau de bord** | Le transformer en **vrai accueil** : cartes de chiffres (total contacts, entreprises, personnes, archivés), derniers contacts ajoutés, raccourcis. |
| **Liste** | Avatars à initiales, badges de type, étiquettes visibles, e-mail cliquable, bandeau de compteurs, meilleur état vide. |
| **Fiche** | Avatar en tête, e-mail/téléphone cliquables avec icônes, sections mieux marquées, bouton « + Ajouter une personne » sur une entreprise. |
| **Formulaire** | Découpage en sections (cartes), barre d'enregistrement, sélection des **étiquettes** directement dans le formulaire. |
| **Connexion** | Nom de l'app + petit slogan, ambiance douce, monogramme. |
| **Global** | Bouton **mode sombre**, icônes Heroicons, toasts de confirmation. |

---

## 12. Fonctionnalités envisagées (backlog design)

**Améliorations rapides des contacts**
- Avatars à initiales · étiquettes dans la liste · e-mail/téléphone cliquables
- Export CSV · sélection multiple (archiver/étiqueter en lot)
- Épingler des contacts favoris · champ « dernier contact le… »

**Nouveaux modules**
- 🎯 **Pipeline** — opportunités par étapes (kanban glisser-déposer)
- ✅ **Activités & rappels** — tâches, rendez-vous, journal lié au contact
- 📊 **Tableau de bord** — chiffres clés et graphiques
- 📎 Pièces jointes par contact · ✉️ historique d'e-mails · 📅 agenda
- 🔁 Passerelle **Odoo** (export des contacts / opportunités gagnées)

---

## Historique

| Version | Date | Changement |
|---|---|---|
| 0.1.0 | 2026-09-07 | Brouillon initial du design system, en attente de validation. |

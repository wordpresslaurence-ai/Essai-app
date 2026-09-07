# Design System — CRM

> Document de référence pour l'apparence et l'ergonomie de l'application.
> Objectif : une interface **conviviale, claire et facile à prendre en main**, avec une
> vraie identité (fini le look Laravel par défaut). Reste conforme à la
> [constitution](../constitution.md) — notamment l'accessibilité (art. 8) et la stack
> Tailwind (art. 2.3).
>
> - **Statut :** validé (nom de marque en attente)
> - **Version :** 0.2.0
> - **Date :** 2026-09-07
>
> **Décisions validées avec l'utilisateur :**
> - Marque : **« Laurence B. »**, avec le **logo fourni** (silhouette élégante encre marine +
>   trait doré + accents vert d'eau et lavande).
> - Couleur primaire : **Or & Encre** — dérivée directement du logo (l'émeraude proposée au
>   départ est abandonnée au profit des couleurs réelles du logo, pour la cohérence de marque).
> - Modes : **clair ET sombre**, avec un bouton de bascule.
> - Priorités après le relooking (dans l'ordre) : **1. Tableau de bord d'accueil**,
>   **2. Améliorations liste/fiche**, **3. Module Pipeline**, **4. Module Activités**.

---

## 1. Principes de design

1. **Clarté avant décoration** — chaque écran a un but évident ; on réduit le bruit visuel.
2. **Convivialité** — arrondis doux, couleurs chaleureuses, messages humains en français.
3. **Rapidité de lecture** — l'info importante saute aux yeux (avatars, badges, hiérarchie).
4. **Cohérence** — les mêmes composants partout (un seul style de bouton, de carte, de badge).
5. **Accessible par défaut** — contrastes AA, navigation clavier, focus visible (art. 8).
6. **Léger** — que du Tailwind, pas de grosse librairie UI (art. 2.6).

---

## 2. Identité de marque

- **Nom de l'application :** **Laurence B.**
- **Logo :** le **logo fourni** par l'utilisateur — une silhouette stylisée tout en courbes,
  tracée à l'encre marine, avec un trait doré central et de fines touches vert d'eau et
  lavande. Univers **raffiné, apaisant, bien-être**. Il remplace le logo Laravel partout.
  - *Fichier à ajouter au dépôt (idéalement en SVG, sinon PNG à fond transparent) dans
    `public/images/logo.svg` pour un rendu net. En attendant, un monogramme « LB » or-sur-encre
    sert de repère dans la maquette.*
- **Ton de voix :** chaleureux, humain, élégant. Phrases courtes, bienveillantes.

---

## 3. Couleurs

Palette pensée pour le **mode clair et le mode sombre**. Les couleurs sont définies comme
**jetons** (variables) réutilisables. La couleur primaire est **à valider** (§ Questions).

### Palette « Or & Encre » — dérivée du logo Laurence B.

Couleurs extraites du logo : **encre marine** (structure), **or** (signature), **vert d'eau**
et **lavande** (accents doux).

> ⚠️ **Accessibilité :** l'or pur sur blanc n'a pas un contraste suffisant pour du petit texte.
> On utilise donc **l'encre marine comme couleur des boutons/actions** (contraste élevé) et
> **l'or comme accent** (état actif, filets, icônes, survols, petites touches). En mode sombre,
> l'or ressort pleinement sur le fond encre.

| Rôle | Clair | Sombre | Usage |
|---|---|---|---|
| **Encre (primaire action)** | `#2E2A47` | `#EDECF5` | Boutons principaux, texte de titre |
| **Or (accent/signature)** | `#B8901F` | `#D4AF37` | Liens actifs, filets, icônes clés, survols, focus |
| **Or clair (fond teinté)** | `#F5EAC9` | `#3A3320` | Puces, surlignage doux, badge « signature » |
| **Fond de page** | `#F7F6F2` (ivoire) | `#1A1830` (encre profonde) | Arrière-plan général |
| **Surface (cartes)** | `#FFFFFF` | `#26233F` | Cartes, tableaux |
| **Bordure** | `#E7E4DC` | `#39355A` | Séparateurs, contours |
| **Texte principal** | `#2E2A47` | `#F1F0F7` | Titres, contenu |
| **Texte secondaire** | `#6B677E` | `#A9A6C0` | Libellés, métadonnées |
| **Succès** | `#3F8F6B` (vert d'eau foncé) | `#5DBF98` | Confirmations |
| **Alerte** | `#B8901F` (or) | `#D4AF37` | Doublons, avertissements |
| **Erreur** | `#C0483B` (terracotta) | `#E27166` | Erreurs, suppression |
| **Info** | `#6B74B0` (lavande foncé) | `#9AA2D8` | Informations neutres |

### Couleurs d'accent pour les avatars et étiquettes
Un jeu de teintes douces **tirées du logo** — or, vert d'eau, lavande, encre, terracotta —
attribuées automatiquement selon le nom, pour des **avatars à initiales** élégants et cohérents.

---

## 4. Typographie

- **Police du corps :** `Figtree` (déjà chargée) ou `Inter` — moderne, très lisible.
  Repli : `system-ui, sans-serif`.
- **Police des titres (option raffinée) :** un **serif élégant** type `Cormorant Garamond`
  ou `Fraunces` pour les grands titres, en écho au caractère « bien-être » du logo. À
  confirmer dans la maquette (sinon on garde tout en sans-serif).
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
| 0.2.0 | 2026-09-07 | Décisions validées : marque « Laurence B. » + logo fourni ; palette « Or & Encre » dérivée du logo (remplace l'émeraude) ; modes clair + sombre ; titres serif en option ; priorités de roadmap. |

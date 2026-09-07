# Design System — CRM

> Document de référence pour l'apparence et l'ergonomie de l'application.
> Objectif : une interface **conviviale, claire et facile à prendre en main**, avec une
> vraie identité (fini le look Laravel par défaut). Reste conforme à la
> [constitution](../constitution.md) — notamment l'accessibilité (art. 8) et la stack
> Tailwind (art. 2.3).
>
> - **Statut :** ✅ VALIDÉ (maquette approuvée par l'utilisateur le 2026-09-07)
> - **Version :** 1.0.0
> - **Date :** 2026-09-07
> - **Maquette de référence :** artefact « Laurence B. — Maquette CRM » (tableau de bord,
>   contacts, fiche) — source dans `design/maquette.html`.
>
> **Décisions validées avec l'utilisateur :**
> - Marque : **« Laurence B. »**, avec le **logo fourni** (silhouette élégante encre marine +
>   trait doré + accents vert d'eau et lavande). *Fichier logo à ajouter au dépôt ; en
>   attendant, un monogramme SVG en tient lieu.*
> - **Style : skeuomorphisme / neumorphism** — surfaces en relief doux (double ombre :
>   blanc en haut-gauche, ombre froide en bas-droite), champs « en creux » (inset),
>   coins très arrondis, boutons glossy.
> - **Palette « Or & Encre » sur fond clair** — dérivée du logo : or lumineux `#D4A82C`,
>   encre `#33304A`, fond gris très clair légèrement lavande `#F3F2F8`, accents vert d'eau
>   et lavande. (L'émeraude initiale et les fonds beiges sont abandonnés.)
> - **Typographie :** titres en serif **Cormorant Garamond**, corps en **Figtree**.
> - **Mise en page :** **navigation latérale** (sidebar) douce, cartes en relief.
> - **Tableau de bord :** chiffres clés + **Nouveaux leads** (source + statut) +
>   **mini-pipeline** + **tâches du jour**. Titre « Tableau de bord », pas d'emoji.
> - Modes : **clair ET sombre**, avec un bouton de bascule.
> - Priorités roadmap (dans l'ordre) : **1. Tableau de bord**, **2. Améliorations
>   liste/fiche**, **3. Module Pipeline**, **4. Module Activités** ; plus les nouvelles
>   notions **leads / source / statut / tâches** (voir §12bis).

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

Palette **validée**, pensée pour le **mode clair et le mode sombre**, définie comme **jetons**
(variables CSS) réutilisables. Valeurs finales issues de la maquette approuvée.

### Palette « Or & Encre » sur fond clair (validée)

> ⚠️ **Accessibilité :** l'or lumineux sert d'**accent** (boutons glossy, pastilles, icônes,
> filets, focus) ; pour le **texte** on utilise une version assombrie `--accent #9A7A1C`
> (lisible) et l'**encre** pour les titres. Contraste du texte vérifié AA.

| Jeton | Clair | Sombre | Usage |
|---|---|---|---|
| `--bg` | `#F3F2F8` | `#232035` | Fond de page (ground neumorphique) |
| `--surface` | `#FBFBFE` | `#262340` | Cartes / surfaces en relief |
| `--nm-d` / `--nm-l` | `#DEDCEA` / `#FFFFFF` | `#1A1830` / `#302C4C` | Ombres neumorphiques (basse / haute) |
| `--gold` | `#D4A82C` | `#E0BC52` | Or lumineux : boutons, pastilles, anneaux |
| `--gold-bright` | `#E4BE4A` | `#EBCD73` | Dégradé glossy des boutons |
| `--gold-tint` | `#F7EDD1` | `#413920` | Fonds teintés or (avatars, badges) |
| `--accent` | `#9A7A1C` | `#E4C264` | Or **texte** (liens, libellés dorés) — lisible |
| `--ink` / `--text` | `#33304A` | `#EEECF7` | Titres, texte principal |
| `--text-muted` | `#7C7890` | `#ABA7C4` | Libellés, métadonnées |
| `--mint` / `--mint-tint` | `#4E8577` / `#E7F1EC` | `#84C6B6` / `#294540` | Vert d'eau : succès, accents, avatars |
| `--lav` / `--lav-tint` | `#6C74B4` / `#ECEDF8` | `#A7AEE6` / `#31335E` | Lavande : info, accents, avatars |
| `--terra` / `--terra-tint` | `#C0604F` / `#F8E7E2` | `#E68C7E` / `#4A2C2C` | Terracotta : erreur, statut « chaud » |

### Relief neumorphique (jetons d'ombre)
- **En relief (raised) :** `6px 6px 14px var(--nm-d), -6px -6px 14px var(--nm-l)` — cartes, boutons.
- **En creux (inset) :** `inset 3px 3px 7px var(--nm-d), inset -3px -3px 7px var(--nm-l)` —
  champs de recherche, colonnes de pipeline, statuts de tâche.
- Coins : `22px` (cartes), `999px` (pastilles/boutons), `14px` (petits blocs).

### Couleurs d'accent pour les avatars et étiquettes
Cinq teintes douces — **or, vert d'eau, lavande, terracotta** (+ encre) — attribuées selon le
nom, pour des **avatars à initiales** en relief. Sources de leads : LinkedIn `#0A66C2`,
Facebook `#1877F2`, Instagram `#C13584`, e-mail = or, site web = vert d'eau.

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

## 12bis. Fonctionnalités validées pour la roadmap (nouveau)

Décidées avec l'utilisateur pendant la phase design (à spécifier proprement ensuite,
façon Spec Kit) :

- **Leads** — un contact peut être un *lead* (prospect entrant) avec :
  - une **source** : LinkedIn, Instagram, Facebook, E-mail, site web, Typeform, etc.
    (liste extensible, avec pastille de couleur par source) ;
  - un **statut / température** dans le CRM : *client chaud*, *intérêt élevé*,
    *intérêt moyen*, *à qualifier*, etc.
- **Tâches du jour** — rappels et rendez-vous (appel, envoi de proposition…) avec
  un **statut** (planifié, en attente, terminé) et une heure/un montant.
- **Tableau de bord** repensé autour de ces éléments : chiffres clés
  (opportunités / gagnées / nouveaux leads / à relancer), section *Nouveaux leads*
  (cartes avec source + statut), section *Mes tâches du jour*.

Ces notions viendront **enrichir le module Contacts** et alimenteront les futurs
modules **Pipeline** et **Activités**.

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
| 1.0.0 | 2026-09-07 | **Design validé.** Style skeuomorphique/neumorphism, palette finale sur fond clair, or lumineux, sidebar, tableau de bord (leads + pipeline + tâches). Maquette de référence ajoutée (`design/maquette.html`). |

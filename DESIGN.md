# Design System — CRM « Laurence B. »

Référence pratique du style de l'application : **« Or & Encre »**, d'esprit doux
et neumorphique (ombres portées légères + creux), serif élégant pour les titres.

> Source de vérité : `resources/css/app.css` (tokens + classes `.lb-*`).
> Maquette de référence : `design/maquette.html` · notes : `design/design-system.md`.
> Toute nouvelle vue doit réutiliser ces tokens et ces classes plutôt que des
> valeurs en dur, et rester lisible en thème clair **et** sombre.

---

## 1. Couleurs (variables CSS)

On n'écrit **jamais** une couleur en dur : on utilise `var(--token)`. Les valeurs
sont redéfinies automatiquement en thème sombre (`prefers-color-scheme` et
`[data-theme="dark"]`).

| Token | Clair | Rôle |
|---|---|---|
| `--bg` | `#F5F6FA` | fond de page / champs en creux |
| `--surface` | `#FFFFFF` | fond des cartes, boutons |
| `--border` | `#E0E4ED` | bordures fines |
| `--ink` / `--text` | `#252A38` | texte principal / titres |
| `--text-muted` | `#697386` | texte secondaire, libellés |
| `--accent` | `#5254A3` | couleur d'action (liens, boutons, actif) |
| `--accent-strong` | `#43468C` | survol de l'accent |
| `--side` | `#202A44` | fond de la barre latérale |

Couleurs sémantiques (chacune a une variante `-tint` pour les fonds) :

| Famille | Token | `-tint` | Sens habituel |
|---|---|---|---|
| Mint (vert) | `--mint` `#4E8577` | `--mint-tint` | succès, entreprise, « fait » |
| Lavande | `--lav` `#5254A3` | `--lav-tint` | personne, info neutre |
| Or | `--gold` `#D4A82C` | `--gold-tint` | mise en avant, avertissement |
| Terracotta | `--terra` `#C0604F` | `--terra-tint` | danger, retard, erreur |

## 2. Ombres (relief)

| Token | Usage |
|---|---|
| `--raise` | relief marqué — grandes cartes |
| `--raise-sm` | relief léger — boutons, petites tuiles, lignes |
| `--inset` | creux — champs de saisie, zones en retrait (`.lb-inset`) |

## 3. Typographie

- **Titres** : *Cormorant Garamond* (serif). Classe `.serif`, ou directement
  `.lb-h1` (34px) / `.lb-h3` (22px). Jamais pour le corps de texte.
- **Corps** : la police sans-serif par défaut du thème.
- **Libellé de champ discret** : `.k` (11px, MAJUSCULES, `--text-muted`, 600).
- **Titre de ligne / champ en gras** : `.nm` (600, `--ink`) — p. ex. le titre
  d'un e-mail ou d'un champ dans un encadré.
- **Texte secondaire** : `.mt` (12.5px, `--text-muted`) ou `.lb-muted`.
- **Eyebrow** (sur-titre espacé) : `.lb-eyebrow`.

Échelle de tailles courantes : 11–12.5px (secondaire), 13.5–14px (corps/boutons),
19–22px (titres de carte), 34px (titre de page).

## 4. Composants (classes `.lb-*`)

### Cartes & surfaces
- `.lb-card` — carte principale (fond `--surface`, coins 22px, `--raise`).
- `.lb-card-sm` — petite carte (coins 16px, `--raise-sm`).
- `.lb-inset` — zone en creux (fond `--bg`, `--inset`).

### Boutons (tous arrondis en pilule)
- `.lb-btn` + `.lb-btn-primary` — action principale (fond `--accent`).
- `.lb-btn` + `.lb-btn-ghost` — action secondaire (fond `--surface`).
- `.lb-btn` + `.lb-btn-danger` — action destructrice (texte `--terra`).
- `.lb-iconbtn` — bouton icône carré 38px.
- Lien-bouton discret : `.lb-accent` (texte en couleur d'accent).

### Formulaire
- `.lb-field` — input/select/textarea (en creux). `select.lb-field` gère la flèche.
- `.lb-label` — libellé au-dessus du champ. `.lb-req` pour l'astérisque requis.
- `.lb-error` — message d'erreur (terracotta).
- `.lb-select` / `.lb-search` / `.lb-pill` — filtres et recherche (en pilule).

### Listes & lignes
- `.lb-people` (colonne) + `.lb-prow` (ligne encadrée). **Dans une `.lb-card`**,
  `.lb-prow` devient un **encadré** : fond `--bg`, bordure fine, coins 16px.
  Titre `.nm`, sous-texte `.mt`. C'est le style des opportunités / activités.
- `.lb-placeholder` — état vide (en creux, centré, discret).

### Pastilles & badges
- `.lb-tag` — étiquette en pilule (+ `.lb-dot` pour la pastille de couleur).
- `.lb-badge` + `.lb-b-person` / `.lb-b-firm` — badge personne / entreprise.

### Avatars
- `.lb-av` (rond) ou `.lb-av.sq` (carré, entreprises), tailles `.lb-av-44` /
  `.lb-av-34`, couleurs `.lb-av-mint|lav|gold|terra` et variantes contacts.

### Alertes
- `.lb-alert` + `.lb-alert-success|warn|danger`.

### Stats
- `.lb-stat` avec `.k` (libellé), `.v` (grand chiffre serif), `.d` (détail).

## 5. Conventions

- **Filtre actif** : pastille remplie en `--accent`, texte `--btn-text`, gras.
- **Accordéon** : en-tête cliquable + chevron (`rotate(180deg)` à l'ouverture),
  contenu en `x-show` protégé par `[x-cloak]`.
- **Boutons de copie enrichie** : voir la section *Analyse & relances* de la
  fiche contact (copie `text/html` + repli `text/plain`).
- **Espacements** : `gap`/`margin` multiples de ~4px ; cartes à 20px de padding.
- **Accessibilité** : `aria-pressed` sur les toggles, `aria-label` sur les
  boutons icône, focus visible via `--ring`.
- **Thème sombre** : ne jamais coder une couleur en dur ; toujours un token.

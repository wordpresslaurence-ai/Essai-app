# Spec — Brique 11 : Alertes & relances (opportunités qui stagnent, indécis, tâche RDV)

> Étape **specify** (Spec Kit). Langue : français. Statut : en cours.
> Complète l'assistant avec des **signaux à base de règles** (sans IA) dans le Brief,
> et une action « proposer un RDV » sur la fiche.

## 1. Objectif

Trois besoins restants :
- **Opportunités qui stagnent** : repérer les deals ouverts oubliés.
- **Relance des indécis** : réveiller les contacts chauds sans suite et les personnes qui
  s'étaient intéressées à une session passée.
- **Rendez-vous** : quand un RDV est demandé, créer une **tâche « proposer un RDV »**
  (Laurence propose elle-même le créneau).

## 2. Règles fonctionnelles

### Opportunités qui stagnent
- **RF-1** : une opportunité **ouverte** (hors Gagné/Perdu) est « qui stagne » si son **étape
  n'a pas changé depuis ≥ 21 jours**. On suit la date de dernier changement d'étape
  (`etape_changee_le`, posée à la création et à chaque changement d'étape ; à défaut, on se
  rabat sur `updated_at`).
- **RF-2** : elles sont listées dans le **Brief**, avec un bouton pour créer une **tâche de
  relance** liée à l'opportunité (sans doublon).

### Indécis à relancer (Brief)
- **RF-3 — Leads chauds sans suite** : contact **actif**, température **chaud** ou **intérêt
  élevé**, **sans** inscription confirmée, **sans** opportunité gagnée, et **silencieux** depuis
  plus de `relance_delai_jours` jours (aucun e-mail récent et contact créé il y a plus longtemps
  que ce délai).
- **RF-4 — Intéressés à une session passée** : contact **actif** ayant une inscription
  **en attente** ou **annulée** à une séance **passée**, et **sans** inscription confirmée à une
  séance **à venir**.
- **RF-5** : chaque ligne propose un bouton pour créer une **tâche de relance** (sans doublon).

### Rendez-vous (fiche contact)
- **RF-6** : depuis l'analyse, quand un RDV est demandé/détecté, un bouton
  **« Créer une tâche : proposer un RDV »** crée une **tâche** « Proposer un rendez-vous à … »
  (avec la date/les détails en note si présents), sans doublon. Le bouton direct
  « Créer le rendez-vous » (activité datée) reste proposé quand une date ferme est détectée.

### Transverse
- **RF-7** : le **filtre par pôle** du Brief s'applique aussi aux opportunités qui stagnent et
  aux indécis.
- **RF-8** : toutes les actions restent **manuelles** (un clic) ; rien n'est fait
  automatiquement.

## 3. Modèle de données
- Table `opportunites` : colonne additive `etape_changee_le` (datetime, nullable), initialisée
  à la création et mise à jour à chaque `changerEtape`. Reprise : valeur = `updated_at` pour
  les lignes existantes.

## 4. Tests (Pest)
- `scopeStagnantes` : une opp dont l'étape n'a pas bougé depuis > 21 j est listée ; une récente
  ne l'est pas ; une gagnée/perdue n'est jamais « qui stagne ».
- `changerEtape` met à jour `etape_changee_le`.
- Indécis : un lead chaud sans suite est détecté ; un lead chaud récent ou avec inscription
  confirmée ne l'est pas. Un contact en attente sur une séance passée est détecté.
- Fiche : « Créer une tâche : proposer un RDV » crée une tâche (sans doublon).
- Brief : les sections s'affichent et les boutons créent les tâches attendues ; le filtre pôle
  restreint les listes.

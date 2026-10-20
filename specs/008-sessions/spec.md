# Spec — Brique 8 : Sessions & listes d'attente

> Étape **specify** (Spec Kit). Langue : français. Statut : **à valider** avant `plan.md`.
> Note technique : le concept s'appelle **« Session »** dans l'écran, mais le modèle est
> **`Seance`** (table `seances`) car la table `sessions` est réservée par Laravel.

---

## 1. Objectif

Gérer les **sessions datées** de Laurence (promos de **formation de profs** — 10 places —,
**journées aura/chakras**, etc.) avec : un **nombre de places**, les **inscrits**, et une
**liste d'attente** automatique quand c'est complet.

## 2. Récits utilisateur

- **US-1** : je crée une **session** (ex. « Formation profs — Promo mars 2027 »), je lui donne
  une **date**, un **lieu** et un **nombre de places** (10).
- **US-2** : j'**inscris un contact** à une session ; s'il reste de la place il est **confirmé**,
  sinon il passe en **liste d'attente** automatiquement.
- **US-3** : quand une place se libère (annulation), le **premier de la liste d'attente** est
  **promu** automatiquement (et je peux aussi promouvoir à la main).
- **US-4** : je vois d'un coup d'œil, par session, **combien de places prises / restantes** et
  **qui** est confirmé / en attente.
- **US-5** : je range mes sessions par **catégorie** (Formation profs, Journée aura…) et je peux
  filtrer.

## 3. Périmètre

**Inclus**
- Écran **Sessions** : liste des sessions (à venir / passées), avec places prises/restantes ;
  filtre **Pôle → Catégorie**.
- Création / modification d'une session : titre, **catégorie** (facultative), **date** (une seule
  date/heure ; détail des stages dans les notes), **lieu**, **nombre de places**, notes.
- **Fiche session** : liste des **confirmés** et de la **liste d'attente** ; bouton **« Inscrire
  un contact »** ; actions **désinscrire / annuler**, **promouvoir** depuis l'attente.
- **Liste d'attente automatique** (au-delà de la capacité) + **promotion automatique** du premier
  en attente quand une place se libère.
- Sur la **fiche contact** : ses sessions (petit encart « Inscriptions »).

**Exclus (plus tard)**
- Paiement / facturation (reste hors CRM).
- Inscriptions via un formulaire public ou un webhook Fluent Booking.
- Rappels e-mail automatiques, gestion fine des 3 stages d'une promo (on met les détails dans
  les notes / la date de fin pour l'instant).

## 4. Règles fonctionnelles

- **RF-1** : une **inscription** lie un **contact** à une **session** avec un statut :
  `confirme`, `attente` ou `annule`.
- **RF-2** : à l'inscription, si `confirmés < capacité` → **confirmé**, sinon → **attente**.
  (Capacité vide ou 0 = pas de limite → toujours confirmé.)
- **RF-3** : un même contact ne peut avoir **qu'une inscription active** (confirmé ou attente)
  par session.
- **RF-4** : quand un **confirmé** est désinscrit/annulé et qu'il reste des personnes en
  **attente**, la **plus ancienne** passe **confirmée** (promotion auto). Promotion manuelle
  possible aussi.
- **RF-5** : suppression d'un **contact** → ses inscriptions sont supprimées (sans toucher aux
  autres). Suppression d'une **session** → ses inscriptions aussi.
- **RF-6** : l'**ordre** de la liste d'attente suit la date d'inscription.

## 5. Données (nouveaux éléments)

- **Table `seances`** : `titre`, `categorie_id` (nullable, `nullOnDelete`), `date_debut`
  (date/heure, une seule date par session), `lieu` (nullable), `capacite` (unsigned, nullable),
  `notes`, timestamps.
- **Table `inscriptions`** : `seance_id` (cascade), `contact_id` (cascade), `statut`
  (`confirme`/`attente`/`annule`), `notes` (nullable), timestamps ; unicité logique d'une
  inscription active par (séance, contact).

## 6. Écrans

- **Sessions / Liste** (`Sessions\Liste`) : cartes ou lignes avec titre, catégorie, date,
  **X/▢ places**, badge « Complet » / « N en attente » ; filtres Pôle→Catégorie ; onglets
  « À venir » / « Passées ».
- **Sessions / Formulaire** (`Sessions\Formulaire`) : créer/éditer une session.
- **Sessions / Fiche** (`Sessions\Fiche`) : détails + inscrits (confirmés / attente) + inscrire /
  promouvoir / désinscrire.
- **Nav** : entrée **« Sessions »** (après « Organisation »).
- **Fiche contact** : encart **« Inscriptions »** (ses sessions + statut).

## 7. Critères d'acceptation

1. Je crée une session à 10 places, j'inscris 12 contacts : **10 confirmés + 2 en attente**.
2. Je désinscris un confirmé → **le 1ᵉʳ en attente devient confirmé** automatiquement.
3. Le **filtre** Pôle→Catégorie et les onglets À venir/Passées fonctionnent.
4. Supprimer une session/un contact nettoie les inscriptions, sans effet de bord.
5. **Tests** Pest verts ; **CI verte** (PHP 8.4 & 8.5).

## 8. Découpage pressenti (tasks)

- **T1** — Données : tables `seances`, `inscriptions` + modèles + relations + logique
  (inscrire / promouvoir / places restantes) + tests.
- **T2** — Écran **Sessions / Liste** (places, badges, filtres, onglets) + route + nav + tests.
- **T3** — **Formulaire** de session (créer/éditer) + tests.
- **T4** — **Fiche session** : inscrits, inscrire, promouvoir, désinscrire + tests.
- **T5** — Encart **Inscriptions** sur la fiche contact + **seed** de démo + docs.

---

*Prochaine étape (après validation) : `plan.md` puis implémentation tâche par tâche, CI verte
à chaque étape.*

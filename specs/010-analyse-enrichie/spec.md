# Spec — Brique 10 : Analyse enrichie (intention, infos clés, actions 1-clic)

> Étape **specify** (Spec Kit). Langue : français. Statut : en cours.
> Enrichit l'« Analyse & relances » de la fiche contact : l'assistant ne se contente
> plus de résumer et de proposer un brouillon, il **comprend** le fil (intention,
> infos clés, promesses, rendez-vous) et propose des **actions en 1 clic** — jamais
> automatiques.

## 1. Objectif

À partir du fil d'e-mails d'un contact, l'assistant produit une **fiche d'analyse riche**
(qui sert aussi de **prépa avant un appel**) et propose des actions que Laurence applique
d'un clic :
- **intention** du contact (devis, RDV, réclamation, paiement, question…) ;
- **infos clés** extraites (budget, délai, dates de RDV, décisions) ;
- **température conseillée** (froid → chaud) → bouton « Appliquer » ;
- **étape de pipeline conseillée** (ex. devis envoyé → « Proposition ») → bouton « Faire avancer » ;
- **promesses** détectées (« je vous envoie le devis lundi ») → bouton « Créer un rappel » ;
- **rendez-vous** détecté → bouton « Créer le rendez-vous ».

## 2. Périmètre

**Inclus**
- Enrichissement du résultat de l'assistant (`analyser`) : intention, température conseillée,
  étape conseillée, infos clés, promesses, rendez-vous — en plus du résumé / verdict / brouillon.
- Stockage de ces champs dans le modèle `Analyse` (colonnes additives).
- Affichage sur la fiche contact (carte « Analyse & relances » enrichie = prépa appel).
- **Actions 1 clic**, toutes manuelles (jamais d'automatisme) :
  - appliquer la **température** conseillée au contact ;
  - **faire avancer** l'opportunité ouverte du contact vers l'étape conseillée ;
  - créer une **tâche de rappel** depuis une promesse (avec échéance si détectée) ;
  - créer une activité **rendez-vous** depuis une date de RDV détectée.

**Exclus (autres briques)**
- Alertes d'opportunités qui **stagnent** et **relance des indécis** (règles → brique 011).
- **Proposer des créneaux** de RDV selon une disponibilité (brique 011, nécessite une source
  de disponibilité).
- Mise à jour **automatique** (sans clic) de la température ou du pipeline.
- Multilingue, apprentissage de la voix (autres briques).

## 3. Règles fonctionnelles

- **RF-1** : l'assistant renvoie, en plus de l'existant, des champs structurés : `intention`
  (valeur parmi une liste connue, sinon « autre »), `temperature_conseillee` (une des
  températures valides ou rien), `etape_conseillee` (une des étapes valides ou rien),
  `infos_cles` (budget, délai, dates_rdv, décisions), `promesses` (liste {texte, échéance}),
  `rdv` (date, détails).
- **RF-2** : valeurs **normalisées** côté application : une température / étape non reconnue
  est ignorée (null). L'assistant **n'invente jamais** de fait absent du fil.
- **RF-3** : « Appliquer la température » met à jour `contact.temperature` avec la valeur
  conseillée (si valide).
- **RF-4** : « Faire avancer » applique l'étape conseillée à **l'opportunité ouverte** la plus
  récente du contact (via `changerEtape`). S'il n'y a pas d'opportunité ouverte, un message
  l'indique (aucune création automatique d'opportunité).
- **RF-5** : « Créer un rappel » crée une **tâche** (`type = tache`) rattachée au contact,
  titrée d'après la promesse, avec l'échéance détectée si présente ; pas de doublon (une tâche
  ouverte identique n'est pas recréée).
- **RF-6** : « Créer le rendez-vous » crée une activité (`type = rdv`) rattachée au contact,
  à la date détectée ; pas de doublon.
- **RF-7** : toutes ces actions sont **manuelles** (un clic), jamais déclenchées par la seule
  analyse.

## 4. Modèle de données

- Table `analyses` : colonnes additives `intention` (string), `temperature_conseillee` (string),
  `etape_conseillee` (string), `infos_cles` (json), `promesses` (json), `rdv` (json).

## 5. Tests (Pest)

- `analyser` enregistre les champs enrichis.
- Parsing côté client Claude (API simulée) des nouveaux champs + rétro-compatibilité
  (réponse minimale sans ces champs → pas d'erreur).
- Actions : appliquer température, faire avancer le pipeline, créer un rappel (avec échéance),
  créer un rendez-vous ; dédoublonnage des tâches/rdv.

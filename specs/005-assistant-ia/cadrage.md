# Cadrage — Assistant IA de suivi & relances

> Document de **cadrage** (discovery), étape 0 de la méthode Spec Kit.
> Il capture le besoin et les décisions **avant** d'écrire la spec détaillée.
> État : **en cours** — l'activité « Sites WordPress » reste à décrire (vocal à venir).

---

## 1. Objectif

Ajouter au CRM « Laurence B. » un **assistant IA** qui :
- **lit les échanges email** avec les clients/prospects (envois **et** réponses),
- **suit les conversations** et **dit quoi faire** : qui relancer, quand, comment,
- **rédige des brouillons** (prospection, relances, réponses) dans le ton de Laurence,
- **rend compte dans le CRM** (résumé, tâches, mise à jour des fiches).

Principe directeur : l'assistant est la **mémoire**, le **garde-temps**, la **plume** et le
**copilote de pilotage** — il **propose**, Laurence **décide et envoie**.

## 2. Parti pris d'architecture

**Famille II — l'assistant vit DANS le CRM** (pas un outil tiers séparé).
- Le CRM se connecte lui-même aux boîtes mail et aux outils.
- L'intelligence tourne **côté serveur** via l'**API Claude** (quelques centimes par analyse).
- Les données restent **chez Laurence** (son hébergement) → meilleur pour le RGPD.

## 3. Garde-fous (non négociables)

- **Jamais d'envoi automatique** (au moins au début) : l'assistant propose, Laurence clique.
- **Prospection semi-manuelle** : pas de scraping ni d'envoi de masse LinkedIn/Instagram
  (contraire aux règles de ces plateformes, risque de blocage, contraintes RGPD).
  L'assistant **aide à trouver, rédige, trace et rappelle** ; l'envoi reste manuel.
- **Périmètre email** : ne traiter que les mails liés à un **contact connu** du CRM.
- **Facturation** : jamais dans le CRM (reste sur Odoo). Voir §7.
- **RGPD** (Belgique) : données de clients = données personnelles ; conservation chez l'hôte,
  pas de diffusion vers un outil tiers.

---

## 4. Les activités de Laurence

### 4.A — Prana Vidya *(décrite ✅)*

Trois offres + une prospection d'un autre type :

1. **Formation annuelle profs de yoga** (présentiel)
   - Sur 1 an, **3 stages de 3 jours**, **10 places** par promo.
   - **~3 promos / an**. Nouvelle promo ouverte **seulement si la liste d'attente est suffisante**.
   - Entrées : **session découverte gratuite** (lien Fluent Booking) + **RDV 1-to-1 en ligne**
     (autre lien Fluent Booking).
   - **Prospection** de profs de yoga nécessaire (pas de liste existante → départ de zéro).
2. **Journées « nettoyage aura & chakras »** (présentiel, 1 jour)
   - **Plusieurs dates** (à définir), **lieux variés**, **page de site dédiée**.
   - **Inscription + paiement** via Fluent Booking.
3. **Prospection de salles / lieux** (pour héberger les stages de 3 jours)
   - Pipeline **distinct** (fournisseurs/partenaires, pas des clients).

Boîte mail concernée pour démarrer : **`laurencehomeharmony@gmail.com`**.

### 4.B — Internet / Digital *(décrite ✅ — nom du pôle à confirmer)*

Boîte : `wordpresslaurence@gmail.com`. Plusieurs activités, vouées à évoluer :

1. **Sites WordPress**
   - Activité qui **va probablement décroître** (sans disparaître) → à **garder** mais
     **pas prioritaire**.
   - Arrive surtout par **bouche-à-oreille** → peu de prospection.
2. **PDF interactifs** (freebies / artefacts créés avec l'IA)
   - Laurence **crée l'artefact à l'avance**, puis veut **vendre** : mail de prospection →
     envoi → **suivi des réponses / relances**. → activité **active/prioritaire** du pôle.
3. **Futur** : petits **bots**, **workshops IA**, autres « petites choses faites par l'IA »
   (pas encore défini) → doivent pouvoir s'ajouter **facilement** (voir §5.bis).

---

## 5. Objets du domaine (ce que le CRM devra représenter)

- **Pôle** et **Activité** (voir §5.bis) — la **taxonomie configurable** à deux niveaux.
- **Contact** (déjà existant) — personne/entreprise, avec une « casquette » selon l'activité
  (un même contact peut être client web **et** élève prana).
- **Offre / Programme** : Formation profs, Journée aura/chakras, (plus tard) prestations web.
- **Session** : une instance datée d'une offre — une **promo** de formation, une **journée**
  (date + lieu). Porte une **jauge de places**.
- **Inscription / Participation** : lien entre un contact et une session (intéressé, inscrit,
  payé, présent…).
- **Liste d'attente** : contacts intéressés par une offre quand il n'y a pas (encore) de place.
- **Salle / Lieu (partenaire)** : contact d'un type particulier, pour la prospection de salles.
- **Réservation (booking)** : créneau pris via Fluent Booking (découverte, 1-to-1, journée).
- **Réunion + transcription** : compte-rendu (tl;dv) collé → résumé + tâches.

## 5.bis — Taxonomie à deux niveaux (configurable par Laurence)

Besoin explicite : pouvoir **ajouter facilement** de nouvelles activités. On ne fige donc pas
les onglets ; on met une structure **à deux étages que Laurence gère elle-même** :

```
PÔLE (onglet principal)   →   ACTIVITÉ (sous-onglet, avec son propre pipeline)
──────────────────────────────────────────────────────────────────────────────
Prana                     →   Formation profs · Journée aura/chakras · Salles · (Workshops…)
Internet / Digital        →   Sites web · PDF interactifs · Workshops IA · (Bots…)
```

- **Pôle** et **Activité** sont des données **créables depuis l'interface** (pas en dur dans le code).
- Chaque **Activité** porte son **pipeline** (ses étapes), sa jauge de places s'il y a lieu, etc.
- Chaque **contact / opportunité** est rangé par **pôle + activité**.
- Nom du 2ᵉ pôle **à confirmer** : Internet / Digital / Numérique / Studio / Web & IA / Créations.

## 6. Pipelines (une **Activité = un sous-onglet = un pipeline**)

Exemples d'étapes (modifiables, chaque activité a les siennes) :

- **Formation profs** : prospection → découverte gratuite → RDV 1-to-1 → inscription (→ promo).
- **Journées aura/chakras** : intérêt → inscription payante (par date/lieu).
- **Salles / partenaires** : à contacter → dispo demandée → devis reçu → réservé.
- **PDF interactifs** : artefact prêt → prospection → envoi → relance → vendu.
- **Sites web** : (surtout bouche-à-oreille) contact → devis → maquette → mise en ligne → maintenance.

> Le **moteur « prospection → mail → suivi → relances »** est **transversal** : identique
> pour toutes les activités. On le construit **une fois**, il sert partout.

## 7. Intégrations

| Outil | Rôle | Mode retenu |
|---|---|---|
| **Gmail** (`laurencehomeharmony@` d'abord) | lire envois + réponses | 1 boîte pour commencer, puis les autres |
| **Fluent Booking** | réservations (découverte, 1-to-1, journée payante) | ⭐ **passer en Pro** pour les **webhooks** → création auto de fiche/activité dans le CRM ; statut de **paiement** transmis si possible |
| **Google Agenda** | les RDV y sont écrits par Fluent Booking | le CRM **lit** l'agenda (rappels + fiche de prépa avant RDV) |
| **tl;dv** | comptes-rendus de réunion | Laurence **colle la transcription** → l'IA en tire résumé + **tâches dans le CRM** |
| **Odoo** | factures | **Pas d'intégration** : Laurence **coche « facturé »** + saisit **n° de facture, montant** (et payée o/n) |

Notes :
- **Tâches** → vivent **uniquement dans le CRM** (c'est lui qui rappelle).
- **RDV** → vivent dans **Google Agenda** (écrits par Fluent Booking). Pas de doublon.
- Dans les mails, l'assistant insère **le bon lien Fluent Booking** selon le contexte
  (découverte / 1-to-1 / journée). Le prospect **réserve lui-même** ; le webhook logue.

## 8. Ce que fait l'assistant (fonctions pressenties)

- **Comprendre** : rattacher le mail au contact, détecter l'activité, résumer le fil,
  repérer l'intention (prospect, question, RDV, paiement, silence…), la langue (FR/NL/EN).
- **Relancer (2 sens)** :
  - *toi en attente* (tu as écrit, pas de réponse depuis X jours),
  - ⚠️ *toi en retard* (le client a écrit, pas encore répondu).
- **Rédiger** : mails de **prospection sortante**, **relances**, réponses — dans le ton de Laurence.
- **Alimenter le CRM** : journaliser les mails, créer un lead depuis un mail entrant inconnu,
  faire avancer le pipeline, compléter les fiches, extraire les tâches d'une transcription.
- **Préparer** : brief du matin (relances du jour + réponses en attente), fiche de prépa avant RDV.
- **Liste d'attente** : signaler « assez de monde en attente → ouvrir une nouvelle promo ».
- **Piloter** : taux de conversion, délai de réponse, sources qui marchent, places restantes.

## 9. Décisions prises

| Sujet | Décision |
|---|---|
| Où vit l'assistant | **Dans le CRM** (Famille II), moteur = API Claude |
| 1ʳᵉ boîte branchée | **`laurencehomeharmony@gmail.com`** |
| Fluent Booking | pas Pro aujourd'hui → **OK pour investir** (webhooks + paiement) |
| Paiement journées | « bonus si facile », **pas obligatoire** |
| Prospection LinkedIn/Insta | **copilote semi-manuel**, pas de robot/scraping |
| Rédaction prospection | **oui**, l'assistant rédige les mails |
| Formation | **~3 promos/an × 10** + **liste d'attente** (ouvre si assez de monde) |
| Salles | **même CRM**, **onglet séparé** par type de prospection |
| Tâches / RDV | tâches **CRM**, RDV **Google Agenda** (via Fluent Booking) |
| Odoo | **manuel** : coche « facturé » + n° + montant |
| **Organisation** | **2 niveaux configurables** : Pôle → Activité (sous-onglets), ajoutables par Laurence |
| **Pôles** | **Prana** et **Internet/Digital** (nom à confirmer) |
| **Sites web** | activité **gardée mais non prioritaire** (déclin, bouche-à-oreille) |
| **PDF interactifs** | activité **active** : artefact créé à l'avance → prospection → suivi |
| **Moteur prospection/relances** | **transversal** à toutes les activités (construit une seule fois) |

## 10. Questions encore ouvertes

- **Nom du 2ᵉ pôle** : Internet / Digital / Numérique / Studio / Web & IA / Créations ?
- Récupération des mails : **automatique** (toutes les X min) ou **à la demande** pour l'étape 1 ?
  *(pressenti : à la demande d'abord, plus simple.)*
- Confirmer les **liens Fluent Booking** exacts + l'**URL du site**.
- Langues à gérer en priorité (FR seul au départ ?).

## 11. Priorisation pressentie

1. **Moteur de l'assistant** (transversal) : brancher `laurencehomeharmony@`, journaliser les
   fils des contacts connus, bouton **« Analyse & relances »** (résumé + qui relancer/pourquoi
   + **brouillon** avec le bon lien Fluent Booking), **brief du matin** (relances + « tu dois
   une réponse »).
2. **Taxonomie à 2 niveaux** (pôles / activités configurables).
3. **Prospection PDF interactifs** (activité active : vendre les artefacts).
4. **Prospection formation profs** (Prana) + **sessions / liste d'attente**.
5. **Webhook Fluent Booking** (après passage en Pro) : réservations → fiches/activités auto.
6. **Sites WordPress** (bas, bouche-à-oreille) + extension aux autres boîtes (`wordpresslaurence@`, Outlook).

---

*Prochaine étape : trancher les questions ouvertes (§10), puis rédiger `spec.md` (périmètre
précis) → `plan.md` → `tasks.md` pour la **brique 1** (le moteur de l'assistant).*

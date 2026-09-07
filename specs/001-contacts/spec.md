# Spécification — Module Contacts / Entreprises

> **Étape 1 (Spec Kit) — le QUOI, pas le COMMENT.**
> Ce document décrit ce que le module doit faire et pourquoi, sans détail technique.
> Il doit rester conforme à la [constitution](../../constitution.md).
>
> - **ID :** 001-contacts
> - **Statut :** validée
> - **Version :** 1.0.0
> - **Date :** 2026-09-07

---

## 1. Objectif

Permettre à l'utilisateur de **centraliser et gérer son carnet de relations** :
les **personnes** et les **entreprises** avec qui il est en affaires. C'est la brique
de base du CRM : le pipeline et les activités viendront s'y rattacher.

**Pourquoi :** aujourd'hui ces informations vivent dans Odoo (ou dispersées). L'objectif
est de disposer d'un carnet clair, rapide à consulter et à mettre à jour, adapté à un
usage solo, en Belgique.

---

## 2. Personas

- **L'utilisateur (unique, solo).** Consulte, crée et met à jour ses contacts au
  quotidien. Veut retrouver une fiche en quelques secondes et voir l'historique lié.

---

## 3. Scénarios utilisateur (priorisés)

### P1 — Créer et retrouver un contact *(indispensable)*
En tant qu'utilisateur, je veux **ajouter une personne ou une entreprise** avec ses
coordonnées, puis la **retrouver par recherche** (nom, email, téléphone), afin de
disposer d'un carnet fiable.

### P2 — Relier une personne à une entreprise *(indispensable)*
En tant qu'utilisateur, je veux **rattacher une personne à l'entreprise** pour laquelle
elle travaille (avec sa fonction), afin de comprendre qui est qui.

### P3 — Enrichir et organiser *(important)*
En tant qu'utilisateur, je veux **classer mes contacts par étiquettes** (ex. « client »,
« prospect », « fournisseur ») et **ajouter des notes libres**, afin de retrouver
facilement des groupes de contacts.

### P4 — Nettoyer *(souhaitable)*
En tant qu'utilisateur, je veux **archiver** un contact qui n'est plus actif sans le
supprimer définitivement, afin de garder mon carnet propre tout en conservant l'historique.

---

## 4. Exigences fonctionnelles

Chaque exigence est numérotée et **vérifiable** (elle pourra devenir un test).

### Gestion des fiches
- **EF-01** — Le système permet de créer un contact de type **Personne** OU
  **Entreprise**.
- **EF-02** — Le système permet de consulter la fiche détaillée d'un contact.
- **EF-03** — Le système permet de modifier tous les champs d'un contact existant.
- **EF-04** — Le système permet d'**archiver** un contact (le retirer des listes
  actives) et de le **désarchiver**. La suppression définitive est possible mais
  distincte de l'archivage et demande une confirmation.
- **EF-05** — Le système affiche la liste des contacts, **paginée**, triable par nom
  et par date de dernière modification.

### Champs — Personne
- **EF-06** — Une personne possède : **nom** (obligatoire), **prénom**, **email**,
  **téléphone/mobile**, **fonction/poste**, **entreprise de rattachement**, **adresse**
  (rue, code postal, ville, pays — pays par défaut : Belgique), **étiquettes**,
  **notes** libres.

### Champs — Entreprise
- **EF-07** — Une entreprise possède : **raison sociale** (obligatoire), **email**,
  **téléphone**, **site web**, **numéro d'entreprise (BCE/KBO)**, **numéro de TVA**,
  **adresse**, **secteur d'activité**, **étiquettes**, **notes** libres.
- **EF-08** — Une entreprise affiche la **liste des personnes** qui lui sont rattachées.
- **EF-08b** — Une personne est rattachée à **au plus une seule entreprise** (décision
  MVP). Le multi-entreprises n'est pas géré.

### Recherche et organisation
- **EF-09** — Le système permet de **rechercher** un contact par nom, prénom, raison
  sociale, email ou téléphone. La recherche est insensible à la casse.
- **EF-10** — Le système permet de **filtrer** la liste par type (Personne / Entreprise),
  par étiquette et par statut (actif / archivé).
- **EF-11** — Le système permet de gérer un ensemble d'**étiquettes** réutilisables et
  d'en associer plusieurs à un contact.

### Validation et qualité des données
- **EF-12** — Un email saisi doit avoir un **format valide**.
- **EF-13** — Le système **signale les doublons potentiels** à la création (même email,
  ou même nom + même entreprise) sans bloquer l'utilisateur.
- **EF-14** — Les champs obligatoires (nom pour une personne, raison sociale pour une
  entreprise) sont contrôlés ; un message clair en français s'affiche sinon.
- **EF-15** — Le **numéro d'entreprise (BCE/KBO)** est validé : 10 chiffres au format
  belge `0XXX.XXX.XXX` (commençant par 0 ou 1), avec vérification de la **clé de
  contrôle** officielle (modulo 97). Facultatif, mais s'il est renseigné il doit être
  valide.
- **EF-16** — Le **numéro de TVA** est validé au format belge `BE` + le numéro
  d'entreprise à 10 chiffres. S'il est renseigné avec le numéro d'entreprise, leur
  cohérence est vérifiée. Facultatif, mais valide s'il est renseigné.

### Import de contacts
- **EF-17** — Le système permet d'**importer des contacts depuis un fichier CSV**
  (personnes et/ou entreprises), pour reprendre les données existantes issues d'Odoo.
- **EF-18** — L'import propose une **correspondance des colonnes** du CSV vers les
  champs du contact (nom, email, téléphone, entreprise, etc.).
- **EF-19** — L'import affiche un **récapitulatif avant validation** (nombre de lignes,
  lignes en erreur, doublons potentiels détectés) et n'écrit rien tant que
  l'utilisateur n'a pas confirmé.
- **EF-20** — Les lignes invalides (champ obligatoire manquant, email/BCE/TVA au mauvais
  format) sont **signalées ligne par ligne** et **ignorées** sans faire échouer tout
  l'import ; l'utilisateur voit le rapport des lignes rejetées.

### Traçabilité
- **EF-21** — Chaque fiche conserve sa **date de création** et sa **date de dernière
  modification**.
- **EF-22** — La fiche d'un contact affiche un emplacement pour l'**historique des
  activités et opportunités liées** (rempli par les modules Pipeline et Activités —
  prévoir la place, contenu détaillé hors de cette spec).

---

## 5. Entités clés (vocabulaire métier, sans technique)

- **Contact** — une fiche, de type **Personne** ou **Entreprise**.
- **Personne** — un individu ; peut être rattaché à une Entreprise avec une fonction.
- **Entreprise** — une organisation ; regroupe zéro, une ou plusieurs Personnes.
- **Étiquette** — un mot-clé réutilisable pour classer les contacts.
- **Adresse** — rue, code postal, ville, pays.

*(Noms de domaine en français conformément à la constitution : Contact, Personne,
Entreprise, Étiquette, Adresse.)*

---

## 6. Cas limites à gérer

- Une **personne sans entreprise** (indépendant, particulier) → autorisé.
- Une **entreprise sans aucune personne** → autorisé.
- Suppression d'une **entreprise ayant des personnes rattachées** → les personnes sont
  **détachées** (elles restent, sans entreprise) ; elles ne sont jamais supprimées avec
  l'entreprise. *(Décision actée.)*
- **Doublon** détecté → avertir, ne pas bloquer.
- Contact **archivé** → n'apparaît pas dans les listes/recherches actives par défaut,
  mais reste consultable via le filtre « archivés ».
- Champs **facultatifs vides** → la fiche reste valide et lisible.

---

## 7. Critères d'acceptation (le module est « fini » quand…)

1. Je peux créer une Personne et une Entreprise, les modifier, les archiver et les
   supprimer.
2. Je peux rattacher une Personne à une Entreprise et voir cette Personne listée sur la
   fiche de l'Entreprise.
3. Je peux rechercher un contact par nom/email/téléphone et filtrer par type, étiquette
   et statut.
4. Les champs obligatoires et le format d'email sont contrôlés, avec messages en français.
5. Un numéro BCE et un numéro de TVA invalides sont refusés avec un message clair ;
   renseignés valides, ils sont acceptés.
6. Un doublon potentiel est signalé à la création.
7. La liste est paginée et triable.
8. Je peux importer un fichier CSV de contacts : voir le récapitulatif, corriger/ignorer
   les lignes en erreur, puis confirmer l'import.
9. Chaque parcours critique ci-dessus est couvert par un test (conformément à la
   constitution, article 5).

---

## 8. Décisions actées

Tous les points en suspens ont été tranchés avec l'utilisateur :

1. **Suppression d'une entreprise liée à des personnes** → les personnes sont
   **détachées** (conservées, sans entreprise). *(voir EF-04, §6)*
2. **Import Odoo** → **import CSV dès ce module**. *(voir EF-17 à EF-20)*
3. **Personne ↔ entreprise** → **une seule entreprise** par personne au MVP.
   *(voir EF-08b)*
4. **Numéro BCE / TVA** → **format validé** (clé de contrôle belge), et non texte
   libre. *(voir EF-15, EF-16)*

---

## Historique

| Version | Date       | Changement                        |
|---------|------------|-----------------------------------|
| 0.1.0   | 2026-09-07 | Brouillon initial du module Contacts. |
| 1.0.0   | 2026-09-07 | Décisions actées : détachement des personnes, import CSV, une entreprise par personne, validation des formats BCE/TVA. Ajout des exigences d'import (EF-17→20) et de validation belge (EF-15, EF-16). |

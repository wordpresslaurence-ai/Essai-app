# Checklist qualité — Module Contacts / Entreprises

> **Étape 2bis (Spec Kit) — validation qualité.**
> Vérifie que la [spec](./spec.md) et le [plan](./plan.md) sont **complets, clairs et
> cohérents** avant de coder. Chaque case cochée = point validé.
>
> - **ID :** 001-contacts
> - **Date :** 2026-09-07

---

## A. Qualité de la spécification (le QUOI)

- [x] **A1** — L'objectif du module est énoncé clairement (§1 de la spec).
- [x] **A2** — Les personas / utilisateurs sont identifiés (§2).
- [x] **A3** — Chaque scénario utilisateur est priorisé (P1→P4) et rédigé « en tant
  que… je veux… afin de… » (§3).
- [x] **A4** — Chaque exigence fonctionnelle est **numérotée** et **vérifiable**
  (EF-01 → EF-22).
- [x] **A5** — Aucune exigence ne contient de détail technique (pas de nom de table, de
  framework, de librairie dans la spec). *Vérifié : la spec parle métier uniquement.*
- [x] **A6** — Les champs de chaque entité sont listés, avec les obligatoires marqués
  (EF-06 personne, EF-07 entreprise).
- [x] **A7** — Les cas limites sont recensés (§6) et tranchés (détachement, doublons,
  archivage, champs vides).
- [x] **A8** — Les critères d'acceptation permettent de dire « fini ou pas » (§7).
- [x] **A9** — Aucun `[À CLARIFIER]` ne subsiste : les 4 décisions sont actées (§8).
- [x] **A10** — Les spécificités **belges** (BCE, TVA, pays par défaut Belgique) sont
  présentes (EF-07, EF-15, EF-16).

## B. Qualité du plan technique (le COMMENT)

- [x] **B1** — La stack imposée par la constitution est respectée (Laravel, MySQL/SQLite,
  TALL, Pest) — voir §11 du plan (tableau de conformité).
- [x] **B2** — Le modèle de données couvre **toutes** les entités de la spec (Contact,
  Etiquette, Adresse via colonnes, relation personne↔entreprise).
- [x] **B3** — Chaque exigence fonctionnelle de la spec est adressée par au moins un
  élément du plan. *Vérifié en détail dans le rapport d'analyse (analyze.md).*
- [x] **B4** — Les règles de gestion belges ont une solution technique explicite
  (règles `NumeroEntrepriseBe` / `NumeroTvaBe`, §5).
- [x] **B5** — La sécurité est traitée (validation, CSRF/XSS/SQLi natifs, Policies) §5/§7.
- [x] **B6** — La performance est traitée (index, pagination, anti N+1) §2/§4.
- [x] **B7** — L'accessibilité est traitée (§4, tâche T056).
- [x] **B8** — Les dépendances sont listées et minimales ; les « à confirmer » sont
  identifiées (`league/csv`) §10.
- [x] **B9** — Un plan de tests existe et couvre chaque parcours critique (§9).
- [x] **B10** — La contrainte d'hébergement Hostinger est respectée (stockage local,
  cron, pas de démon Node) §6, art. 3.

## C. Cohérence avec la constitution

- [x] **C1** — Périmètre conforme : contacts + (place pour) pipeline/activités ;
  facturation exclue (reste sur Odoo). *(constitution art. 1)*
- [x] **C2** — Code métier en français (Contact, Personne, Entreprise, Etiquette).
  *(art. 4.3)*
- [x] **C3** — Tests obligatoires prévus avant « fini ». *(art. 5)*
- [x] **C4** — Policies présentes même en usage solo. *(art. 6.4)*
- [x] **C5** — Aucune donnée sensible / secret dans le dépôt. *(art. 3.4)*

## D. Complétude pour démarrer le code

- [x] **D1** — Le socle Phase 0 est en place et testé (Laravel démarre, auth OK, FR OK).
- [x] **D2** — Les tâches sont ordonnées avec leurs dépendances (tasks.md §dépendances).
- [x] **D3** — Chaque tâche a un critère de fin (« ✅ Fait quand »).
- [x] **D4** — Rien ne bloque le démarrage de la Phase 1 (migrations).

---

## Verdict

✅ **Spec et plan validés.** Aucun point bloquant. Les rares zones de souplesse
(`league/csv` à confirmer, historique activités/opportunités hors périmètre de ce
module) sont explicitement identifiées et n'empêchent pas d'avancer.

**Prêt à implémenter la Phase 1.**

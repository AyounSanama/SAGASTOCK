# Backlog des améliorations

Mis à jour le 30/09/2026. Source principale :
[01-analyse-cahier-des-charges.md](01-analyse-cahier-des-charges.md).

## Vue d'ensemble

| ID | Pri. | Domaine | Titre | Statut | Dépend de |
|---|:---:|---|---|---|---|
| AM-100 | P0 | SEC | Conserver Sanctum avec expiration et rotation ; amender la spécification | Validée (DEC-07) | — |
| AM-101 | P0 | QA | Remettre la suite de tests backend au vert | **Livrée** (30/09) | — |
| AM-102 | P0 | UI | Commiter la refonte UI/Tailwind après non-régression | **Livrée** (30/09) | AM-101 |
| AM-103 | P1 | QA | Recette visuelle Admin Site et Utilisateur Site | Proposée | AM-102 |
| AM-104 | P1 | SYNC | Compléter la synchronisation : conflits, curseurs, historique | Proposée | AM-101 |
| AM-110 | P1 | CONF | Enrichir la fiche projet (partenaire, codes, responsable, statut) | **Livrée** (30/09) | AM-102 |
| AM-111 | P1 | REF | Niveaux de soins hiérarchiques (Niveau → Catégorie → Programme) | **Livrée** (30/09) | AM-102 |
| AM-112 | P1 | CONF | Configuration médicale du projet (niveaux, populations, pathologies) | **Livrée** (30/09) | AM-111 |
| AM-113 | P1 | REF | Génération de la liste standard à partir de la configuration projet | **Livrée** (30/09) | AM-112 |
| AM-114 | P1 | CONF | Paramètres d'approvisionnement obligatoires et historisés | **Livrée** (30/09) | AM-110 |
| AM-120 | P2 | GOUV | Formulaire de compte en trois rubriques (Web et mobile) | Proposée | AM-102 |
| AM-121 | P1 | GOUV | Formaliser les limitations de modification des comptes | Bloquée (DEC-03) | — |
| AM-122 | P1 | GOUV | Contexte projet automatique pour l'Admin Projet | Proposée | — |
| AM-130 | P1 | UI | « Mon projet » complet (configuration médicale + indicateurs) | Proposée | AM-112 |
| AM-131 | P1 | STRUCT | Fiche FOSA de supervision pour l'Admin Projet | Bloquée (DEC-04, DEC-05) | AM-122 |
| AM-132 | P2 | RAPP | Indicateurs agrégés par projet (API unique) | Proposée | AM-130 |
| AM-140 | P1 | RAPP | Module Rapports Web (remplace le placeholder) | Proposée | AM-101 |
| AM-141 | P1 | SYNC | Écran de supervision de la synchronisation | Proposée | AM-104 |
| AM-142 | P2 | CONF | Paramètres projet / site (remplace les placeholders) | Proposée | AM-114 |
| AM-143 | P2 | SEC | Journal d'activité consultable (global et local) | Proposée | AM-101 |
| AM-150 | P2 | DOC | Mettre `docs/03` et `docs/04` en cohérence avec le modèle réel | Proposée | AM-112 |
| AM-160 | P1 | UI | Charte couleurs et typographie Web + mobile (lot a, audit `06`) | **Livrée** (01/10) | — |
| AM-161 | P1 | UI | Structure commune Admin Projet : menu 4 entrées, barre supérieure, navigation mobile, masquages V1 (lot b) | **En recette** (01/10) | AM-160 |
| AM-162 | P1 | STRUCT | Fiche FOSA complète, Form Request commun, tableau de bord, export Excel (lot c1) | Validée (DEC-08, 09, 11) | AM-161 |
| AM-163 | P1 | UI | Écrans Web Admin Projet 01–04 (lot c2) | Validée | AM-162 |
| AM-164 | P1 | UI | Écrans mobiles Admin Projet 05–08, tablette (lot c3) | Validée | AM-162 |
| AM-165 | P1 | SYNC | Hors ligne Admin Projet : conflits, opérations en échec, chiffrement local (lot d) | Validée (DEC-10 à préciser) | AM-164 |
| AM-166 | P1 | QA | Tests et scénarios de l'interface Admin Projet (lot e) | Validée | AM-165 |
| AM-170 | P1 | CONF | Configuration Mission — Créer un projet (bailleur unique, libellés, 1–12 mois, populations, « Ajouter un service ») | **Livrée** (01/10) | AM-114 |
| AM-171 | P1 | GOUV | Configuration Mission — Comptes : Admin Projet et Admin Coordination en lecture seule | **Livrée** (01/10) | — |

---

## Lot 0 — Stabilisation (prérequis)

### AM-100 · Contrat d'authentification · P0 · SEC

- **Contexte :** la spécification demande JWT ; le code utilise Sanctum (audit Phase 2, P0-01).
- **Travail :** faire trancher DEC-07. Si Sanctum est conservé : expiration des jetons, rotation au rafraîchissement, révocation à la déconnexion, amendement écrit de la spécification.
- **Critères d'acceptation :**
  - le contrat login / refresh / logout / me est documenté ;
  - un jeton expiré est refusé ; un appareil révoqué ne peut plus rafraîchir ;
  - tests API couvrant chacun de ces cas.

### AM-101 · Suite de tests au vert · P0 · QA

- **Contexte :** 39 échecs au 23/09 ; 6 échecs mesurés le 30/09 (259 tests), tous liés au menu Admin Projet (voir `05-audit-technique-v1.md`, C-09).
- **Travail :** classer chaque échec (régression, fixture obsolète, ancien rôle, test à réécrire), corriger, supprimer les dépendances aux anciens codes de rôle.
- **Critères d'acceptation :**
  - `php artisan test` : 0 échec, 0 erreur ;
  - `flutter analyze` sans erreur ; `flutter test` termine et réussit ;
  - aucun test désactivé sans justification écrite.

### AM-102 · Commit de la refonte UI · P0 · UI

- **Contexte :** environ 50 fichiers modifiés et 8 nouveaux (CSS/JS design system, notifications) non commités depuis le 08/09.
- **Travail :** relire le diff, commits thématiques (design system, sidebar/topbar, dashboards, notifications, mobile), exclure `.pub-cache`, `.gradle` et `build/` via `.gitignore`.
- **Critères d'acceptation :**
  - `git status` propre sur `backend/` et `mobile/lib/` ;
  - AM-053 à AM-056 passent au statut *Livrée* dans le registre ;
  - `npm run build` et compilation Blade réussis.

### AM-103 · Recette visuelle des rôles de site · P1 · QA

- **Critères d'acceptation :** parcours Admin Site et Utilisateur Site exécutés sur Web et Android, captures archivées, anomalies ouvertes en tant qu'entrées AM.

### AM-104 · Synchronisation complète · P1 · SYNC

- **Contexte :** base Drift et idempotence présentes ; conflits et curseurs à confirmer (P0-05).
- **Critères d'acceptation :**
  - deux appareils hors ligne sur le même site ne créent aucun doublon ;
  - un conflit est enregistré, visible et résoluble ;
  - la reprise après coupure réseau ne rejoue aucune opération déjà acceptée.

---

## Lot 1 — Modèle projet

### AM-110 · Fiche projet enrichie · P1 · CONF

- **Source :** RG-PRJ-02, RG-PRJ-03, analyse § 3.1.
- **Travail :**
  - `projects` : `implementing_partner` (texte), `responsible_user_id` (nullable), `status` (`draft`, `active`, `suspended`, `closed`) ;
  - `project_donors.donor_project_code`, `program_project.moh_code` ;
  - `is_active` conservé et synchronisé avec `status` pendant la transition ;
  - formulaires Web (Coordination), API, affichage mobile.
- **Critères d'acceptation :**
  - seul l'Admin Coordination modifie ces champs ; l'Admin Projet les voit ;
  - un changement de statut est audité ;
  - un projet `closed` n'accepte plus de nouvelle transaction de stock ;
  - migration réversible, testée sur SQLite et PostgreSQL.

### AM-111 · Niveaux de soins hiérarchiques · P1 · REF

- **Source :** RG-PRJ-05, RG-PRJ-06.
- **Bloquée par :** DEC-01 (profondeur), DEC-02 (programme clinique vs programme de financement).
- **Travail :**
  - `catalog_references.parent_id` pour le type `care_level` (et `program_clinical` si DEC-02 le confirme) ;
  - service de validation : pas de cycle, profondeur maximale, parent du même type et de la même organisation ou global ;
  - arborescence dans l'écran Référentiels (ajout d'un niveau, d'une catégorie, d'un programme) ;
  - jeu de données initial : Soins de santé primaire / secondaire ; Programmes PEC VIH, Paludisme, Malnutrition, Tuberculose.
- **Critères d'acceptation :**
  - on peut ajouter un nouveau niveau de service sans intervention technique ;
  - l'archivage d'un nœud parent est refusé tant qu'il a des enfants actifs ;
  - les données existantes restent valides (nœuds racines).

### AM-112 · Configuration médicale du projet · P1 · CONF

- **Source :** RG-POP-01, RG-PAT-01, RG-ADP-01.
- **Travail :**
  - tables `project_care_levels`, `project_target_populations`, `project_pathologies`, `project_pathology_population` ;
  - écran Coordination « Configuration médicale » du projet (sélection des niveaux, populations, puis pathologies par population) ;
  - API en lecture pour l'Admin Projet, Admin Site et le mobile (projection SQLite).
- **Critères d'acceptation :**
  - aucune valeur codée en dur : toutes les options proviennent des référentiels ;
  - une pathologie ne peut être associée qu'à une population retenue par le projet ;
  - l'Admin Projet reçoit `403` sur toute écriture ;
  - modification auditée ; tests d'isolation inter-organisation.

### AM-114 · Approvisionnement obligatoire et historisé · P1 · CONF

- **Source :** RG-APP-01 à 04, DEC-06.
- **Travail :** table `project_supply_settings_history` (append-only) ; blocage du passage au statut `active` sans les trois paramètres ; les calculs de commande utilisent la valeur en vigueur à la date du calcul.
- **Critères d'acceptation :**
  - chaque modification crée une ligne d'historique avec auteur et date d'effet ;
  - une commande affiche les paramètres utilisés pour son calcul ;
  - listes déroulantes en mois identiques sur Web et mobile.

---

## Lot 2 — Génération des listes

### AM-113 · Liste standard dérivée de la configuration projet · P1 · REF

- **Source :** RG-PAT-02, analyse § 3.2.
- **Travail :**
  - `StandardListGenerationService` lit la configuration du projet (AM-112) au lieu d'un contexte ponctuel ;
  - support de plusieurs niveaux de soins, avec héritage des correspondances d'un niveau parent ;
  - instantané relationnel du contexte à la publication ; dépréciation des colonnes JSON de `standard_list_versions` ;
  - affectation de la version publiée aux sites du projet (`site_standard_lists`).
- **Critères d'acceptation :**
  - même configuration → même liste (déterministe, testé) ;
  - modifier la configuration projet signale la liste publiée comme « à régénérer » sans la modifier ;
  - un produit hors liste n'est pas dispensable sans autorisation explicite (MET-008).

---

## Lot 3 — Comptes

### AM-120 · Formulaire de compte en trois rubriques · P2 · GOUV

- **Critères d'acceptation :** rubriques *Informations personnelles*, *Identifiant et sécurité*, *Rôle et niveau d'accès* sur création, édition et fiche, Web et mobile ; si rôle = Admin Projet, seul le champ Projet (liste déroulante des projets du périmètre) est demandé.

### AM-121 · Limitations de modification des comptes · P1 · GOUV

- **Bloquée par :** DEC-03.
- **Critères d'acceptation :** matrice champ × rôle éditeur documentée et appliquée côté serveur ; toute tentative hors matrice renvoie `403` et est auditée.

### AM-122 · Contexte projet automatique · P1 · GOUV

- **Source :** RG-CPT-04.
- **Critères d'acceptation :**
  - aucune page de l'Admin Projet ne propose de sélecteur de projet ;
  - l'API déduit le projet du scope ; un `project_id` différent dans la requête renvoie `404` ;
  - test automatisé parcourant les routes accessibles à l'Admin Projet.

---

## Lot 4 — Supervision projet

### AM-130 · « Mon projet » complet · P1 · UI

- **Source :** analyse § 3.5, recommandation 7 de l'analyse fonctionnelle.
- **Contenu de l'écran (Web et mobile, lecture seule) :**

| Section | Données |
|---|---|
| Informations générales | Nom, code, mission, organisation, partenaire de mise en œuvre, pays, description, responsable, dates, statut |
| Configuration médicale | Liste standard publiée (version), niveaux de soins, populations, pathologies, nombre de produits autorisés |
| Approvisionnement | Périodicité, délai de livraison, stock de sécurité (+ date de dernière modification) |
| Opérationnel | Nombre de FOSA, de membres, de bénéficiaires, de dispensations ; valeur/quantité de stock ; alertes actives ; accès aux rapports |

- **Critères d'acceptation :** données issues d'une seule API scopée ; disponible hors ligne à partir de la dernière synchronisation avec horodatage visible.

### AM-131 · Fiche FOSA de supervision · P1 · STRUCT

- **Bloquée par :** DEC-04 (terme Bénéficiaires/Patients), DEC-05 (périmètre Admin Site).
- **Structure :** Projet → FOSA → onglets *Informations*, *Responsable*, *Équipe* (Administrateur FOSA, médecins, infirmiers, agents), *Bénéficiaires*, *Pathologies*, *Médicaments*, *Stock*, *Dispensations*, *Rapports*.
- **Critères d'acceptation :** l'Admin Projet voit uniquement les FOSA de son projet ; les données patient sont minimisées (pas de pièce jointe d'ordonnance sans droit explicite, DAT-004) ; aucune action d'écriture sur les transactions de la FOSA.

### AM-132 · Indicateurs agrégés par projet · P2 · RAPP

- **Critères d'acceptation :** endpoint unique `/api/v1/projects/{id}/overview` ; chiffres rapprochés des données sources dans un test.

---

## Lot 5 — Modules provisoires

| ID | Écran actuel | Cible |
|---|---|---|
| AM-140 | `/reports` → placeholder | Rapports prioritaires `docs/01` (stock, consommation, réception, inventaire, commandes), export PDF/Excel |
| AM-141 | `/synchronization` → placeholder | État par appareil/site, file en attente, conflits, dernière synchronisation |
| AM-142 | `/project-settings`, `/site-settings`, `/settings` → placeholder | Paramètres opérationnels du périmètre, lecture seule pour la configuration structurante |
| AM-143 | `/activity-log`, `/activity-log-local` → placeholder | Consultation filtrée du journal d'audit selon le scope |

---

## Documentation

### AM-150 · Cohérence de la documentation de référence · P2 · DOC

- `docs/03` : remplacer `project_supply_settings` par les colonnes réelles + historique ; ajouter les tables de AM-112.
- `docs/04` : ajouter la matrice de la section 5 de l'analyse.
- `docs/06` : reporter DEC-01 à DEC-07 et corriger l'encodage du fichier (caractères `Ã©`).

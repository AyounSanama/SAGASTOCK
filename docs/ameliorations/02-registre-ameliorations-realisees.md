# Registre des améliorations réalisées

Historique reconstitué le 30/09/2026 à partir de l'historique Git (branche
`master`), des migrations Laravel et des rapports d'audit `docs/09` à `docs/23`.
Les entrées suivantes seront ajoutées au fil des livraisons.

> **Statut « Livrée »** signifie commitée. Les travaux présents dans l'arbre de
> travail mais non commités sont listés à part, section 9.

---

## Synthèse par lot

| Lot | Date | Commit | Thème | Entrées |
|---|---|---|---|---|
| 1 | 27/07/2026 | `775274d` | Socle, authentification, cadrage | AM-001 → AM-006 |
| 2 | 28/07/2026 | `8bd18e4` | Hiérarchie organisationnelle, catalogue, stock | AM-007 → AM-013 |
| 3 | 05/08/2026 | `fc89413` | Gouvernance des cinq rôles, assistant, dispensation | AM-014 → AM-019 |
| 4 | 12/08/2026 | `8260b6c` | Workflows métier : dispensation, inventaire, commandes | AM-020 → AM-024 |
| 5 | 24/08/2026 | `b22e1f5` | Module Admin Sago et standards plateforme | AM-025 → AM-031 |
| 6 | 31/08/2026 | `56be0a9` | Interfaces mobiles, configuration Coordination/Projet | AM-032 → AM-038 |
| 7 | 02/09/2026 | `46e8f18` | FOSA, patients, ordonnances | AM-039 → AM-044 |
| 8 | 07/09/2026 | `e2e9b56` | Espace utilisateur FOSA, offline, notifications | AM-045 → AM-052 |
| 9 | 08–23/09/2026 | `c440807`, `7a3c584` | Refonte UI Web (Tailwind) et mobile | AM-053 → AM-056 |
| 10 | 30/09/2026 | `bbbf9f1` → `d42c7ea` | Stabilisation : tests au vert, commit de la refonte | AM-101, AM-102 |
| 11 | 30/09/2026 | `bce20dd`, `126aa74` | Configuration du projet (cahier des charges Mission / Projet) | AM-110 → AM-114 |
| 12 | 30/09 → 01/10/2026 | `193a7bd` → `7c45e25` | Charte couleurs et typographie (lot a), corrections de recette | AM-160 |
| 13 | 01/10/2026 | `a3db199` | Configuration Mission : Créer un projet et Comptes de la Coordination | AM-170, AM-171 |
| 14 | 01/10/2026 | `bc38cba` | Structure Admin Projet et masquage des fonctions EN TROP (lot b) | AM-161 |

---

## Lot 1 — Socle et authentification · 27/07/2026 · `775274d`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-001 | DOC | Documents de cadrage : exigences consolidées, architecture, modèle de données, rôles, MVP, décisions ouvertes |
| AM-002 | SEC | API Laravel versionnée `/api/v1` avec authentification Sanctum ; login Web et mobile uniques |
| AM-003 | SEC | Politique de mot de passe, oubli et réinitialisation par e-mail (SMTP, `docs/08`) |
| AM-004 | SEC | Verrouillage temporaire après échecs de connexion, comptes actifs/inactifs |
| AM-005 | SEC | Enregistrement et révocation des appareils ; journal d'audit (`AuditService`) |
| AM-006 | UI | Application Flutter initiale : splash, connexion, profil, changement de mot de passe, tableau de bord |

## Lot 2 — Hiérarchie organisationnelle et catalogue · 28/07/2026 · `8bd18e4`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-007 | CONF | Organisations, pays et missions ; projets rattachés à une mission |
| AM-008 | CONF | Bailleurs et programmes, association aux projets avec montant, devise et référence d'accord |
| AM-009 | STRUCT | Formations sanitaires, départements, pharmacies et sites de dispensation |
| AM-010 | REF | Référentiels paramétrables (catégories, unités, formes, dosages, voies, pathologies, populations, protocoles, niveaux de soins), produits, codes, lots, kits |
| AM-011 | REF | Listes standards versionnées avec publication contrôlée ; import/export CSV et XLSX |
| AM-012 | STOCK | Registre de mouvements immuable, soldes projetés, blocage du stock négatif, mouvements compensatoires, FEFO, transferts, quarantaines |
| AM-013 | STOCK | Réceptions avec quantités commandées, reçues, acceptées et rejetées ; logo officiel PharmaCare |

## Lot 3 — Gouvernance et assistant · 05/08/2026 · `fc89413`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-014 | GOUV | Migration vers les cinq rôles officiels (`GovernanceService`), création strictement descendante |
| AM-015 | GOUV | Délégation de permissions opérationnelles par utilisateur (`permission_user`) |
| AM-016 | CONF | Assistant de configuration avec machine d'état par étape et par périmètre (`not_started` → `valid` / `needs_correction`) |
| AM-017 | CONF | Centre de contrôle Web |
| AM-018 | DISP | Tables patients, ordonnances, dispensations et lignes de dispensation |
| AM-019 | GOUV | Middleware de permission et navigation dérivée des droits (`ApplicationNavigationService`) |

## Lot 4 — Workflows métier · 12/08/2026 · `8260b6c`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-020 | DISP | Workflow clinique complet : ordonnance, validation, dispensation, retour |
| AM-021 | INV | Workflow d'inventaire : création, démarrage, comptage, soumission, validation, export |
| AM-022 | CMD | Commandes de réapprovisionnement avec circuit d'approbation et préparation |
| AM-023 | GOUV | Application stricte des rôles PharmaCare ; accès géographique des organisations |
| AM-024 | UI | Écrans Web et mobile correspondants (dispensation, inventaires, commandes) |

## Lot 5 — Module Admin Sago · 24/08/2026 · `b22e1f5`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-025 | GOUV | Frontière de la plateforme Sago (`EnforceSagoPlatformBoundary`) ; Admin Sago limité au domaine plateforme |
| AM-026 | REF | Standards plateforme versionnés et affectations aux organisations, avec historique et restauration |
| AM-027 | REF | Référentiel pays complet ISO 3166 |
| AM-028 | SYNC | Suivi de synchronisation de la configuration effective des organisations |
| AM-029 | SYNC | Clés d'idempotence des requêtes API (`api_idempotency_keys`) |
| AM-030 | CONF | Langues additionnelles par organisation |
| AM-031 | UI | Bibliothèque de composants Blade partagés (badge, breadcrumb, bouton, carte, tableau, panneau dashboard) |

## Lot 6 — Interfaces mobiles et configuration · 31/08/2026 · `56be0a9`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-032 | GOUV | Admin Coordination rattaché à une mission |
| AM-033 | REF | Génération de liste standard par projet selon niveau de soins, catégorie de FOSA, populations, pathologies et examens (`StandardListGenerationService`, `product_standard_mappings`) |
| AM-034 | CONF | Paramètres d'approvisionnement du projet : périodicité, délai de livraison, stock de sécurité |
| AM-035 | GOUV | Retrait des droits d'écriture de configuration à l'Admin Projet (conformité V1) |
| AM-036 | UI | Langue préférée par utilisateur |
| AM-037 | SEC | Politique de mot de passe unifiée Web/API/mobile (`PasswordPolicy`) |
| AM-038 | UI | Écrans mobiles scopés : missions, projets, financements ; configuration de projet |

## Lot 7 — FOSA · 02/09/2026 · `46e8f18`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-039 | DISP | Patients et pièces jointes d'ordonnance rattachés à la formation sanitaire |
| AM-040 | DISP | Saisie de l'ordonnance au moment de la dispensation |
| AM-041 | REF | Conditionnement des produits |
| AM-042 | UI | Écran mobile « Mon projet » (informations générales, bailleurs, programmes, approvisionnement) |
| AM-043 | SEC | Stockage privé des pièces jointes sur mobile |
| AM-044 | SYNC | Service d'opérations hors ligne et amorçage de la synchronisation ; périmètre de session mobile |

## Lot 8 — Espace utilisateur FOSA · 07/09/2026 · `e2e9b56`

| ID | Domaine | Amélioration |
|---|---|---|
| AM-045 | SYNC | Base locale Drift (entités locales, opérations hors ligne, états de synchronisation) et dépôt « local-first » |
| AM-046 | SYNC | Idempotence liée à la requête et à l'autorisation |
| AM-047 | ALERT | Notifications opérationnelles (table, API, écran mobile) |
| AM-048 | STRUCT | Localisation structurée des formations sanitaires |
| AM-049 | SEC | En-têtes de sécurité HTTP (`AddSecurityHeaders`) |
| AM-050 | RAPP | Rapports opérationnels (API et écran mobile) |
| AM-051 | UI | Écrans mobiles réceptions, inventaires, commandes, FOSA scopées, utilisateurs scopés |
| AM-052 | SEC | Politique de mot de passe côté mobile |

## Lot 9 — Refonte des interfaces · 08–23/09/2026 · `c440807`, `7a3c584`

| ID | Domaine | Amélioration | Référence |
|---|---|---|---|
| AM-053 | UI | Tokens Flutter/Web harmonisés, fenêtre de formulaire centrée, garde de brouillon, sidebar Flutter réductible | `docs/21` |
| AM-054 | UI | Workflow FOSA en quatre étapes (Web et mobile) | `docs/21` |
| AM-055 | UI | Refonte Web Tailwind : design system, sidebar, topbar, dashboards, 76 vues inventoriées | `docs/22`, `docs/23` |
| AM-056 | ALERT | Centre de notifications Web (liste, lecture, lecture globale, badge) | `docs/23` |

## Lot 10 — Stabilisation · 30/09/2026

| ID | Domaine | Amélioration | Commit · Tests |
|---|---|---|---|
| AM-101 | QA | Suite de tests au vert : menu Admin Projet ramené au contrat V1 (retrait de Stocks, Réceptions, Dispensations, Médicaments, Sites, Bénéficiaires ajoutés hors contrat) ; modules masqués de nouveau refusés en 403 | `691c4b8` · PHPUnit 259/259 (1 984 assertions), `flutter test` 73/73, `flutter analyze` 0 problème |
| AM-102 | UI | Refonte UI Web/mobile commitée en lots thématiques ; `.pub-cache` (13 813 fichiers) et caches locaux retirés du suivi Git | `bbbf9f1`, `c440807`, `7a3c584`, `d42c7ea` · `npm run build` réussi |

---

## Lot 11 — Configuration du projet · 30/09/2026

Source : spécifications « Créer un projet » (analyse `01`, règles RG-PRJ, RG-POP,
RG-PAT, RG-APP). Décisions appliquées : DEC-01 (3 niveaux), DEC-02 (programmes
cliniques distincts des programmes de financement), DEC-06 (paramètres
obligatoires pour un projet actif).

| ID | Domaine | Amélioration | Tests |
|---|---|---|---|
| AM-110 | CONF | Fiche projet enrichie : organisation / programme de mise en œuvre, code bailleur, code programme MoH, responsable et contact, statut (Brouillon, Actif, Suspendu, Clôturé ; `is_active` dérivé). **Un seul Form Request `SaveProjectRequest` pour le Web et l'API** (corrige en partie M-01) ; premier Admin Projet facultatif partout. Formulaires Web et mobile, écran « Mon projet » | `ProjectIdentityDetailsTest` (5) |
| AM-111 | REF | Niveaux de soins hiérarchiques Niveau → Catégorie → Programme (`parent_id`, `depth`) ; règles serveur : même type, organisation ou référentiel global, pas de cycle, 3 niveaux maximum, pas d'archivage d'un parent actif, pas de déplacement d'un nœud qui a des enfants. Référentiel global initial du cahier des charges (SSP / SSS → Programmes PEC VIH, Paludisme, Malnutrition, Tuberculose). Onglet Web « Référentiel médical » et API `/projects/medical-references` | `CareLevelHierarchyTest` (4) |
| AM-112 | CONF | Configuration médicale du projet : niveaux de soins, populations cibles, pathologies associées à chaque population (tables relationnelles `project_care_levels`, `project_target_populations`, `project_pathology_populations`). Écriture Coordination uniquement ; lecture Admin Projet. Ajout de populations et pathologies au référentiel. Web + mobile (éditeur Coordination, section « Mon projet ») | `ProjectMedicalConfigurationTest` (4) |
| AM-113 | REF | Génération de la liste standard préremplie depuis la configuration du projet ; héritage hiérarchique (parents et programmes) ; indicateur « Liste à régénérer » quand la configuration change après publication | `ProjectStandardListFromConfigurationTest` (3) + `ProjectStandardListTest` (non-régression) |
| AM-114 | CONF | Périodicité, délai de livraison et stock de sécurité **obligatoires pour un projet actif** ; historique append-only `project_supply_settings_history` (valeurs, auteur, date d'effet), initialisé avec les valeurs existantes | `ProjectSupplySettingsTest` (2) ; 5 tests existants mis à jour pour fournir les paramètres |

**Changements de comportement à connaître**

- Créer ou activer un projet **Actif** sans les trois paramètres d'approvisionnement est refusé (utiliser le statut Brouillon en attendant).
- La section « Admin Projet » du formulaire de création est désormais facultative sur le Web, comme sur le mobile.
- L'écriture de la configuration médicale et du référentiel médical se fait en ligne uniquement ; la lecture est disponible hors ligne (cache local) sur mobile.

**Reste à faire (hors lot)** : blocage des transactions de stock pour un projet Clôturé ; troisième chemin de création de projet via l'assistant de configuration (M-01, lot L6) ; remplacement progressif des colonnes JSON de `standard_list_versions`.

## Lot 12 — Charte couleurs et typographie · 30/09/2026 · en recette

Source : prompt « Nouvelle interface Admin Projet » (audit `06`, lot a). Teinte
forte choisie par le porteur : **#B85D00** (option B de la planche de
comparaison).

| ID | Domaine | Amélioration | Tests |
|---|---|---|---|
| AM-160 | UI | Jetons uniques Web (`design-system.css`) et Flutter (`app_tokens.dart`) : marque `#F57C00` (indicateurs, soulignements, focus, sélection, texte actif du menu sombre), teinte forte `#B85D00` (boutons à texte blanc, bouton « + », tout texte orange sur fond clair), survol `#9C4F00` (plus foncé) ; neutres `#F5F5F3` / `#FFFFFF` / `#E3E3E0` / `#1C1F23` / `#5F6368` ; quatre couleurs d'état, jamais orange ; menu latéral sombre `#1E2329` (232 px). Oranges parasites supprimés (`#FF7A00`, `#FF9C2A`, `#B85C00`, `#C86500`, `#A65000`). Couleurs codées en dur des vues Blade remplacées par les jetons (733 occurrences, `welcome.blade.php` exclu). Police Inter embarquée dans l'application mobile (licence OFL) et appliquée à tous les styles de composants ; champs à 15 px | `theme_charter_test.dart` (11 : contrastes, rôles, pastilles, badges) ; suites complètes backend et Flutter |

**Corrections découvertes pendant le lot**

- Le sélecteur de langue et le bouton de thème de la barre supérieure recevaient le fond orange de la règle générique des boutons : ils sont neutres, la langue active est en teinte forte (l'ancien bleu `#4563F5` est retiré).
- Le bouton « afficher le mot de passe » du formulaire de compte apparaissait comme un bouton d'action orange : icône neutre.
- Les libellés des pastilles (`FilterChip`) mobiles s'affichaient en blanc (couleur non résolue) : couleur d'état explicite.
- Les boutons et titres mobiles retombaient sur Roboto (styles de composants sans famille) : Inter partout.
- Badges d'état « actif » rendus en gris par la règle globale : classes explicites (`active`, `inactive`, `off`…) raccordées aux couleurs d'état ; « inactif » n'est plus orange.

**Numérotation** : le plan de l'audit `06` utilisait AM-120 → AM-126, déjà attribués ; il est renuméroté AM-160 → AM-166.

**Restant (lots suivants)** : barre supérieure trop large sur téléphone (lot b) ; onglets du catalogue visibles sur la Liste Standard de l'Admin Projet (lot c2) ; couleurs de catégories des graphiques SAGO conservées (données, pas des états).

### Corrections de recette du lot a (01/10/2026)

| Point | Résultat |
|---|---|
| Bordure noire du « + » mobile | Artefact du test Flutter (`debugDisableShadows` remplace les ombres par un contour) ; capture refaite avec ombres réelles. Aucune modification de l'application. |
| Slogan de connexion | Traduit FR/EN sur le Web (page de connexion entière) et le mobile ; la dernière langue choisie est mémorisée (cookie Web, stockage sécurisé mobile). |
| Textes orange | Audit automatisé de toutes les pages des rôles Admin Projet, Coordination, Admin Site, Utilisateur Site : plus aucun texte `#F57C00` sur fond clair. |
| Mode sombre | Masqué par `pharmacare_v1.features.dark_mode` (code conservé, choix mémorisé ignoré). |
| Lancement | Scripts `serve-demo.ps1` / `stop-demo.ps1` (base de test isolée uniquement) ; adresse du serveur configurable sur mobile (`--dart-define` ou écran « Paramètres serveur » des builds de test). |

## Lot 13 — Configuration Mission (Admin Coordination) · 01/10/2026

Source : captures « Configuration Mission » validées par le porteur.

| ID | Domaine | Amélioration | Tests |
|---|---|---|---|
| AM-170 | CONF | Créer un projet : un projet par bailleur (code bailleur = projet) ; exemples de codes (GFFO5, FH4, PNLT) ; libellés « Périodicité de commande » et « Stock de sécurité » partout ; durées de 1 à 12 mois (Web, mobile) ; populations cibles par défaut (Adultes, Femmes enceintes, Enfants < 5 ans) dans le référentiel commun ; bouton « Ajouter un service » dans la configuration du projet (service propre à l'organisation) ; la création enchaîne sur la configuration de la Liste Standard | `ConfigurationMissionProjectTest` (4), `CareLevelHierarchyTest` |
| AM-171 | GOUV | Comptes créés par la Coordination : Admin Projet (projet choisi dans la liste des projets de la coordination) et Admin Coordination **en lecture seule** ; « Formation sanitaire » masqué (`pharmacare_v1.features.coordination_creates_site_admin`). Lecture seule appliquée côté backend : permissions d'écriture retirées et middleware `EnforceReadOnlyAccount` (403 sur toute écriture Web et API, sauf la gestion de son propre compte). Formulaire « Créer un compte » sur le Web (Ma Coordination) et le mobile ; badge « Lecture seule » | `ReadOnlyCoordinationAccountTest` (7), `GovernanceMatrixTest`, `V1ModuleAvailabilityTest`, `coordination_accounts_test.dart` |

**Changements de comportement à connaître**

- La Coordination ne peut plus créer de comptes de formation sanitaire (Admin Site) ; le Projet le fait (Équipe FOSA).
- Un projet ne peut plus être rattaché à plusieurs bailleurs : un projet existant qui en a plusieurs devra n'en garder qu'un à sa prochaine modification.
- Six tests existants encodaient l'ancienne règle (« la Coordination ne crée pas de comptes ») ; ils sont mis à jour selon la spécification validée, sans relâcher les autres contrôles.

## Lot 14 — Structure Admin Projet et masquages V1 (lot b) · 01/10/2026

Source : audit `07` (section 2) et décisions du 01/10.

| Élément | Réalisation |
|---|---|
| Menu Admin Projet (Q-3) | 4 entrées : Tableau de bord, Projet & FOSA, Liste standard, Profil. FOSA et Comptes utilisateurs deviennent des onglets (routes conservées). Mobile : barre basse Accueil, FOSA, Liste, Profil. |
| Barre supérieure | Fil d'Ariane ONG / Coordination / Projet (ou FOSA), indicateur En ligne / Hors ligne, initiales sur deux lettres ; encart Coordination en bas du menu latéral. |
| EN TROP masqués | Onglets du catalogue sur la Liste Standard (rôles V1) ; filtres Organisation et Mission des FOSA (Admin Projet) ; écriture du catalogue produits et des référentiels pour l'Admin Projet (API refusée) ; menu et page « Produits » des FOSA (API catalogue conservée pour le mobile) ; validation clinique des ordonnances (V4) ; destination « Communauté » (historique visible). |
| Validation clinique masquée | Les ordonnances sont directement dispensables. ~~Créées « validées »~~ : remplacé au lot 15 par le statut distinct « Validation non requise (V1) ». Les tests du code conservé réactivent la fonction. |
| **Correction de sécurité** | Le middleware V1 est global et s'exécutait avant l'authentification Sanctum. Sur un vrai appel mobile avec jeton, **les restrictions V1 de l'API ne s'appliquaient pas** (les tests ne le voyaient pas, car `Sanctum::actingAs` authentifie avant). Le jeton est maintenant résolu explicitement ; un test utilise un vrai jeton. |

Tests : `V1AdminProjectStructureAndMaskingTest` (6), `mobile_navigation_test` ; tests existants alignés sur Q-3 et P-07.

## Lot 15 — Correctifs du point A et fiche FOSA (c1) · 01/10/2026

### Point A — `b2ce968`

| Élément | Réalisation |
|---|---|
| Statut des ordonnances V1 | Statut distinct `validation_not_required` (« Validation non requise (V1) ») : dispensable, mais jamais présenté comme validé cliniquement. Commande `pharmacare:prescriptions:fix-v1-status {--dry-run}` pour corriger les ordonnances « validées » sans validateur (base de test : 0). |
| Ordre des middlewares | Restrictions V1, frontière Sago et lecture seule appliquées aux vrais jetons mobiles (trait `ResolvesAuthenticatedUser`). `RealTokenRoleMatrixTest` (7) appelle l'API avec un vrai jeton pour chaque rôle. |
| Rapport base réelle | `backend/tools/rapport-lecture-seule.php` (SQLite ouvert en lecture seule + `PRAGMA query_only`). Pas encore lancé sur la base réelle : on attend la sauvegarde du porteur. |

### AM-162 — Fiche FOSA complète (lot c1)

| Élément | Réalisation |
|---|---|
| Statut de validation | `validation_status` : en attente, validée, refusée, suspendue. FOSA existantes → validées. Une FOSA déclarée par l'Admin Projet est « en attente » ; une FOSA refusée et corrigée repasse « en attente ». Les écrans de validation (Coordination) arrivent au lot c1-bis. |
| Classification | Catégorie (5 par défaut : HR, HD, CMA, CSI, CSA), niveau de soins, populations et pathologies **limités à la configuration validée du projet** (refus serveur sinon). Endpoint `GET …/facilities/options`. |
| Approvisionnement (DEC-08) | Périodicité, DL, stock de sécurité (0,25 ; 0,5 ; 0,75 ; 1 ; 1,5 ; 2 mois) et 3 dates. Préremplis depuis le projet à la création ; historisés. Un changement du projet n'écrase jamais les FOSA, sauf action explicite de la Coordination avec confirmation (`POST /projects/{id}/supply-settings/apply-to-facilities`). |
| Liste Standard de la FOSA | `GET …/facilities/{id}/standard-list` : liste générée par sa propre configuration, limitée aux produits publiés du projet. |
| Comptes FOSA | L'Admin Projet crée l'Admin Site **et** l'Utilisateur Site. Refus (422) tant que la FOSA n'est pas validée. Site principal invisible créé automatiquement (DEC-05). |
| Type de projet | `donor_project` (défaut) ou `national_program` (« Programme national », bailleur facultatif). |
| Écrans | Web : champs V1 dans les formulaires FOSA de l'Admin Projet (mise en page définitive au lot c2). Mobile : l'étape « Classification » de l'assistant FOSA est remplacée pour l'Admin Projet ; choix mis en cache pour la déclaration hors ligne. Les autres rôles gardent leur formulaire. |

Tests : `HealthFacilityConfigurationTest` (8). Suite backend complète : 316 tests OK. Mobile : `flutter analyze` sans remarque, 91 tests OK.

**Changements de comportement à connaître**

- L'Admin Projet doit renseigner niveau, catégorie, au moins une population et une pathologie ; le type de FOSA est déduit de la catégorie.
- Quatre tests existants encodaient l'ancienne règle (FOSA sans classification, Admin Projet limité à l'Admin Site) ; ils passent maintenant par la configuration du projet et la validation (helper `Tests\Support\V1FacilityFixtures`).
- Le stock de sécurité du **projet** reste un entier tant que le rapport R1 sur la base réelle n'a pas été lu.

## Lot 16 — Gestion des comptes, rapports base réelle, audit de sécurité · 01/10/2026

| Élément | Réalisation |
|---|---|
| Comptes (correction validée) | Un compte sans rôle officiel ne crée ni ne modifie plus aucun compte (API et Web). Admin Site et Utilisateur Site jamais à la plateforme. Personne n'attribue un rôle supérieur au sien, ni à soi-même. Modifier, réinitialiser, archiver ou restaurer un compte : seulement un compte de rang inférieur, dans son périmètre, jamais le sien (`GovernanceService::canManageUser`). |
| Tests | `AccountHierarchyTest` (5). Les 6 fichiers qui utilisaient un ancien rôle personnalisé passent sur des rôles officiels : Admin Coordination (utilisateurs, projets, financements, catalogue) et Admin Sago (organisations). Les écrans masqués en V1 sont testés avec le masquage désactivé, ce qui est commenté dans chaque test. Suite backend : 321 tests OK. |
| Rapports base réelle | R1 à R6 en lecture seule (`backend/tools/rapport-lecture-seule.php`) ; base réelle inchangée (date et empreinte vérifiées). |
| Mobile | Adresse de développement d'`app_config` : `http://192.168.137.1:8000/api/v1` (point d'accès PC-SERGE). |
| Sécurité | Audit `09-audit-securite.md` (2 critiques, 5 élevées) ; vérification de sécurité ajoutée au modèle de lot. |

**À traiter (constatés pendant ce lot)**

- « Configuration des projets » (Web, Coordination) : ses formulaires visent `/organizations/...`, que le masquage V1 bloque. Doublon à retirer (check-up des maquettes).
- Aucun rôle officiel n'a `catalog.manage` : la codification par la Coordination est à ouvrir au lot f (AM-173).

## Lot 17 — Corrections de sécurité critiques et élevées · 02/10/2026

Détail et état : `09-audit-securite.md` (section « État des corrections »). Étude S-02 : `10-etude-chiffrement-mobile.md`.

| Élément | Réalisation |
|---|---|
| S-01, S-04, S-05, S-07, S-09 | Corrigés, avec un test chacun (`SecurityHardeningTest`, 8 tests, vrais jetons) |
| S-03 | Jeton de 30 jours prolongé ; code PIN et verrouillage après 5 min ; opérations conservées si le jeton expire hors ligne |
| S-06 | HTTP réservé aux versions de test, HTTPS obligatoire en production |
| S-11 | Dépendances mises à jour, audits à 0 |
| Android | Sauvegarde automatique désactivée (données locales non chiffrées) |
| Tests existants | 16 tests utilisaient un ancien rôle personnalisé à la plateforme. Les tests stock passent par l'Admin Projet du projet de la FOSA (helper `OfficialModuleActor`), la sécurité par l'Admin Sago, les structures par un rôle limité à une organisation (à passer sur un rôle officiel avec les Policies). Les comptes créés avec un mot de passe temporaire sont d'abord bloqués (S-04), puis débloqués après le changement. |

Suites : backend 325 tests OK, mobile 98 tests OK, `flutter analyze` sans remarque.

## Niveau 1 — Modifications de la base réelle · 02/10/2026

Sur demande du porteur, après sauvegarde : serveurs arrêtés, deux copies, trois empreintes SHA256 identiques (`F5FECB4D02A27B50DD8F32B8745CB576C26D5C82E0B3BC2CD7C88A7490AAFC7B`) :
- `backend/database/database.backup-avant-rapports-20261001.sqlite` ;
- `D:/Sauvegardes/PharmaCare/database.backup-avant-rapports-20261001.sqlite`.

Script `backend/tools/correction-2026-10-02-r1-r8.php` : une transaction, comptes de lignes contrôlés, simulation préalable, chaque changement inscrit au journal d'audit.

| Point | Modification |
|---|---|
| R1 | PSM-2026 « PROJET Santé Maternelle » : stock de sécurité 4 → **2 mois** (1 ligne) |
| R8 | Programmes MSM01 01-H « HELP » (UNICEF, lié à TSIDA) et 37-S « SOS » : **archivés** (inactifs, masqués, non supprimés, liens conservés), non migrés |

Vérification en lecture seule : R1 vide, R8 « archivé ». Nouvelle empreinte de la base réelle : `e85697a9021f1b0d…`.

## Modèle d'entrée pour les prochaines livraisons

```markdown
## Lot N — <Thème> · JJ/MM/AAAA · `<commit>`

| ID | Domaine | Amélioration | Tests |
|---|---|---|---|
| AM-1NN | CONF | <description courte> | <suite exécutée, résultat> |
```

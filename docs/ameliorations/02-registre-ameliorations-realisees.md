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

## Modèle d'entrée pour les prochaines livraisons

```markdown
## Lot N — <Thème> · JJ/MM/AAAA · `<commit>`

| ID | Domaine | Amélioration | Tests |
|---|---|---|---|
| AM-1NN | CONF | <description courte> | <suite exécutée, résultat> |
```

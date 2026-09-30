# PHARMACARE UI/UX REFACTOR REPORT

Date : 22 septembre 2026.

## Périmètre livré

Refonte progressive du socle partagé et du pilote FOSA. La conformité globale
de tous les écrans n'est pas déclarée acquise : les parcours authentifiés dans
le navigateur et les parcours sur appareils physiques restent à effectuer.

- Tokens Flutter/Web harmonisés : orange existant conservé, couleurs
  sémantiques, rayons, espacements, largeur standard de formulaire 720 px.
- Thème typographique Flutter raccordé aux tokens existants.
- Fenêtre Flutter `PharmaCareCenteredFormSheet` centrale, limitée en hauteur,
  avec en-tête fixe, backdrop, fermeture accessible et API de footer fixe.
- Garde de brouillon raccordée aux formulaires FOSA, structures enfants/sites
  et utilisateurs. Fermeture X, retour, annulation et backdrop passent par
  cette garde ; une opération en cours empêche la fermeture sans résultat.
- Workflow FOSA Flutter à quatre étapes conservé. Les sauts vers des étapes
  ultérieures respectent les informations obligatoires précédentes.
- Actions fixes dans les formulaires de sites et utilisateurs Flutter.
- Sidebar Flutter réductible, extensible et compacte sur grandes tablettes,
  avec infobulles. Navigation mobile Admin Sago et FOSA ajustée aux rôles et
  aux entrées autorisées existantes.
- Composant Blade partagé centré sur toutes les tailles d'écran. CSS et JS
  des fenêtres sortis de la sidebar. Les actions historiques sont déplacées
  hors du corps défilant, avec conservation de leur association HTML au form.
- Formulaires de projet Web migrés vers ce composant.
- FOSA Web : quatre étapes dans la même fenêtre, validation native avant
  progression, résumé textuel, projets sélectionnables par cases à cocher.
- Aucun changement de contrôleur métier, de route API, de politique RBAC,
  de stockage Drift ou de moteur de synchronisation.

## Statuts d'acceptation

| Domaine | Statut | Limite |
| --- | --- | --- |
| Design System | PARTIAL | Socle harmonisé ; styles historiques encore présents |
| App Shell Web | PARTIAL | Réutilisé ; validation navigateur non réalisée |
| App Shell mobile | PARTIAL | Navigation adaptée ; contrôles sur appareil à faire |
| Centered Form Sheet | PARTIAL | Composant testé dans Flutter et Chrome ; généralisation restante |
| Admin Sago | PARTIAL | Propagation du socle, pas de parcours visuel complet |
| Admin Coordination | PARTIAL | Formulaire projet migré, pas de parcours visuel complet |
| Admin Project | PARTIAL | Tests API du pilote ; parcours UI complet non validé |
| FOSA opérationnel | PARTIAL | Navigation adaptée, workflows métier conservés |
| Responsive Desktop | PARTIAL | Composant testé Flutter/Chrome, dont 1366×768, 1440×900 et 1920×1080 |
| Responsive Tablet | PARTIAL | Composant testé Flutter/Chrome 800×1024 ; appareil non testé |
| Responsive Mobile | PARTIAL | Composant testé Flutter/Chrome 360×640 ; clavier réel non testé |
| Offline UI / Sync states | PARTIAL | Comportements existants conservés, E2E réel non réalisé |
| RBAC | PARTIAL | Tests ciblés de scope ; suite générale à interpréter séparément |
| Android physique | NON TESTÉ | Pas de validation sur appareil |
| iOS | NON TESTÉ | Pas d'environnement iOS utilisé |

## Vérifications exécutées

| Vérification | Résultat |
| --- | --- |
| `flutter analyze --no-pub` | PASS — aucune anomalie |
| Analyse ciblée des derniers fichiers Dart modifiés | PASS — aucune anomalie |
| `flutter test --no-pub` après reprise | PASS — 71 tests, 4 min 18 s |
| Tests ciblés après correction finale de la navigation Sago | PASS — 2 tests ; rôle explicite et respect du manifeste |
| Laravel : composants, entrées de création projet, isolation du scope, parcours FOSA/site/utilisateur | PASS — 11 tests, 132 assertions |
| Chrome sans interface : composant Blade réel | PASS — cinq résolutions, validation, résumé, footer et brouillon |
| `npm run build` | PASS — Vite, 55 modules transformés |

Les statuts PASS ci-dessus concernent ces vérifications précises. Ils ne
valident pas les autres critères d'acceptation indiqués PARTIAL/NON TESTÉ.

## Limites connues

Le navigateur intégré n'a pas pu démarrer : erreur d'environnement de l'outil
(`sandboxPolicy`). Un contrôle de remplacement dans Chrome sans interface a
testé le vrai composant Blade, avec ses CSS et JS, sur cinq résolutions. Il
vérifie les étapes, la validation, le résumé, le footer, le rattachement HTML
des actions et la garde de brouillon. La capture tablette a été inspectée.
Cela ne constitue pas un PASS du parcours Web authentifié avec sauvegarde API.

La garde Flutter doit encore être propagée aux autres formulaires historiques.
Le composant partagé centre leurs fenêtres, mais ne peut pas déduire leur
brouillon ou séparer automatiquement leurs actions internes. Les pages
administratives autonomes restantes nécessitent une migration par lots.

Les validations 422 restent à raccorder systématiquement sous chaque champ.
Les notifications, détails FOSA Web et parcours opérationnels complets restent
à vérifier visuellement. Aucune donnée de démonstration n'a été ajoutée.

## Fichiers créés

- `backend/public/css/pharmacare-forms.css`
- `backend/public/js/pharmacare-forms.js`
- `backend/tests/Browser/render_centered_sheet.php`
- `backend/tests/Browser/centered_sheet.mjs`
- `backend/tests/Browser/README.md`
- `mobile/test/mobile_navigation_test.dart`
- `docs/20-pharmacare-ui-audit.md`
- `docs/21-pharmacare-ui-refactor-report.md`

## Fichiers modifiés

- `backend/public/css/pharmacare-portal.css`
- `backend/resources/views/components/app-sidebar.blade.php`
- `backend/resources/views/components/form-sheet.blade.php`
- `backend/resources/views/projects/scope.blade.php`
- `backend/resources/views/structures/index.blade.php`
- `backend/tests/Feature/ProjectCreationEntryPointsTest.php`
- `mobile/lib/src/core/theme/app_tokens.dart`
- `mobile/lib/src/core/theme/app_theme.dart`
- `mobile/lib/src/core/widgets/app_form_sheet.dart`
- `mobile/lib/src/core/widgets/app_navigation_drawer.dart`
- `mobile/lib/src/core/widgets/main_navigation_shell.dart`
- `mobile/lib/src/features/home/presentation/home_page.dart`
- `mobile/lib/src/features/structures/presentation/facilities_page.dart`
- `mobile/lib/src/features/users/presentation/scoped_users_page.dart`
- `mobile/test/app_form_sheet_test.dart`

Les modifications de caches Gradle présentes au début de la session ne font
pas partie de la refonte.

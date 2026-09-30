# Rapport de refonte Web PharmaCare — 23 septembre 2026

La refonte raccorde les pages Blade à Tailwind 4 et Vite, centralise le shell et les composants, et rend le centre de notifications interne opérationnel. Laravel, les API mobiles, les modèles, les permissions, les scopes, Drift et la synchronisation ne sont pas remplacés. Les modifications locales antérieures sont conservées.

## Résultats et portée

Un PASS ci-dessous concerne les parcours réellement exécutés, pas toutes les combinaisons de données possibles. Les captures sont dans `.tmp/ui-screenshots/`, les mesures dans `.tmp/ui-browser-results.json` et les journaux dans `.tmp/ui-*-final.txt`.

| Contrôle | État | Preuve / limite |
| --- | --- | --- |
| Tailwind | PASS | Tailwind 4 compilé ; grilles responsive avec `@apply`, sources Blade/JS déclarées |
| Vite | PASS | Entrées CSS/JS chargées par `@vite`, une inclusion par page |
| App Shell | PASS | Parcours authentifiés Sago, Coordination et Projet |
| Sidebar | PASS | Navigation issue du service existant ; icônes, labels accessibles, menu mobile |
| Topbar | PASS | Profil séparant nom/e-mail, menu profil, centre de notifications |
| Dashboards | PASS | Trois dashboards administrateurs, captures à quatre résolutions |
| Alignement widgets | PASS | Grilles Tailwind ; KPI communs et hauteurs mesurées de 124 px dans les fixtures |
| Tableaux | PASS | Conteneurs défilables au clavier ; pages administratives testées à 1366 et 360 px |
| Formulaires | PASS | Tests Laravel de création Projet et contrôles de validation du formulaire FOSA |
| Centered Form Sheet | PASS | Cinq résolutions, centrage, hauteur, footer fixe, association des boutons et protection du brouillon |
| Notifications internes | PASS | Stockage Laravel réel, session Web, appartenance, pagination, états vide/erreur et actualisation |
| Badge non lu | PASS | Compteur réel ; disparition après lecture observée dans le navigateur |
| Lecture des notifications | PASS | Lecture individuelle et globale ; ID étranger refusé ; destination protégée |
| Responsive desktop | PASS | 1920×1080 et 1366×768, sur les parcours décrits ci-dessous |
| Responsive tablette | PASS | 800×1024 pour les trois dashboards ; formulaire également vérifié |
| Responsive petit écran | PASS | 360×740 pour les parcours administrateurs ; fenêtre également à 360×640 |
| RBAC/scopes après refonte | FAIL global / PASS ciblé | Des échecs historiques de périmètre subsistent ; les tests ciblés passent et aucune permission n'est élargie |
| Build Vite | PASS | `npm --prefix backend run build` |
| Tests Laravel | 185 PASS / 39 FAIL | Baseline : 182 PASS / 39 FAIL ; trois nouveaux tests passent ; mêmes 39 noms en échec |

## Vérifications effectuées

- Suite complète avant et après : `.tmp/ui-baseline-tests.txt` et `.tmp/ui-final-tests.txt`. Comparaison des noms en échec : aucun échec nouveau, aucun échec historique supprimé. La liste est annexée plus bas.
- Le rapport machine `.tmp/ui-final-junit.xml` confirme 21 erreurs et 18 assertions en échec. PHPUnit compte 255 exécutions car la suite « V1 Acceptance » rejoue 31 tests : 216 exécutions réussies et 39 en échec, correspondant aux 185 tests distincts réussis et 39 en échec affichés par Artisan.
- Suite ciblée : 18 tests, 253 assertions, zéro échec. Notifications Web/API, cycle inventaire, sessions, entrées de création Projet, disponibilité des modules et périmètres pays.
- Compilation des vues Blade et syntaxe PHP/JavaScript.
- Navigateur Chrome sans affichage, base SQLite isolée, connexions réelles des trois rôles administrateurs. Aucune exception JavaScript relevée dans les parcours.
- Ouverture du menu mobile et fermeture par Échap vérifiées avec Admin Projet ; le serveur de test est arrêté en fin d'intervention. Le serveur de développement préexistant n'est pas arrêté.
- Sago : dashboard, organisations, standards/référentiels, profil.
- Coordination : dashboard, coordination, projets, bailleurs/programmes, liste standard, profil.
- Projet : dashboard, mon projet, formations sanitaires, équipe FOSA, liste standard, profil.
- Notifications dans le navigateur : ouverture, liste réelle, lecture, badge, lecture globale, erreur réseau simulée et récupération par actualisation. Les lectures déjà effectuées restent persistées dans la base de test.
- Cycle métier testé séparément dans Laravel : validation d'inventaire → notification enregistrée pour son destinataire → centre Web → lecture → compteur zéro. Le scénario navigateur utilise des notifications de démonstration du même mécanisme de stockage ; il ne déclenche pas l'inventaire depuis l'interface.

## Changements et corrections fonctionnelles

1. La cloche utilisait `session('notification_count')` et n'ouvrait aucun centre. Les nouvelles routes `/profile/notifications` utilisent l'authentification Web et les relations du destinataire. Les routes API existantes ne changent pas. Le placement sous le profil respecte les modules déjà accessibles sans élargir leur liste ni les permissions métier.
2. La lecture d'un ID étranger renvoie 404. Les liens sont limités à des chemins locaux ; les destinations conservent leurs contrôles d'autorisation.
3. Des gestionnaires locaux fermaient directement les fenêtres et contournaient le contrôle des brouillons. La fermeture passe par le comportement commun. Les formulaires administratifs autonomes utilisateur, formation sanitaire et produit s'ouvrent dans le composant centré.
4. Le graphique catalogue comportait des proportions fixes sans données correspondantes. Il est supprimé. Les KPI proviennent toujours du service existant. Les cartes de stock, utilisateurs et organisations sont conditionnées à leurs permissions ; les raccourcis Coordination/Projet respectent également celles-ci.
5. Le libellé « conformité » du dashboard Sago représentait en réalité la proportion de configurations synchronisées : le libellé est corrigé sans modifier le calcul.
6. Nom/e-mail séparés dans la topbar et les cellules ; icônes de boutons protégées contre une règle de police trop générale ; suppression de la recherche décorative sans comportement.
7. Correction de débordements des grilles Standards et Coordination, des pistes implicites du layout et des marges mobiles des pages autonomes. Les contrôles attendent la fin des transitions avant de mesurer.
8. Le fil d'Ariane décodait mal les titres contenant `&` : décodage des entités avant l'échappement Blade, sans rendu HTML non échappé.
9. `AuthSessionTest` vérifie désormais l'absence des alertes stock pour un utilisateur sans permission stock. Ce changement d'attente correspond à la demande de ne pas afficher de widgets non autorisés ; ce n'est pas la suppression d'un test en échec historique.

## CSS historique et migration

- Toujours utilisés via Vite : `backend/public/css/pharmacare-portal.css` et `backend/public/css/pharmacare-forms.css`.
- Styles globaux extraits de la sidebar : `backend/resources/css/shell-compat.css`. Styles des dashboards centralisés dans `backend/resources/css/dashboard.css`. Tokens et règles communes dans `backend/resources/css/design-system.css`.
- Les styles locaux propres aux pages restent utilisés pour préserver leurs comportements. L'inventaire complet des 76 vues se trouve dans `22-pharmacare-tailwind-audit.md`.
- Aucun ancien fichier CSS supprimé. Les anciens liens directs du portail, de l'authentification et de la sidebar sont remplacés par le bundle. Les blocs CSS/JS embarqués dans la sidebar et les styles embarqués des dashboards sont déplacés, pas perdus.
- Pas de dépendance Bootstrap UI trouvée. `resources/js/bootstrap.js` initialise Axios. Pas de nouveau framework JS, pas de dépendance npm ajoutée ; les dépendances étaient déjà installées.
- Inter est demandé via Google Fonts, avec une pile de polices système de secours. L'absence de réseau vers ce fournisseur n'empêche pas l'usage de l'interface.

## Éléments non testés et limites

- **NON TESTÉ VISUELLEMENT** : rôles Admin Site et Utilisateur Site, anciennes pages opérationnelles hors des parcours ci-dessus, toutes les fiches de détail/archives et tous les états alimentés par de gros jeux de données.
- Les 76 vues ont été inventoriées statiquement ; cela ne signifie pas 76 pages inspectées visuellement. Les formulaires ne sont pas tous soumis depuis le navigateur.
- Les données de démonstration contiennent peu de lignes. Pagination des notifications et isolation sont testées côté Laravel ; un parcours navigateur dépassant vingt notifications n'a pas été exécuté.
- Appareils physiques, Safari/Firefox, lecteur d'écran et audit WCAG complet : NON TESTÉS.
- Push FCM externe : NON TESTÉ ; aucune affirmation de configuration ou de fonctionnement.
- Les 39 échecs historiques restent à traiter séparément : notamment fixtures avec contraintes uniques, attentes historiques de modules/scopes et assertions de code mobile. Cette refonte ne les masque pas.
- La migration garde une couche CSS de compatibilité et des styles locaux. Elle ne prétend pas avoir supprimé toute la dette CSS historique.

## Fichiers modifiés dans cette intervention

La liste ci-dessous exclut les changements mobile/caches, les documents 20/21, le CSS public du portail et `ProjectCreationEntryPointsTest.php`, qui étaient déjà modifiés avant cette intervention et n'ont pas été réécrits ici. Les fichiers de tests navigateur préexistants non modifiés sont aussi exclus.

- `backend/app/Http/Controllers/Web/NotificationController.php`
- `backend/public/js/pharmacare-forms.js`
- `backend/resources/css/app.css`
- `backend/resources/css/dashboard.css`
- `backend/resources/css/design-system.css`
- `backend/resources/css/shell-compat.css`
- `backend/resources/js/app.js`
- `backend/resources/js/notifications.js`
- `backend/resources/js/sidebar.js`
- `backend/resources/js/tables.js`
- `backend/resources/views/auth/forgot-password.blade.php`
- `backend/resources/views/auth/login.blade.php`
- `backend/resources/views/auth/reset-password.blade.php`
- `backend/resources/views/catalog/create-product.blade.php`
- `backend/resources/views/catalog/index.blade.php`
- `backend/resources/views/components/app-sidebar.blade.php`
- `backend/resources/views/components/assets.blade.php`
- `backend/resources/views/components/form-sheet.blade.php`
- `backend/resources/views/components/navigation/breadcrumb.blade.php`
- `backend/resources/views/components/navigation/topbar.blade.php`
- `backend/resources/views/components/notification-center.blade.php`
- `backend/resources/views/configuration/organizations.blade.php`
- `backend/resources/views/configuration/steps/mission.blade.php`
- `backend/resources/views/configuration/steps/organization.blade.php`
- `backend/resources/views/dashboard/home.blade.php`
- `backend/resources/views/dashboard/index.blade.php`
- `backend/resources/views/dashboard/sago.blade.php`
- `backend/resources/views/dispensations/index.blade.php`
- `backend/resources/views/inventories/index.blade.php`
- `backend/resources/views/layouts/portal.blade.php`
- `backend/resources/views/projects/index.blade.php`
- `backend/resources/views/projects/scope.blade.php`
- `backend/resources/views/receipts/index.blade.php`
- `backend/resources/views/receipts/show.blade.php`
- `backend/resources/views/security/index.blade.php`
- `backend/resources/views/setup/index.blade.php`
- `backend/resources/views/sites/index.blade.php`
- `backend/resources/views/stocks/create-movement.blade.php`
- `backend/resources/views/stocks/index.blade.php`
- `backend/resources/views/structures/create.blade.php`
- `backend/resources/views/structures/index.blade.php`
- `backend/resources/views/users/archived-show.blade.php`
- `backend/resources/views/users/archived.blade.php`
- `backend/resources/views/users/create.blade.php`
- `backend/resources/views/users/edit.blade.php`
- `backend/resources/views/users/show.blade.php`
- `backend/routes/web.php`
- `backend/tests/Browser/README.md`
- `backend/tests/Browser/pharmacare_ui.mjs`
- `backend/tests/Browser/ui_server.php`
- `backend/tests/Feature/Api/InventoryManagementTest.php`
- `backend/tests/Feature/AuthSessionTest.php`
- `backend/tests/Feature/Web/NotificationCenterTest.php`
- `docs/22-pharmacare-tailwind-audit.md`
- `docs/23-pharmacare-tailwind-report.md`

## Échecs historiques conservés

Les noms ci-dessous reprennent le journal Laravel, qui abrège certains intitulés longs.

```text
Tests\Feature\AdminCoordinationMissionOwnershipTest > mission widget and api are limited to coordination…    
Tests\Feature\AdminCoordinationMissionOwnershipTest > mission page and web crud use only authorized count…   
Tests\Feature\AdminCoordinationMissionOwnershipTest > coordination manages projects inside its mission       
Tests\Feature\AdminCoordinationMissionOwnershipTest > coordination creates multiple project admin users a…   
Tests\Feature\Api\AuthenticationTest > switching accounts on same device revokes previous identity and re…   
Tests\Feature\Api\CatalogArchiveAndNavigationTest > archived records are listed and can be restored          
Tests\Feature\Api\ConfigurationWorkflowTest > authenticated user can start and list independent workflows    
Tests\Feature\Api\ConfigurationWorkflowTest > user cannot read another users workflow                        
Tests\Feature\Api\ConfigurationWorkflowTest > draft is saved without sensitive fields                        
Tests\Feature\Api\InventoryModuleCompletionTest > mobile inventory route and offline outbox are real         
Tests\Feature\Api\SecurityAdministrationTest > authorized admin can m…  UniqueConstraintViolationException   
Tests\Feature\Api\SecurityAdministrationTest > web role workspace per…  UniqueConstraintViolationException   
Tests\Feature\Api\StructureManagementTest > complete structure lifecycle and module activation               
Tests\Feature\Api\StructureManagementTest > coordination admin never sees facilities of another organizat…   
Tests\Feature\Api\SupplyOrderModuleCompletionTest > web and mobile use real order workspaces                 
Tests\Feature\Api\SupplyOrderModuleCompletionTest > shared layout alignment and versioned logo are presen…   
Tests\Feature\Web\CompleteConfigurationWizardTest > steps are locked…   UniqueConstraintViolationException   
Tests\Feature\Web\CompleteConfigurationWizardTest > complete configur…  UniqueConstraintViolationException   
Tests\Feature\Web\CompleteConfigurationWizardTest > project archive i…  UniqueConstraintViolationException   
Tests\Feature\Web\ConfigurationHomeNavigationTest > home displays onl…  UniqueConstraintViolationException   
Tests\Feature\Web\ConfigurationHomeNavigationTest > organization and…   UniqueConstraintViolationException   
Tests\Feature\Web\ConfigurationHomeNavigationTest > return link prese…  UniqueConstraintViolationException   
Tests\Feature\Web\ConfigurationWorkflowEngineTest > each flow type creates an independent progression        
Tests\Feature\Web\ConfigurationWorkflowEngineTest > new project flow…   UniqueConstraintViolationException   
Tests\Feature\Web\ConfigurationWorkflowEngineTest > draft is persisted without password a…  ErrorException   
Tests\Feature\Web\ConfigurationWorkflowEngineTest > saved draft is restored when the step…  ErrorException   
Tests\Feature\Web\MissionConfigurationTest > step is available only a…  UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > valid mission is persist…  UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > validation rejects missi…  UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > saving again updates wit…  UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > inactive mission is save…  UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > mission can be archived…   UniqueConstraintViolationException   
Tests\Feature\Web\MissionConfigurationTest > a second mission can be…   UniqueConstraintViolationException   
Tests\Feature\Web\MultiOrganizationConfigurationTest > coordination admin only sees the assigned organiza…   
Tests\Feature\Web\OrganizationConfigurationTest > authorized user can open organization configuration        
Tests\Feature\Web\OrganizationConfigurationTest > organization is val…  UniqueConstraintViolationException   
Tests\Feature\Web\OrganizationConfigurationTest > required fields display validation errors                  
Tests\Feature\Web\OrganizationConfigurationTest > form submitted with…  UniqueConstraintViolationException   
Tests\Feature\Web\OrganizationConfigurationTest > editing updates onl…  UniqueConstraintViolationException   
```

Fin de cette refonte. Attente de validation avant toute autre refonte ou fonctionnalité.

# Audit avant migration — 23 septembre 2026

Laravel / Blade, Tailwind 4 et plugin Vite déjà installés. Les entrées resources/css/app.css et resources/js/app.js existent mais le portail charge les assets publics directement. Aucun Bootstrap CSS détecté ; bootstrap.js initialise Axios, ce n’est pas Bootstrap UI.

Les styles publics pharmacare-portal.css et pharmacare-forms.css restent utilisés. La sidebar inclut environ 1100 lignes de CSS global et son JavaScript. Les pages autonomes incluent cette sidebar sans layout commun. Les styles locaux ont des règles contradictoires et des !important. Les composants Blade existants couvrent boutons, champs, badges, cartes, KPI, tableaux, pagination, états vides et fenêtre centrée.

Notifications : stockage Laravel et endpoints API existants, contrôle d’appartenance déjà présent. Cloche Web décorative fondée sur session notification_count, pas de panneau ni routes de session. API limitée aux 100 dernières entrées. Formulaires centrés et protection des brouillons déjà présents dans les modifications locales : à préserver.

Dashboard : graphique catalogue à proportions constantes sans données correspondantes ; styles KPI historiques masqués encore présents. Recherche topbar sans comportement. Des cellules affichent le nom immédiatement suivi d'un élément small sans séparation explicite.

Modifications locales préexistantes dans backend, mobile et caches : conservées. Baseline Laravel lancée avant modification et enregistrée dans .tmp/ui-baseline-tests.txt : 182 réussites, 39 échecs.

## Inventaire exhaustif des vues

| Vue | Structure | Blocs CSS locaux | Tableaux |
| --- | --- | --- | --- |
| `auth\forgot-password.blade.php` | autonome/composant | 0 | 0 |
| `auth\login.blade.php` | autonome/composant | 0 | 0 |
| `auth\reset-password.blade.php` | autonome/composant | 0 | 0 |
| `catalog\create-product.blade.php` | autonome/composant | 1 | 0 |
| `catalog\index.blade.php` | autonome/composant | 4 | 1 |
| `components\app-badge.blade.php` | autonome/composant | 0 | 0 |
| `components\app-breadcrumb.blade.php` | autonome/composant | 0 | 0 |
| `components\app-button.blade.php` | autonome/composant | 0 | 0 |
| `components\app-card.blade.php` | autonome/composant | 0 | 0 |
| `components\app-dashboard-panel.blade.php` | autonome/composant | 0 | 0 |
| `components\app-data-table.blade.php` | autonome/composant | 0 | 1 |
| `components\app-empty-state.blade.php` | autonome/composant | 0 | 0 |
| `components\app-filter-bar.blade.php` | autonome/composant | 0 | 0 |
| `components\app-icon-button.blade.php` | autonome/composant | 0 | 0 |
| `components\app-input.blade.php` | autonome/composant | 0 | 0 |
| `components\app-kpi-card.blade.php` | autonome/composant | 0 | 0 |
| `components\app-page-header.blade.php` | autonome/composant | 0 | 0 |
| `components\app-page-layout.blade.php` | autonome/composant | 0 | 0 |
| `components\app-pagination.blade.php` | autonome/composant | 0 | 0 |
| `components\app-search-input.blade.php` | autonome/composant | 0 | 0 |
| `components\app-sidebar.blade.php` | autonome/composant | 1 | 0 |
| `components\auth-styles.blade.php` | autonome/composant | 1 | 0 |
| `components\form-sheet.blade.php` | autonome/composant | 0 | 0 |
| `components\navigation\breadcrumb.blade.php` | autonome/composant | 0 | 0 |
| `components\navigation\sidebar.blade.php` | autonome/composant | 0 | 0 |
| `components\navigation\topbar.blade.php` | autonome/composant | 0 | 0 |
| `configuration\components\manual-configuration-fields.blade.php` | autonome/composant | 0 | 0 |
| `configuration\components\organization-form-fields.blade.php` | autonome/composant | 0 | 0 |
| `configuration\components\platform-standard-fields.blade.php` | autonome/composant | 1 | 0 |
| `configuration\components\stepper.blade.php` | autonome/composant | 0 | 0 |
| `configuration\index.blade.php` | portal | 0 | 0 |
| `configuration\organizations.blade.php` | portal | 1 | 0 |
| `configuration\platform-standard-assistance.blade.php` | portal | 1 | 0 |
| `configuration\platform-standard-history-detail.blade.php` | portal | 1 | 0 |
| `configuration\platform-standard-history.blade.php` | portal | 1 | 1 |
| `configuration\platform-standard-home.blade.php` | portal | 1 | 1 |
| `configuration\platform-standard-organizations.blade.php` | portal | 1 | 1 |
| `configuration\steps\advanced.blade.php` | autonome/composant | 1 | 1 |
| `configuration\steps\mission.blade.php` | autonome/composant | 0 | 0 |
| `configuration\steps\organization.blade.php` | autonome/composant | 2 | 1 |
| `control-center\index.blade.php` | portal | 1 | 0 |
| `dashboard\home.blade.php` | portal | 1 | 1 |
| `dashboard\index.blade.php` | autonome/composant | 2 | 1 |
| `dashboard\sago.blade.php` | portal | 1 | 0 |
| `dispensations\index.blade.php` | autonome/composant | 1 | 3 |
| `errors\403.blade.php` | autonome/composant | 1 | 0 |
| `funding\index.blade.php` | portal | 2 | 2 |
| `inventories\index.blade.php` | autonome/composant | 1 | 1 |
| `layouts\portal.blade.php` | autonome/composant | 0 | 0 |
| `missions\index.blade.php` | portal | 1 | 0 |
| `missions\partials\form-fields.blade.php` | autonome/composant | 0 | 0 |
| `missions\show.blade.php` | portal | 1 | 2 |
| `modules\placeholder.blade.php` | portal | 0 | 0 |
| `orders\index.blade.php` | portal | 1 | 1 |
| `organizations\index.blade.php` | portal | 1 | 0 |
| `organizations\partials\form-fields.blade.php` | autonome/composant | 0 | 0 |
| `profile\show.blade.php` | portal | 1 | 0 |
| `projects\index.blade.php` | autonome/composant | 1 | 0 |
| `projects\partials\configuration-tabs.blade.php` | autonome/composant | 1 | 0 |
| `projects\scope.blade.php` | portal | 1 | 1 |
| `projects\standard-list.blade.php` | portal | 1 | 0 |
| `receipts\index.blade.php` | autonome/composant | 1 | 0 |
| `receipts\show.blade.php` | autonome/composant | 1 | 1 |
| `security\index.blade.php` | autonome/composant | 1 | 1 |
| `setup\index.blade.php` | autonome/composant | 1 | 0 |
| `sites\index.blade.php` | autonome/composant | 2 | 1 |
| `stocks\create-movement.blade.php` | autonome/composant | 1 | 0 |
| `stocks\index.blade.php` | autonome/composant | 1 | 5 |
| `structures\create.blade.php` | autonome/composant | 1 | 0 |
| `structures\index.blade.php` | autonome/composant | 2 | 1 |
| `users\archived-show.blade.php` | autonome/composant | 1 | 0 |
| `users\archived.blade.php` | autonome/composant | 1 | 1 |
| `users\create.blade.php` | autonome/composant | 1 | 0 |
| `users\edit.blade.php` | autonome/composant | 1 | 0 |
| `users\show.blade.php` | autonome/composant | 1 | 0 |
| `welcome.blade.php` | autonome/composant | 1 | 0 |

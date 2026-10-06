# Matrice rôles × permissions — proposition à valider · 06/10/2026

**Statut : proposition, NON appliquée.** Le code (`backend/app/Support/RolePermissions.php`) contient les permissions effectives actuelles ; seules les corrections demandées explicitement y sont déjà (alias `owner` aligné sur `sago_admin`, libellés accentués, profil Coordination en lecture seule). Après validation : mise à jour de `RolePermissions`, des tests par rôle, puis application sur la copie de travail, et sur la base réelle seulement après validation et sauvegarde vérifiée.

## Comparaison des seeders

Le `DatabaseSeeder.php` de l'ancien PC est identique à celui du dépôt avant correction (liste des permissions, `roleDefinitions`, `strictMatrix`, suppression des rôles obsolètes).

Défauts corrigés dans le dépôt :

| Défaut | Correction |
|---|---|
| Double définition `roleDefinitions` puis `strictMatrix` (la seconde écrasait la première pour 5 rôles) | Définition unique `RolePermissions::ROLES`, égale aux permissions effectivement appliquées jusqu'ici |
| Rôles système absents supprimés, comptes détachés | Rôle désactivé et signalé (journal + console), jamais supprimé ni détaché |
| `stock.adjust` (faute de frappe, sans effet car écrasé) | Disparaît avec la définition unique ; `stocks.adjust` reste un écart (voir plus bas) |
| `owner` avec `users.manage`, `roles.manage`, missions, listes… | Aligné sur `sago_admin` |
| Libellés sans accents (Gerer, Creer, reactiver, receptions, Preparer, parametres) | Corrigés |
| Coordination (lecture seule) absente | Profil `coordination_read_only` : rôle `coordination_admin` + indicateur `read_only` du compte → permissions de consultation seulement |
| `db:seed` possible sur n'importe quelle base | Refusé sur `reelle` et `travail` (voir « Garde-fous ») |

## Légende

✓ conservé · ~~✓~~ retirer : tes décisions ou le cahier corrigé · **+ ajouter** : décision validée ou cahier corrigé · « → retirer ? » / « → ajouter ? » : écart **à trancher**.

Sources, par ordre de priorité (règle permanente n° 1) : décisions validées (registre) → document « Configuration Mission » → cahier des charges corrigé (octobre 2026) → maquettes.

| Permission | Sago | owner | Coordination | Projet | Admin Site | Util. Site |
|---|---|---|---|---|---|---|
| `dashboard.view` | ✓ | ✓ | ✓ | ✓ | ✓ | ✓ |
| `organizations.view` | ✓ | ✓ |  |  |  |  |
| `organizations.manage` | ✓ | ✓ |  |  |  |  |
| `configuration.view` | ✓ | ✓ |  |  |  |  |
| `configuration.platform.manage` | ✓ | ✓ |  |  |  |  |
| `missions.view` |  |  | ✓ |  |  |  |
| `missions.manage` |  |  | ✓ |  |  |  |
| `projects.view` |  |  | ✓ | ✓ |  |  |
| `projects.manage` |  |  | ✓ |  |  |  |
| `standard_lists.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `standard_lists.manage` |  |  | ✓ |  |  |  |
| `products.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `products.manage` |  |  |  | ~~✓~~ retirer |  |  |
| `funding.view` |  |  | ✓ |  |  |  |
| `funding.manage` |  |  | ✓ |  |  |  |
| `modules.manage` |  |  | — → ajouter ? | — → ajouter ? |  |  |
| `catalog.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `catalog.manage` |  |  | **+ ajouter** |  |  |  |
| `standards.assign` | ✓ | ✓ |  |  |  |  |
| `platform_standards.view` | ✓ | ✓ |  |  |  |  |
| `platform_standards.manage` | ✓ | ✓ |  |  |  |  |
| `batches.manage` |  |  |  |  | — → ajouter ? |  |
| `stocks.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `stocks.manage` |  |  |  |  | ✓ |  |
| `stocks.adjust` |  |  |  |  | — → ajouter ? |  |
| `transfers.manage` |  |  |  |  | — → ajouter ? |  |
| `receipts.manage` |  |  | ✓ → retirer ? | ~~✓~~ retirer | ✓ | **+ ajouter** |
| `dispensing.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `dispensing.manage` |  |  | ~~✓~~ retirer | ~~✓~~ retirer | ✓ |  |
| `inventories.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `inventories.manage` |  |  | ✓ → retirer ? | ✓ → retirer ? | ✓ | **+ ajouter** |
| `orders.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `orders.manage` |  |  | ✓ → retirer ? | ✓ | ✓ |  |
| `reports.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `reports.export` |  |  | ✓ | ✓ |  |  |
| `synchronization.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `synchronization.manage` |  |  |  |  | ✓ |  |
| `users.view` |  |  | ✓ | ✓ |  |  |
| `users.manage` |  |  | ✓ |  |  |  |
| `activity_logs.view` | ✓ | ✓ | ✓ | ✓ |  |  |
| `audit.view` | ✓ | ✓ |  |  |  |  |
| `health_facilities.view` | — → ajouter ? |  | ✓ | ✓ |  |  |
| `health_facilities.manage` |  |  | ✓ | ✓ |  |  |
| `dispensing_sites.view` |  |  | ✓ | ✓ |  |  |
| `dispensing_sites.manage` |  |  | ✓ | ✓ |  |  |
| `users.create_site_admin` |  |  |  | ✓ |  |  |
| `users.update_site_admin` |  |  |  | ✓ |  |  |
| `users.suspend_site_admin` |  |  |  | ✓ |  |  |
| `receipts.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `orders.prepare` |  |  |  |  | ✓ |  |
| `orders.approve` |  |  | ✓ → retirer ? |  |  |  |
| `inventories.validate` |  |  | ✓ → retirer ? |  |  |  |
| `patients.view` |  |  | ✓ → retirer ? | ✓ → retirer ? | ✓ | ✓ |
| `patients.manage` |  |  | ~~✓~~ retirer | ~~✓~~ retirer | ✓ |  |
| `prescriptions.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `prescriptions.manage` |  |  | ~~✓~~ retirer | ~~✓~~ retirer | ✓ | ✓ |
| `prescriptions.validate` |  |  | ~~✓~~ retirer | ~~✓~~ retirer |  |  |
| `dispensations.view` |  |  | ✓ | ✓ | ✓ | ✓ |
| `dispensations.manage` |  |  | ~~✓~~ retirer | ~~✓~~ retirer | ✓ | ✓ |
| `reports.export_local` |  |  |  |  | ✓ |  |
| `project_settings.view` |  |  |  | ✓ |  |  |
| `site_settings.view` |  |  |  |  | ✓ |  |
| `activity_logs.view_local` |  |  |  |  | ✓ | ✓ |

## Écarts à trancher

| # | Rôle | Écart | Cahier corrigé / matrice | Proposition |
|---|---|---|---|---|
| E1 | Coordination, Projet | `patients.view` : identité des patients (données de santé sensibles) | « accès à toutes les vues des FOSA » (I.1, I.2), mais « droits d'accès stricts » sur les données patients (§3) | Retirer `patients.view` ; garder la consultation des dispensations et ordonnances (sans fiche patient) |
| E2 | Coordination | `receipts.manage`, `inventories.manage`, `inventories.validate`, `orders.manage`, `orders.approve` | Entrées, inventaire : gérant de pharmacie et responsable FOSA ; la Coordination consulte et analyse | Retirer |
| E3 | Projet | `inventories.manage` | Inventaire réalisé par la FOSA | Retirer ; garder `inventories.view` |
| E4 | Projet | `inventories.validate`, `orders.approve` (matrice interne `04`) | Non prévus par le cahier corrigé | **Ne pas ajouter** (je retire ma proposition précédente) |
| E5 | Admin Site | `stocks.adjust`, `batches.manage`, `transfers.manage` vérifiés par le code mais attribués à personne | Gestion des lots, quarantaine (périmés, chaîne du froid) | Ajouter |
| E6 | Coordination, Projet | `modules.manage` attribué à personne | « Defines what features are active » (I.1, I.2) | Ajouter aux deux rôles quand l'écran d'activation sera repris (hors V1 actuelle) |
| E7 | Projet | Création des comptes **Utilisateur Site** : aucune permission (seulement `users.*_site_admin`) | §3 : l'Admin Projet crée et désactive Admin Site **et** Utilisateur Site | Niveau 5 : `users.create_site_user`, `users.update_site_user`, `users.suspend_site_user` |
| E8 | Admin Sago | Liste de toutes les FOSA et de leurs comptes, suspension | §3 « Garde-fous » : l'Admin Sago voit et peut suspendre | Voir C1 ; au minimum `health_facilities.view` |
| E9 | Coordination (lecture seule) | Profil sans rôle propre (indicateur `read_only`) | §3 : niveau d'accès « Coordination (lecture seule) » | Garder l'indicateur (refus déjà appliqué côté serveur partout) |
| E10 | `owner` | Comptes rattachés : inconnu (base réelle absente de ce PC) | — | Lecture seule dès réception de la base, puis comptes → `sago_admin` et `owner` désactivé |
| E11 | Admin Site, Utilisateur Site | `reports.view` : accès aux analyses | « l'ONG sera la seule à accéder à l'analyse » (inventaire) | Restreindre les analyses d'inventaire à la Coordination au niveau 9 (analyses) |

## Conformité au cahier corrigé (octobre 2026)

**Contradictions avec les décisions validées** (le registre prime ; le cahier est à mettre à jour, ou la décision à revoir) :

| # | Cahier corrigé | Décision validée et implémentée | À trancher |
|---|---|---|---|
| C1 | §3 et annexe B : l'**Admin Sago** valide la FOSA une seule fois, voit toutes les FOSA et leurs comptes, peut suspendre | 01/10 : la validation des FOSA passe à l'**Admin Coordination** (« le rôle de l'Admin Sago est inchangé ») ; écrans « Ma Coordination » livrés (niveau 3) | Confirmer la Coordination (cahier à corriger) ou donner aussi à l'Admin Sago la supervision et la suspension (E8) |
| C2 | Annexe A : entrées en stock, inventaire, photo d'ordonnance, analyses de base en **V2** | 01/10 : analyses de base et capture par le téléphone en **V1** (feuille de route niveaux 8 et 9) | Garder la feuille de route (cahier à mettre à jour) |
| C3 | II.1/II.2 : écrans d'entrée dynamiques par code mission / projet / FOSA | C-02 : connexion par compte individuel, périmètre déduit du compte | Garder C-02 |

**Confirmé par le cahier** (déjà fait ou prévu) :
- Stock de sécurité du projet en 0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois et dates d'inventaire, de soumission et de réception au niveau du projet (II.2) : correspond à la migration du niveau 2.
- Préremplissage des FOSA depuis le projet, sans écrasement, action « Appliquer à toutes les FOSA » (II.3) : déjà en place (DEC-08).
- Seule la Coordination modifie la Liste Standard ; codification propre à l'ONG, ajout, import Excel, décochage par FOSA : `standard_lists.manage` et `catalog.manage` pour la Coordination seulement (niveau 6).
- Utilisateur Site : entrées, dispensation, inventaire (§3) : `receipts.manage`, `inventories.manage`.
- Validation clinique des ordonnances en V4 (annexe A) : `prescriptions.validate` retiré à la Coordination et au Projet.

**Écarts relevés sur l'assistant « Créer un projet / programme »** (niveau 2, à reprendre au niveau 6) :
- Catégorie de FOSA (II.1) : le cahier coche les produits selon la catégorie dès la configuration du projet ; l'assistant génère la liste sans catégorie (la catégorie est choisie par FOSA).
- Niveau « Programme Laboratoire » et examens de laboratoire par population : absents.
- Pathologies proposées selon le couple niveau de soins × population : toutes proposées aujourd'hui.
- Article hors liste livré par un tiers : règle encore « à valider » dans le cahier.

## Garde-fous de la base

- `PHARMACARE_DATABASE` (`reelle` par défaut, `travail`, `demo`, `test`) dans le `.env` de chaque instance. **Sans cette ligne, la base est traitée comme réelle.**
- `db:seed` refusé sur `reelle` et `travail` (`App\Support\DatabaseGuard`, appelé en première ligne du seeder) ; dérogation explicite et temporaire : `PHARMACARE_ALLOW_SEED=true`.
- `migrate:fresh`, `migrate:refresh`, `migrate:reset`, `db:wipe` interdits sur `reelle` et `travail` (`DB::prohibitDestructiveCommands`).
- Tests : `phpunit.xml` impose (`force="true"`) SQLite en mémoire et `PHARMACARE_DATABASE=test`. `tests/TestCase.php` arrête tout test avant les migrations si la connexion n'est pas SQLite en mémoire (configuration en cache, variable mal positionnée…).

## APP_KEY — usage et rotation proposée

Usage vérifié dans le code : aucun champ chiffré (`encrypted`, `Crypt`), sessions non chiffrées (`SESSION_ENCRYPT=false`, fichiers), aucune URL signée ni tâche chiffrée. Jetons mobiles (Sanctum) et jetons de réinitialisation stockés hachés : indépendants de la clé. La clé sert au chiffrement des cookies du navigateur (session, XSRF).

Effet d'une rotation : sessions Web déconnectées (sauf transition), téléphones non concernés, aucune donnée à rechiffrer.

Procédure proposée (à appliquer après validation et sauvegarde vérifiée) :
1. Sauvegarde vérifiée de la base et du `.env`.
2. Nouvelle clé (`php artisan key:generate --show`, sans écrire le `.env`), puis dans le `.env` : `APP_KEY=<nouvelle>` et `APP_PREVIOUS_KEYS=<ancienne>`.
3. `php artisan config:clear` ; vérification : connexion Web existante conservée, nouvelle connexion, application mobile.
4. Après la durée de session (120 min) ou au plus tard une semaine : retirer `APP_PREVIOUS_KEYS`.

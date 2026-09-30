# Audit technique avant tests V1 en ligne

| | |
|---|---|
| **Date** | 30 septembre 2026 |
| **Périmètre** | Backend Laravel (`backend/`), client Flutter (`mobile/`), portail Web Blade |
| **Base analysée** | `master` @ `e2e9b56` + modifications non commitées (refonte UI 08–23/09) |
| **Méthode** | Lecture du code, exécution des tests, analyse statique. **Aucun code modifié.** |

## Mesures exécutées

| Contrôle | Résultat |
|---|---|
| `php vendor/bin/phpunit` (SQLite mémoire) | 259 tests, 1 895 assertions, **6 échecs** (tous liés à la navigation, voir C-09) |
| `flutter analyze` | Aucun problème |
| `flutter test` | Non exécuté dans cet audit (durée > délai lors de l'audit Phase 2) |

Chaque constat indique s'il est **vérifié** (test ou exécution) ou **constaté à la lecture** du code.

---

## 1. Constats critiques — bloquants, sécurité, perte de données

| ID | Constat | Emplacement | Preuve |
|---|---|---|---|
| **C-01** | **Opérations hors ligne perdues après interruption.** Une opération passe à `syncing` avant l'envoi. Si l'application est fermée ou plante pendant l'envoi, elle reste `syncing` pour toujours : `pendingOperations()` ne reprend que `pending` et `retry`. | [sync_service.dart:88](../../mobile/lib/src/core/sync/sync_service.dart#L88), [app_database.dart:236](../../mobile/lib/src/core/database/app_database.dart#L236) | Lecture |
| **C-02** | **Échecs de synchronisation invisibles.** Les opérations refusées (4xx) ou en conflit (409) passent en `failed`/`conflict`, mais aucun écran ne les affiche. Une dispensation faite hors ligne puis refusée (stock insuffisant, inventaire en cours, référence en double) disparaît sans alerte. Les refus **temporaires** (423 inventaire gelé, 403 après changement de droits) sont traités comme définitifs. | [sync_service.dart:193-205](../../mobile/lib/src/core/sync/sync_service.dart#L193-L205) | Lecture |
| **C-03** | **Comptes inutilisables.** Si l'administrateur ne saisit pas de mot de passe, un mot de passe aléatoire de 16 caractères est généré puis **jamais communiqué** (aucun e-mail d'invitation). L'action « Réinitialiser le mot de passe » d'un administrateur fait de même. Le compte n'est récupérable que par « Mot de passe oublié », donc seulement si le SMTP fonctionne. | [Web/AuthController.php:177](../../backend/app/Http/Controllers/Web/AuthController.php#L177), [Web/AuthController.php:253](../../backend/app/Http/Controllers/Web/AuthController.php#L253), [Api/V1/UserController.php:99](../../backend/app/Http/Controllers/Api/V1/UserController.php#L99), [Api/V1/UserController.php:172](../../backend/app/Http/Controllers/Api/V1/UserController.php#L172) | Lecture |
| **C-04** | **Changement de mot de passe initial non imposé.** Le Web redirige une seule fois vers le profil, puis l'utilisateur peut naviguer librement. L'API n'applique aucun blocage. Le routeur Flutter ne bloque que la page d'accueil. | [Web/AuthController.php:74](../../backend/app/Http/Controllers/Web/AuthController.php#L74), [app_router.dart:53](../../mobile/lib/src/core/routing/app_router.dart#L53) | Lecture |
| **C-05** | **Ordonnance non obligatoire côté serveur.** `prescription_id` et `attachment` sont facultatifs dans l'API de dispensation. Seule l'application Android impose la photo. Tout autre client (Web, API directe) peut dispenser sans ordonnance. | [Api/V1/DispensationController.php:216-217](../../backend/app/Http/Controllers/Api/V1/DispensationController.php#L216-L217) | Lecture |
| **C-06** | **Fuite de cloisonnement à la restauration d'une FOSA.** La restauration vérifie l'organisation, mais pas que la FOSA fait partie du périmètre de l'utilisateur. Un Admin Projet peut restaurer une FOSA archivée d'un autre projet de la même organisation. | [Web/StructureController.php:166](../../backend/app/Http/Controllers/Web/StructureController.php#L166), [Api/V1/StructureController.php:100](../../backend/app/Http/Controllers/Api/V1/StructureController.php#L100) | Lecture |
| **C-07** | **Configuration non adaptée à un serveur en ligne.** `APP_DEBUG=true` (traces exposées). Aucun `config/sanctum.php` : **les jetons n'expirent jamais**. Android autorise le HTTP en clair (`usesCleartextTraffic`). L'URL de l'API est codée en dur sur une IP locale (`192.168.137.234`). | `backend/.env`, [AndroidManifest.xml:8](../../mobile/android/app/src/main/AndroidManifest.xml#L8), [app_config.dart:14](../../mobile/lib/src/core/config/app_config.dart#L14) | Lecture |
| **C-08** | **Mot de passe hors ligne faiblement protégé.** Le vérificateur est un SHA-256 simple (appareil + identifiant + mot de passe), sans étirement de clé. Il est cassable par force brute si l'appareil est compromis. | [auth_service.dart:166-175](../../mobile/lib/src/features/auth/data/auth_service.dart#L166-L175) | Lecture |
| **C-09** | **Régression non commitée : menu de l'Admin Projet élargi.** Le menu affiche désormais Bénéficiaires, Sites, Médicaments, Stocks, Réceptions et Dispensations, contrairement à la règle (Tableau de bord, Projet, Liste Standard, Profil). **6 tests échouent**, et deux modules masqués répondent 404 au lieu de 403. | [ApplicationNavigationService.php:110](../../backend/app/Services/ApplicationNavigationService.php#L110), [config/pharmacare_v1.php](../../backend/config/pharmacare_v1.php) | **Vérifié** (tests) |

## 2. Constats majeurs

| ID | Constat | Emplacement |
|---|---|---|
| **M-01** | **Trois chemins de création de projet, avec des règles différentes.** Web : administrateur obligatoire. API (mobile) : administrateur facultatif. Assistant de configuration : dates obligatoires, pas de paramètres d'approvisionnement, pas de `ProjectProvisioningService`. Contraire à la règle « un seul workflow, un seul endpoint ». | [Web/ProjectController.php:167](../../backend/app/Http/Controllers/Web/ProjectController.php#L167), [Api/V1/ProjectController.php:171](../../backend/app/Http/Controllers/Api/V1/ProjectController.php#L171), [ConfigurationWizardController.php:314](../../backend/app/Http/Controllers/Web/ConfigurationWizardController.php#L314) |
| **M-02** | **Flutter Web n'existe pas.** Pas de dossier `mobile/web/`. `dart:io Platform` est appelé à la connexion (plante sur le Web). La base locale lève `UnsupportedError` sur le Web et la synchronisation y est désactivée. Aujourd'hui, le client Web réel est le **portail Laravel Blade**. | [auth_service.dart:78](../../mobile/lib/src/features/auth/data/auth_service.dart#L78), [database_connection_stub.dart](../../mobile/lib/src/core/database/database_connection_stub.dart), [sync_bootstrap.dart:19](../../mobile/lib/src/core/sync/sync_bootstrap.dart#L19) |
| **M-03** | **Langue.** Le mobile ne gère que le français (`AppLocale.supported = ['fr']`, l'anglais est refusé). L'organisation porte encore `default_language` / `additional_languages` (API de création, configuration plateforme), contrairement à la règle. | [app_locale.dart:4](../../mobile/lib/src/core/localization/app_locale.dart#L4), [auth_service.dart:241](../../mobile/lib/src/features/auth/data/auth_service.dart#L241), [Api/V1/OrganizationController.php:146](../../backend/app/Http/Controllers/Api/V1/OrganizationController.php#L146) |
| **M-04** | **Aucune Policy, Gate ni Form Request.** L'autorisation est dispersée entre le middleware de permission et des `abort_unless` dans une quarantaine de contrôleurs. Les validations sont dupliquées entre Web et API. C-06 est une conséquence directe de cette dispersion. | `app/Http/Controllers/**` |
| **M-05** | **Liste standard « ouverte par défaut ».** Si aucun projet ou aucune liste publiée n'est trouvé, **tous** les produits de l'organisation deviennent dispensables. | [Api/V1/DispensationController.php:326](../../backend/app/Http/Controllers/Api/V1/DispensationController.php#L326) |
| **M-06** | **Références de dispensation en collision.** Le mobile génère `DIS-<millisecondes>`, unique par organisation côté serveur. Deux appareils peuvent produire la même valeur, et le rejet 422 à la synchronisation est définitif. | [clinical_supply_page.dart:611](../../mobile/lib/src/features/dispensations/presentation/clinical_supply_page.dart#L611) |
| **M-07** | **Aucun contrôle de stock hors ligne.** Pas de solde ni de FEFO local : la dispensation est acceptée sur l'appareil puis peut être refusée à la synchronisation, après le départ du patient (décision MET-009 non tranchée). | `clinical_supply_service.dart` |
| **M-08** | **Idempotence.** Les réponses 4xx sont conservées 30 jours, y compris 423 (inventaire gelé) : la même opération reçoit toujours l'erreur. L'insertion de la clé n'est pas atomique (deux envois simultanés s'exécutent tous deux). | [EnsureIdempotentApiRequest.php:52](../../backend/app/Http/Middleware/EnsureIdempotentApiRequest.php#L52) |
| **M-09** | **Stock réservé ignoré** lors de l'écriture d'un mouvement : `record()` ne contrôle que la quantité théorique, pas `reserved_quantity`. | [StockLedgerService.php:46](../../backend/app/Services/StockLedgerService.php#L46) |
| **M-10** | **Rôles hérités encore actifs.** Un utilisateur sans rôle officiel (`roleCode === null`) obtient l'accès à toute son organisation. Les alias `organization_admin`, `pharmacist`, etc. sont interprétés. Des rôles personnalisés restent créables via `/security`. | [UserScopeService.php:86](../../backend/app/Services/UserScopeService.php#L86), [UserScopeService.php:297-316](../../backend/app/Services/UserScopeService.php#L297-L316) |
| **M-11** | **Modules à moitié fonctionnels visibles.** Rapports et Synchronisation (Web) sont des pages provisoires, mais apparaissent dans le menu des rôles de site. | [web.php:103-109](../../backend/routes/web.php#L103-L109), [ApplicationNavigationService.php:115](../../backend/app/Services/ApplicationNavigationService.php#L115) |
| **M-12** | **Performance.** `roleCode()` interroge la base à chaque appel et est appelé plusieurs fois par requête (périmètre, middleware V1, navigation). Les périmètres chargent des listes d'identifiants complètes (`pluck('id')`). | `UserScopeService`, `GovernanceService` |
| **M-13** | **Fichiers orphelins.** Les pièces jointes d'ordonnance sont écrites **avant** la transaction : si la transaction échoue, le fichier reste. | [Api/V1/DispensationController.php:229](../../backend/app/Http/Controllers/Api/V1/DispensationController.php#L229) |
| **M-14** | **L'Admin Coordination peut créer des Admin Site** (`assignableCodes`), alors que `docs/04` le limite aux Admin Projet. | [GovernanceService.php:96](../../backend/app/Services/GovernanceService.php#L96) |

## 3. Constats mineurs

| ID | Constat |
|---|---|
| m-01 | Politique de mot de passe : 6 caractères partout (conforme), mais complexité imposée (majuscule, minuscule, chiffre, symbole). À confirmer au regard de la règle « 6 caractères minimum ». |
| m-02 | Code mort : `AuthService.devices()` / `revokeDevice()` côté mobile, route `profile.devices.revoke` côté Web (fonction retirée de l'interface, à conserver masquée). |
| m-03 | `dispensed_at` doit être antérieur à `now` : un appareil dont l'horloge est en avance est rejeté définitivement. |
| m-04 | Commentaires et code commenté laissés dans les contrôleurs (`OrganizationController::store`). |
| m-05 | `lang/fr/ui.php` (7 lignes) contre `lang/en/ui.php` (204 lignes) : couverture de traduction à vérifier écran par écran. |
| m-06 | Fichiers de code compressés sur une ligne (`CatalogReference`, `StandardListVersion`, `ProjectStandardListController`) : difficiles à relire et à diffuser. |
| m-07 | Arbre Git : `.pub-cache`, `.gradle` et caches de build sont suivis, ce qui ralentit toutes les commandes Git. |

## 4. Points conformes (à préserver)

- Déconnexion Web : `POST /logout` → `redirect('/login')`, aucun 404 trouvé dans le code (**à confirmer en recette** sur le serveur en ligne).
- Mot de passe : 6 caractères minimum identiques backend / Web / mobile.
- Messages « l'utilisateur devra modifier son mot de passe » ; aucun mot de passe affiché après création.
- « Appareils autorisés » absent du Profil Web et mobile.
- Le message « Mode hors connexion disponible après une première connexion réussie » n'apparaît plus.
- L'Admin Coordination ne peut pas créer de mission (Web et API).
- Menu de l'Admin Coordination conforme (Tableau de bord, Ma Coordination, Configuration des projets, Liste Standard, Profil).
- Seule la Coordination modifie les listes standards.
- Registre de stock : verrou `lockForUpdate`, stock négatif refusé, FEFO par date d'expiration, lots périmés exclus, transactions sur la dispensation et le retour.
- Identifiants client uniques (`offline_uuid`, `client_reference`) sur patients, ordonnances, dispensations, inventaires et commandes.
- Parcours Android Patient → Ordonnance → Dispensation → Résumé présent, avec photo d'ordonnance obligatoire dans l'application.

---

## 5. Décisions requises avant correction

| ID | Question | Recommandation |
|---|---|---|
| Q-1 | Le client Web cible est-il le **portail Laravel Blade** existant ou une nouvelle version **Flutter Web** ? | Tester la V1 avec le portail Blade ; reporter Flutter Web à la V2 (refonte importante) |
| Q-2 | Le hors ligne est-il exigé sur le **Web** ? | Non pour la V1 (hors ligne sur Android/iOS uniquement) |
| Q-3 | Menu Admin Projet : strictement « Tableau de bord, Projet, Liste Standard, Profil » ? Où l'Admin Projet gère-t-il alors ses FOSA et son équipe ? | FOSA et Équipe regroupées **dans** « Projet » (onglets), pour respecter la règle |
| Q-4 | Création de compte sans mot de passe : envoyer un **lien d'invitation** par e-mail, ou rendre la saisie du mot de passe initial **obligatoire** ? | Mot de passe initial obligatoire (fonctionne sans SMTP) + changement imposé |
| Q-5 | Complexité du mot de passe : conserver majuscule, chiffre et symbole, ou seulement 6 caractères ? | Seulement 6 caractères, si c'est l'intention de la règle |
| Q-6 | Dispensation hors ligne sans stock local : autoriser et signaler après coup, ou bloquer ? | Projeter les soldes du site dans SQLite et bloquer localement au-delà du solde connu |
| Q-7 | Hébergement du test en ligne : domaine et certificat HTTPS disponibles ? | Obligatoire avant d'ouvrir le test (jetons, mots de passe) |

---

## 6. Plan de correction proposé

Chaque lot est livré séparément, avec non-régression complète (`phpunit` + `flutter analyze` + `flutter test`) et mise à jour du registre.

| Lot | Contenu | Constats |
|---|---|---|
| **L1 — Stabilisation** | Corriger le menu de l'Admin Projet selon Q-3 ; remettre les 6 tests au vert ; commits thématiques de la refonte UI ; exclure les caches du suivi Git | C-09, m-07 |
| **L2 — Comptes et authentification** | Mot de passe initial (Q-4) ; middleware serveur « changement obligatoire » (Web + API) ; garde du routeur Flutter ; expiration des jetons Sanctum ; vérificateur hors ligne PBKDF2/Argon2 | C-03, C-04, C-07, C-08, m-01 |
| **L3 — Cloisonnement** | Policies Laravel (Organisation, Mission, Projet, FOSA, Site, Patient, Dispensation) ; corriger les restaurations ; retirer la branche `roleCode === null` et les alias actifs ; tests Feature d'isolation | C-06, M-04, M-10, M-14 |
| **L4 — Dispensation fiable** | Ordonnance obligatoire côté serveur ; liste standard fermée par défaut ; références UUID ; pièces jointes après commit ; contrôle du stock réservé | C-05, M-05, M-06, M-09, M-13 |
| **L5 — Synchronisation** | Reprise des opérations `syncing` ; statuts temporaires rejouables ; écran « Synchronisation » (en attente, échecs, conflits, dernière synchronisation) ; idempotence atomique ; stock local du site (Q-6) | C-01, C-02, M-07, M-08 |
| **L6 — Projet unifié** | Un seul service et une seule validation (Form Request) pour les trois chemins de création/modification | M-01 |
| **L7 — Langue et parité** | FR/EN sur mobile ; retrait des champs de langue de l'organisation (sans suppression des colonnes) ; masquage des modules provisoires | M-03, M-11 |
| **L8 — Mise en ligne** | `.env` de production, HTTPS, URL d'API par environnement (`--dart-define`), suppression du HTTP en clair, cache de rôle par requête | C-07, M-12 |
| **L9 — Tests et recette** | Tests Feature : authentification, cloisonnement, création de projet, listes standards, dispensation ; scénarios manuels par rôle, en ligne et hors ligne | — |

Ordre recommandé : **L1 → L2 → L3 → L4 → L5**, puis L6 à L9. L8 peut être préparé en parallèle dès que Q-7 est tranché.

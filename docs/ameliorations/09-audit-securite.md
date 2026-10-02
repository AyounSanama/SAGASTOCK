# Audit de sécurité — 01/10/2026

Audit réalisé **sans modifier le code** (lecture du code, des configurations, de l'historique Git et des dépendances). PharmaCare traite des données de santé (patients, statut VIH, grossesse) et des stocks de médicaments : la sécurité est vérifiée à chaque lot (voir « Vérification de sécurité de chaque lot » en fin de document).

## Synthèse

| Gravité | Nombre | Points |
|---|---|---|
| Critique | 2 | S-01, S-02 |
| Élevée | 5 | S-03 à S-07 |
| Moyenne | 6 | S-08 à S-13 |
| Faible | 2 | S-14, S-15 |

## Critique

| ID | Constat | Preuve | Correction proposée |
|---|---|---|---|
| S-01 | **Un compte désactivé garde son accès.** Passer un compte à « inactif » (API `PUT /users/{id}`, formulaire Web `updateUser`) ne révoque ni ses jetons ni sa session, et aucune vérification n'a lieu à chaque requête : seul le **login** contrôle `is_active` et l'organisation active. Un téléphone déjà connecté continue de lire et d'écrire. Même effet pour une organisation désactivée. | `Api/V1/UserController::update`, `Web/AuthController::updateUser` (pas de `tokens()->delete()`) ; aucun middleware ne lit `is_active`. | Middleware `EnsureAccountIsActive` (API et Web) : compte inactif ou archivé, organisation inactive → jetons révoqués, session fermée, 401. Révocation immédiate des jetons dès la désactivation. La FOSA suspendue s'y ajoute au lot c1-bis (règle M-04 validée). Test avec un vrai jeton. |
| S-02 | **Base locale mobile non chiffrée.** Patients, ordonnances et stock sont en clair dans le fichier SQLite du téléphone ; un téléphone perdu expose ces données. | `database_connection_native.dart` : `NativeDatabase` sans clé. | DEC-10, lot d : étude de faisabilité (SQLCipher ou équivalent, clé dans le stockage sécurisé, migration des données existantes **sans perte**), rapport avant code, puis mise en œuvre **avant tout déploiement terrain**. **Décision nécessaire** : avancer cette étude avant c1-bis ou la garder au lot d. |

## Élevée

| ID | Constat | Preuve | Correction proposée |
|---|---|---|---|
| S-03 | ~~Les jetons n'expirent jamais.~~ **Rectifié le 02/10 :** les jetons créés à la connexion expirent déjà après 30 jours (`expires_at`), mais **sans prolongation** : un téléphone utilisé chaque jour est déconnecté au 30e jour. Pas de verrouillage de l'application en cas d'inactivité. | `Api/V1/AuthController::login` (`now()->addDays(30)`). | Expiration glissante : jeton prolongé à chaque synchronisation, expiré après N jours sans connexion. **Décision nécessaire** sur N (proposition : 30 jours, compatible avec des FOSA longtemps hors ligne ; Web : session de 2 h). |
| S-04 | **Le changement de mot de passe à la 1re connexion n'est pas imposé par le serveur.** Le Web redirige seulement après la connexion (on peut naviguer ailleurs ensuite) ; l'API renvoie l'indicateur, mais seul le mobile le respecte. | `Web/AuthController` l. 74 ; aucun middleware. | Middleware : tant que `must_change_password` est vrai, tout est refusé sauf changement de mot de passe, déconnexion et profil minimal (Web et API). |
| S-05 | **Données patient dans le journal d'audit.** `patient.updated` enregistre la fiche complète avant/après (statut VIH, grossesse…), et `GET /security/audits` (permission `audit.view`, Admin Sago) lit tout le journal, toutes organisations confondues, ce qui contredit la frontière Sago. | `DispensationController` l. 78 ; `AuditController::index`. | Journal : identifiants et noms de champs modifiés seulement, jamais les valeurs de santé. Lecture du journal limitée au périmètre de l'utilisateur ; l'Admin Sago ne voit que les événements de plateforme. |
| S-06 | **HTTP en clair autorisé dans toutes les versions Android**, production comprise ; HTTPS non imposé. | `AndroidManifest.xml` : `usesCleartextTraffic="true"` ; `app_config.dart` accepte `http://` en production. | HTTP autorisé uniquement dans les versions de test (manifeste par type de build) ; en production, `app_config` refuse toute adresse non `https://`. iOS : pas d'exception ATS en production. |
| S-07 | **Un compte sans rôle officiel, à périmètre plateforme, voit toutes les organisations** (`isPlatform` accepte un rôle non officiel). La création et la modification de comptes sont déjà fermées (point 1, ce jour). Le rapport R6 montre qu'**aucun compte de ce type n'existe sur la base réelle**. | `UserScopeService::isPlatform`. | Réserver le périmètre plateforme au seul Admin Sago. |

## Moyenne

| ID | Constat | Correction proposée |
|---|---|---|
| S-08 | `APP_DEBUG=true` dans le `.env` local : traces exposées si ce fichier est déployé tel quel. | Liste de contrôle de déploiement ; refus de démarrer en production si `APP_DEBUG=true` ou `APP_ENV≠production`. |
| S-09 | Connexion Web sans limitation par adresse IP : seul le verrou par compte existe (5 échecs → 15 min). L'API est limitée à 6 essais par minute. | Limiteur `login` sur la route Web (même règle que l'API). |
| S-10 | Aucune **Policy** Laravel : les règles sont dans les middlewares, les contrôleurs et les services (cohérentes et testées, mais un oubli reste possible sur une nouvelle route). | Policies progressives : `User`, `HealthFacility`, `Patient`, `Prescription`, en commençant par les lots touchés. Test de matrice par rôle avec vrai jeton étendu à chaque nouveau module. |
| S-11 | Dépendances vulnérables : `league/commonmark` 2.10.0 (1 haute, déni de service ; 1 moyenne ; aucun usage Markdown trouvé dans l'application), `axios` (haute, **inclus dans le bundle Web**), `nanoid` (haute, indirect, outil de build). | Mettre à jour `league/commonmark` (> 2.10.1) et `axios` ; relancer `composer audit` et `npm audit` à chaque lot. |
| S-12 | Pièces jointes d'ordonnances : type (pdf/jpg/png) et taille (5 Mo) contrôlés, rangées hors du dossier public, mais **en clair** sur le serveur ; aucune route de consultation (donc aucune fuite, mais pas encore de consultation contrôlée). | Lot g-bis : chiffrement au repos et route de consultation vérifiant le périmètre ; jamais d'URL publique. |
| S-13 | Cookie de session : `secure` non forcé, session non chiffrée. | En production : `SESSION_SECURE_COOKIE=true`, `SESSION_ENCRYPT=true`, HTTPS seulement. |

## Faible

| ID | Constat | Correction proposée |
|---|---|---|
| S-14 | `ProductStandardMapping` sans liste de champs autorisés (`$guarded=[]`). Ses écritures passent par des données validées, donc pas de faille aujourd'hui. | Déclarer `$fillable`. |
| S-15 | Paquets Flutter en retard : `sqlite3_flutter_libs` en fin de vie (`eol`), `flutter_secure_storage` 10.3 (11.2 disponible), `drift` 2.34 (2.35). Aucune vulnérabilité connue publiée. | Mise à jour groupée au lot d (chiffrement : même pile SQLite). |

## Points vérifiés conformes

- **Comptes :** seuls les rôles officiels gèrent des comptes, jamais un rôle supérieur ou égal (sauf la Coordination en lecture seule, validée), jamais un compte FOSA à la plateforme (corrigé ce jour, `AccountHierarchyTest`).
- **Verrouillage :** après 5 échecs, le compte est verrouillé 15 minutes (Web et API). La révocation d'appareil supprime ses jetons ; la réinitialisation du mot de passe aussi.
- **Cloisonnement :** les restrictions V1, la frontière Sago et la lecture seule s'appliquent aux vrais jetons (`RealTokenRoleMatrixTest`). Les périmètres Organisation / Coordination / Projet / FOSA sont filtrés côté serveur (`UserScopeService`).
- **Entrées :** validation systématique (Form Requests ou `validate`) ; aucune requête SQL construite à partir d'une saisie ; aucun affichage non échappé (`{!!` absent des vues) ; protection CSRF active sur le Web (aucune exception).
- **Mobile :** jeton dans le stockage sécurisé ; aucune trace (`print`, `debugPrint`) ; ordonnances jamais dans la galerie (à revérifier au lot g-bis).
- **Secrets :** aucune clé ni mot de passe dans le dépôt ni dans l'historique Git. Seul `.env.example` est suivi ; la doc SMTP contient un exemple fictif (`smtp.example.org`).
- **Base réelle :** R4 = 0 ordonnance « validée » sans validateur ; R5 et R6 vides.

## Ordre de correction proposé

1. S-01, S-04, S-07 (comptes et sessions), avec un test par point.
2. S-05 (journal), S-06 (HTTP / HTTPS).
3. S-03, après décision sur la durée.
4. S-02 : étude DEC-10, selon décision.
5. Points moyens au fil des lots concernés (S-11 et S-09 tout de suite : peu coûteux).

## État des corrections (02/10/2026)

| ID | État | Réalisation | Test |
|---|---|---|---|
| S-01 | **Corrigé** | Middleware `EnsureAccountIsActive` (Web et API, à chaque requête) : compte inactif ou archivé, organisation inactive → jetons révoqués, session fermée, 401 `account_disabled`. Révocation immédiate des jetons dès qu'un compte ou une organisation est désactivé (modèles `User`, `Organization`). La FOSA suspendue s'ajoutera au lot c1-bis. | `SecurityHardeningTest` (2) |
| S-02 | Étude faite | `10-etude-chiffrement-mobile.md` ; mise en œuvre au lot d. Sauvegarde automatique Android désactivée dès maintenant. | — |
| S-03 | **Corrigé** | Jeton de 30 jours prolongé à chaque échange (`ExtendMobileTokenLifetime`). Mobile : code PIN obligatoire (4 à 6 chiffres, empreinte salée et étirée), verrouillage après 5 min d'inactivité, au retour après 5 min et à chaque démarrage ; 5 codes faux → session fermée. Jeton expiré (401) : opérations **conservées en attente** (aucune tentative comptée), envoyées après reconnexion. | `SecurityHardeningTest`, `app_lock_test` (5), `sync_service_test` |
| S-04 | **Corrigé** | Middleware `EnforcePasswordChange` : tant que le mot de passe temporaire n'est pas remplacé, tout est refusé sauf changement de mot de passe, déconnexion et profil (403 `password_change_required` / redirection vers le profil). | `SecurityHardeningTest` |
| S-05 | **Corrigé** | Événements patient, ordonnance et dispensation : seuls les **noms** des champs modifiés sont journalisés. Identité du patient ajoutée aux champs masqués. Journal global et tableaux de bord sans événement de santé. Commande `pharmacare:audit:anonymize-health` pour l'existant. **Base réelle (R7, lecture seule) : 0 entrée concernée**, donc aucune anonymisation nécessaire. | `SecurityHardeningTest` (2) |
| S-06 | **Corrigé** | HTTP en clair autorisé seulement si `APP_ENV≠production` (manifeste Android généré selon la version) ; `app_config` refuse toute adresse non `https://` en production. | `app_config_test` |
| S-07 | **Corrigé** | Périmètre plateforme réservé à l'Admin Sago. | `SecurityHardeningTest` |
| S-09 | **Corrigé** | Connexion Web limitée à 6 essais par minute et par adresse IP. | `SecurityHardeningTest` |
| S-11 | **Corrigé** | `league/commonmark` 2.10.3, `axios` 1.20, `nanoid` corrigé : `composer audit` et `npm audit` sans vulnérabilité. | — |

Constatés pendant les corrections :

- Le changement de rôle d'un compte par l'API enregistrait les champs **avant** de vérifier le rôle demandé. Les vérifications passent maintenant avant toute écriture.
- La fabrique de comptes de test créait des comptes inactifs avec un mot de passe à changer. Elle crée maintenant des comptes ordinaires. Les tests qui vérifient un compte inactif ou un mot de passe temporaire le précisent.

## Plan de passage aux Laravel Policies (S-10)

Principe : une Policy par ressource, qui **appelle** les règles existantes (`GovernanceService`, `UserScopeService`) sans les dupliquer. Les contrôleurs appellent `$this->authorize(...)`, et chaque Policy a ses tests par rôle avec un vrai jeton. Passage au fil des lots, sur les écrans touchés :

| Lot | Policies |
|---|---|
| c1-bis | `UserPolicy` (créer, modifier, archiver, restaurer : `canManageUser`), `HealthFacilityPolicy` (valider, refuser, suspendre, réactiver), `ProjectPolicy` |
| c2 / c3 | `HealthFacilityPolicy` (déclaration et modification par l'Admin Projet), `SitePolicy` |
| f | `StandardListPolicy`, `ProductPolicy` (codification par la Coordination), `DonorPolicy` |
| g, g-bis | `PatientPolicy`, `PrescriptionPolicy`, `DispensationPolicy`, `AttachmentPolicy` (consultation contrôlée des photos) |
| h | `AnalysisPolicy` (lecture selon le périmètre) |
| d | `DevicePolicy` (révocation, synchronisation) |

## Vérification de sécurité de chaque lot (ajoutée à la recette)

À cocher dans le rapport de chaque lot :

- [ ] Nouvelles routes : permission, périmètre et rôle vérifiés côté serveur ; test avec un vrai jeton pour chaque rôle concerné.
- [ ] Aucune élévation possible (rôle supérieur, autre organisation, autre FOSA).
- [ ] Entrées validées ; champs autorisés explicites (`$fillable`) ; aucun `{!!`.
- [ ] Aucune donnée patient ni mot de passe dans les journaux, les messages d'erreur ou les captures.
- [ ] Fichiers : type, taille, stockage privé, accès contrôlé.
- [ ] Mobile : données sensibles uniquement dans la base locale (chiffrée dès DEC-10) ou le stockage sécurisé ; rien dans la galerie.
- [ ] `composer audit`, `npm audit` sans vulnérabilité haute non traitée.

# Audit de conformité — Phase 2

Date : 30 juillet 2026  
Références : cahier des charges et documents officiels `03`, `04`, `15`,
`16`, `17`, `18`.

## Verdict

L'application constitue un prototype avancé, mais **elle n'est pas encore
conforme à la fondation V1**. La reprise doit commencer par l'authentification,
la gouvernance, l'assistant et l'Offline-First avant de poursuivre les modules
métier.

## Synthèse

| Domaine | Statut | Conclusion |
|---|---|---|
| Splash natif | Conforme sous réserve d'un test final | Présent et distinct de la navigation Flutter |
| Login unique | Partiellement conforme | Un écran unique, récupération et connexion locale existantes |
| Authentification Laravel | Non conforme | Sanctum opaque utilisé, JWT demandé par la spécification |
| Profil/rôle/permissions/scope | Partiellement conforme | Payload disponible, anciens alias toujours actifs |
| Hiérarchie des cinq rôles | Partiellement conforme | Service officiel présent, branches historiques restantes |
| Isolation serveur | Partiellement conforme | Services de scope présents, couverture de tests insuffisante |
| Assistant 12 étapes | Non conforme | Web seulement, progression globale et validation manuelle |
| Centre de contrôle | Absent | Aucun modèle, API ou écran |
| Dashboard unique Flutter | Partiellement conforme | Un écran, mais cartes/actions trop statiques |
| Menu dynamique | Non conforme | Listes codées en dur par rôle, permissions peu prises en compte |
| PostgreSQL cible | Non démontré | Migrations surtout testées sur SQLite/local |
| SQLite mobile | Non conforme | Drift déclaré mais aucune base Drift implémentée |
| Offline-First | Non conforme | Cache/outbox JSON dans `FlutterSecureStorage` |
| Synchronisation | Non conforme | Pas de journal, idempotence, curseur ni gestion de conflits |
| Référentiels/listes | Partiellement conforme | CRUD/import/version présents, moteur multifactoriel incomplet |
| Stock/réception/FEFO | Partiellement conforme | Ledger solide, mais dépend de la fondation non conforme |
| Dispensation | Absent | Page provisoire uniquement |
| Inventaires | Absent | Permission/texte seulement |
| Commandes | Absent | Permission/texte seulement |
| Alertes/rapports | Absent ou provisoire | Pas d'entités V1 complètes |
| Tests | Non conforme | Suite interrompue au premier échec, anciens rôles dans les fixtures |

## Éléments conformes à conserver

- Une seule route de connexion Web et une seule page Login Flutter.
- Verrouillage après échecs, comptes actifs/inactifs et appareils révocables.
- Archivage logique des utilisateurs.
- `GovernanceService` définit les cinq codes officiels.
- Création descendante officielle déjà contrôlée dans plusieurs contrôleurs.
- `UserScopeService` filtre organisations, projets, structures et sites.
- `StockLedgerService` bloque les sorties rendant le stock négatif.
- Mouvements compensatoires au lieu d'une modification directe.
- FEFO par expiration puis numéro de lot.
- Réceptions avec quantités commandées, reçues, acceptées et rejetées.
- Audit présent sur plusieurs opérations sensibles.
- Un seul composant `HomePage` Flutter pour le Dashboard.
- Dépendances Drift déjà déclarées dans Flutter, réutilisables.

## Écarts critiques P0

### P0-01 — Authentification différente de la spécification

Le backend utilise `laravel/sanctum` et `personal_access_tokens`. La
spécification impose JWT, gestion du token et session.

Décision nécessaire avant correction :

- appliquer strictement JWT avec access token court et refresh token rotatif ;
- ou modifier officiellement la spécification pour conserver Sanctum.

Sans décision, l'étape Authentification Laravel ne peut pas être validée.

### P0-02 — Anciens rôles encore exécutables

`UserScopeService`, `GovernanceService`, des contrôleurs, migrations et tests
référencent encore :

- `platform_owner` ;
- `organization_admin` ;
- `project_coordinator` ;
- `facility_manager` ;
- `pharmacist`, `clinician`, `supervisor`.

Les alias de migration peuvent rester dans une migration historique, mais ils
doivent disparaître de la logique active et des tests.

### P0-03 — Assistant non transactionnel

`SetupProgress::current()` retourne une progression globale unique :

- aucun scope ;
- aucun état par étape ;
- aucun brouillon structuré ;
- aucune dépendance ;
- aucun contrôle que les données existent ;
- un bouton marque manuellement une étape comme terminée ;
- seul l'Owner peut utiliser l'assistant ;
- aucune API ni interface Flutter.

La migration utilise aussi `foreignUuid('completed_by')` alors que la clé
primaire actuelle de `users` est un entier. PostgreSQL peut refuser cette
contrainte sur un schéma vierge.

### P0-04 — Pas de base locale opérationnelle

`drift` est déclaré mais aucun `@DriftDatabase`, aucune table locale et aucun
DAO n'existent. Les listes et opérations sont sérialisées dans
`FlutterSecureStorage`, qui doit uniquement conserver les secrets.

Risques :

- limites de taille et performances ;
- corruption de la file entière ;
- absence de transactions ;
- aucune requête locale relationnelle ;
- aucune migration locale ;
- aucune gestion robuste des conflits.

### P0-05 — Synchronisation non idempotente

Les outbox Stocks et Réceptions rejouent de simples requêtes HTTP :

- aucun UUID d'opération ;
- aucune clé d'idempotence ;
- aucune version de ressource ;
- aucun curseur ;
- aucun ordre garanti entre opérations ;
- toute erreur HTTP conserve l'élément sans classification ;
- aucun conflit ni historique serveur.

Un timeout après acceptation serveur peut donc créer un doublon au renvoi.

### P0-06 — Menu codé en dur

`AppNavigationDrawer` et `MainNavigationShell` contiennent des tableaux
statiques par rôle. Ils ne sont pas dérivés de
`rôle + permissions + scope + modules actifs`. Plusieurs entrées n'ont aucune
route et affichent un cadenas.

### P0-07 — Tests non fiables

Résultat observé :

- analyse Dart : aucune erreur ;
- tests Laravel : 4 réussis puis échec du catalogue (`404` au lieu de `201`) ;
- 44 tests non exécutés après l'arrêt ;
- fixtures utilisant les anciens rôles ;
- `flutter test` ne termine pas dans le délai, bien que l'analyse Dart passe.

## Écarts du modèle de données

### Tables ou concepts absents

- `project_supply_settings` ;
- `care_levels` structurés ;
- `target_populations` ;
- `pathologies` et protocoles versionnés ;
- `site_standard_lists` ;
- `dispensations`, `dispensation_items` ;
- `inventories`, `inventory_lines` ;
- `orders`, `order_items` ;
- `alerts` transactionnelles ;
- `sync_operations`, `sync_conflicts`, curseurs et idempotence.

### Modèles incomplets

- `products` ne porte pas explicitement conditionnement et prix courant ;
- `batches` ne lie pas directement bailleur et projet ;
- la liste standard utilise un seul couple polymorphe `scope_type/scope_id`
  alors que la génération exige une combinaison de critères ;
- l'affectation versionnée d'une liste à un site est absente ;
- les paramètres d'approvisionnement par projet sont absents.

## Écarts de navigation et Dashboard

- Après session, Flutter va directement sur `/home` sans interroger l'état de
  configuration ni ouvrir un Centre de contrôle.
- L'Assistant mobile n'a pas de route.
- Le Centre de contrôle n'existe pas.
- Les cartes Dashboard sont majoritairement communes et ne proviennent pas
  d'une configuration serveur de widgets autorisés.
- Le Web partage la vue, mais plusieurs raccourcis sont conditionnés seulement
  par des permissions simples et non par le rôle/scope complet.
- Des textes encodés incorrectement (`DÃ©connexion`, etc.) subsistent dans des
  sources et expliquent les libellés anormaux observés sur téléphone.

## Plan correctif recommandé

Chaque lot exige validation avant le suivant.

1. **P2.1 — Décision et contrat d'authentification**
   JWT ou amendement officiel Sanctum ; contrat Login/refresh/logout/me.
2. **P2.2 — Gouvernance propre**
   Retirer les alias actifs, fermer les cinq rôles, corriger tests et scopes.
3. **P2.3 — Schéma de configuration**
   Progression par scope, états, dépendances, brouillons et validation réelle.
4. **P2.4 — API + écrans Assistant**
   Étape 1 Organisation uniquement, validation puis STOP.
5. **P2.5 — Centre de contrôle**
   Contexte et version de configuration/synchronisation.
6. **P2.6 — SQLite Drift**
   Base locale, migrations, repositories et transactions.
7. **P2.7 — Moteur de synchronisation**
   Outbox, UUID, idempotence, curseurs, conflits et historique.
8. **P2.8 — Navigation dynamique**
   Descripteurs serveur et filtrage par rôle/permissions/scope/modules.
9. **P2.9 — Dashboard unique dynamique**
   Widgets autorisés et agrégats strictement scopés.
10. **P2.10 — Reprise des référentiels**
    Seulement après validation de toute la fondation.

## Conclusion

Le code existant doit être corrigé, pas entièrement jeté. Les services de
scope, le registre de stock, les réceptions, les composants visuels et une
partie de l'authentification sont réutilisables. En revanche, il est interdit
de continuer les stocks ou la dispensation avant d'avoir validé les lots
P2.1 à P2.9.

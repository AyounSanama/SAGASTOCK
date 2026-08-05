# Matrice officielle des rôles et permissions

Statut : **référence technique à valider avant reprise du code**.

## Hiérarchie immuable

```text
Owner
  └── Admin Coordination
        └── Admin Projet
              └── Admin Site
                    └── Utilisateur Site
```

Aucun autre rôle système n'est autorisé.

| Rôle | Code technique | Périmètre | Crée uniquement |
|---|---|---|---|
| Propriétaire | `owner` | Plateforme | Admin Coordination |
| Admin Coordination | `coordination_admin` | Organisation/mission | Admin Projet |
| Admin Projet | `project_admin` | Un projet | Admin Site |
| Admin Site | `site_admin` | Un site | Utilisateur Site |
| Utilisateur Site | `site_user` | Un site | Personne |

## Règles d'autorisation

Une autorisation est accordée seulement si :

```text
rôle compatible + permission explicite + ressource incluse dans le scope
```

- Laravel est l'autorité finale.
- Masquer un menu Flutter ou Web n'est jamais une protection.
- Le rôle n'est jamais sélectionné à la connexion.
- Un compte ne peut pas élargir son propre périmètre.
- Un créateur n'affecte que le rôle immédiatement inférieur.
- L'Admin Site délègue seulement des permissions opérationnelles.
- Les droits administratifs ne sont jamais délégués à un Utilisateur Site.
- Toute modification d'accès est auditée.

## Matrice fonctionnelle

Légende : **G** gérer, **V** voir, **A** agrégé, **D** selon droits locaux,
**—** interdit.

| Domaine | Owner | Coordination | Projet | Admin Site | Utilisateur Site |
|---|---:|---:|---:|---:|---:|
| Organisations autorisées | G | V propre | V propre | V contexte | V contexte |
| Missions/pays | V | G | V propre | V contexte | — |
| Projets | V | G | G propre | V contexte | — |
| Bailleurs/programmes | V | G | V/affecter | V contexte | — |
| Modules/fonctionnalités | G global | G organisation | G projet autorisé | V | — |
| Formations sanitaires | V | V | G propre projet | V contexte | V contexte |
| Sites de dispensation | V | V | G propre projet | G propre site | V propre site |
| Admins Coordination | G | — | — | — | — |
| Admins Projet | V | G | — | — | — |
| Admins Site | V | V | G | — | — |
| Utilisateurs Site | V | V | V | G | — |
| Référentiel central | V | G | V | V | V |
| Listes standards | V | G/versionner | Affecter | V | V |
| Réceptions/lots/stocks | A | A | A projet | G | D |
| Dispensation | A | A | A projet | G | D |
| Inventaires | A | A | Validation projet | G | D |
| Commandes | A | A | G/approbation | Préparer | D |
| Alertes | A | A | A projet | G locales | V selon droit |
| Rapports | Global | Organisation | Projet | Site | Selon droit |
| Synchronisation | Supervision | Supervision | Projet | G locale | D |
| Audit | Global | Organisation | Projet | Site | Propre activité |

## Permissions techniques minimales

| Domaine | Permissions |
|---|---|
| Configuration | `organizations.view/manage`, `missions.view/manage`, `projects.view/manage`, `funding.view/manage`, `structures.view/manage`, `modules.manage`, `features.manage`, `supply_settings.manage` |
| Identités | `users.view/manage`, `roles.manage`, `sessions.revoke`, `audit.view` |
| Référentiels | `catalog.view/manage/import/publish`, `standards.view/manage/assign` |
| Stocks | `receipts.view/manage/validate`, `batches.view/manage`, `stocks.view/manage/adjust`, `transfers.view/manage`, `quarantines.manage`, `destructions.manage` |
| Dispensation | `dispensations.view/manage/validate`, `patients.view/manage`, `prescriptions.view/manage` |
| Inventaires | `inventories.view/manage/validate` |
| Commandes | `orders.view/manage/approve` |
| Pilotage | `alerts.view/manage`, `reports.view/export` |
| Synchronisation | `synchronization.run/view/resolve` |

## Isolation

- Coordination : son organisation, ses projets et descendants.
- Projet : son projet, ses formations sanitaires et sites.
- Site : uniquement les données portant son `site_id`.
- Les agrégats ascendants ne donnent aucun droit de modification descendant.
- Une ressource hors scope doit être refusée côté serveur.

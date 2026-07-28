# Audit du module Organisations et structures

Date de validation : 28 juillet 2026

## Statut

Le module d'administration **Organisations et structures est terminé pour le
portail Web et l'API centrale**.

Il couvre le troisième bloc de la feuille de route MVP :

- organisations ;
- pays et missions ;
- projets ;
- bailleurs et programmes ;
- formations sanitaires ;
- départements ;
- pharmacies ;
- sites de stockage et de dispensation ;
- activation des modules par organisation, projet ou formation sanitaire.

## Réalisations

### Hiérarchie organisationnelle

- Organisation avec coordonnées, pays, état actif et archivage logique.
- Mission reliée à une organisation et à un pays ISO.
- Projet relié à une mission de la même organisation.
- Bailleur et programme isolés dans leur organisation.
- Association et dissociation des bailleurs et programmes aux projets.
- Formation sanitaire reliée à une organisation, une mission et plusieurs
  projets.
- Département, pharmacie et site rattachés à une formation sanitaire.
- Types contrôlés pour les structures, départements, pharmacies et sites.

### Cycle de vie

- Création, consultation, modification et recherche.
- Archivage logique sans suppression physique.
- Listes d'archives et restauration des organisations, missions, projets,
  bailleurs, programmes, formations sanitaires, départements, pharmacies et
  sites.
- Confirmation avant les archivages et restaurations dans le portail Web.
- Réactivation automatique après restauration.
- Journalisation des créations, modifications, associations, dissociations,
  archivages, restaurations et activations.

### Activation des modules

Les modules suivants peuvent être activés ou désactivés explicitement :

- référentiels ;
- stocks ;
- réceptions ;
- dispensation ;
- inventaires ;
- commandes ;
- alertes ;
- rapports.

Le périmètre peut être une organisation, un projet ou une formation sanitaire.
La résolution applique la priorité formation sanitaire, puis projet, puis
organisation.

### Sécurité

- Permissions `structures.view`, `structures.manage` et `modules.manage`.
- Isolation serveur des organisations et de leurs sous-ressources.
- Validation des appartenances mission/projet/formation/département/pharmacie.
- Rejet des références provenant d'une autre organisation ou formation
  sanitaire.
- Les formulaires Utilisateurs et Sécurité prennent désormais en charge les
  périmètres formation sanitaire et site.

### Interface

- Écran responsive unique pour les formations sanitaires et leurs unités.
- Navigation cohérente entre organisations, missions, projets, financements et
  structures.
- Logo officiel PharmaCare sur les écrans du module.
- Messages de succès, erreurs de validation et confirmations.

## Contrôles exécutés

- Migration des tables de structures appliquée avec succès.
- Amorçage des nouvelles permissions appliqué.
- Compilation de tous les templates Blade réussie.
- 34 tests Laravel réussis.
- 219 assertions réussies.
- Tests dédiés :
  - cycle complet formation/département/pharmacie/site ;
  - archivage et restauration ;
  - activation de module ;
  - isolation inter-organisation ;
  - rejet des relations entre formations différentes ;
  - rendu de l'interface Web ;
  - CRUD et restauration des financements ;
  - restauration organisation, mission et projet.

## Hors périmètre de ce module

- Le fonctionnement SQLite hors ligne et la synchronisation mobile seront
  traités dans les étapes Flutter/SQLite et synchronisation.
- Les produits et nomenclatures médicales appartiennent au module suivant.

## Prochain module

Selon la feuille de route, le prochain module est **Référentiels, produits, lots
et listes standards**. Aucun développement de ce module n'a été commencé lors
de cette tranche.

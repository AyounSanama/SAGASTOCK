# Dossier des améliorations PharmaCare

Ce dossier constitue le **registre officiel des évolutions** de PharmaCare :
tout ce qui a été livré, tout ce qui est planifié et la justification métier
de chaque changement. Il complète les documents de cadrage (`docs/01` à
`docs/23`) sans les remplacer.

## Contenu

| Fichier | Rôle |
|---|---|
| [01-analyse-cahier-des-charges.md](01-analyse-cahier-des-charges.md) | Analyse complète du cahier des charges et des spécifications fonctionnelles (Mission, Projet, Comptes), confrontée au code au 30/09/2026 |
| [02-registre-ameliorations-realisees.md](02-registre-ameliorations-realisees.md) | Historique des améliorations déjà livrées, par lot et par date |
| [03-backlog-ameliorations.md](03-backlog-ameliorations.md) | Améliorations planifiées, priorisées, avec critères d'acceptation |
| [04-modele-fiche-amelioration.md](04-modele-fiche-amelioration.md) | Modèle à copier pour documenter une nouvelle amélioration |
| [05-audit-technique-v1.md](05-audit-technique-v1.md) | Audit technique avant tests V1 en ligne : bugs, sécurité, cloisonnement, offline, plan de correction par lots |

## Conventions

### Identifiants

Chaque amélioration porte un identifiant unique et définitif `AM-NNN`.
Un identifiant n'est jamais réutilisé, même si l'amélioration est abandonnée.

- `AM-001` à `AM-099` : améliorations réalisées avant la création de ce registre
  (reconstituées depuis l'historique Git et les rapports d'audit).
- `AM-100` et suivants : améliorations identifiées à partir du 30/09/2026.

### Statuts

| Statut | Signification |
|---|---|
| `Proposée` | Identifiée, non encore validée par le porteur |
| `Validée` | Acceptée, prête à être planifiée |
| `En cours` | Développement démarré |
| `En recette` | Développée, en attente de tests/validation terrain |
| `Livrée` | Validée et commitée sur la branche principale |
| `Bloquée` | En attente d'une décision (référencée) |
| `Abandonnée` | Retirée, avec motif |

### Priorités

| Priorité | Signification |
|---|---|
| **P0** | Bloquant : conformité, sécurité, intégrité des données |
| **P1** | Nécessaire au MVP / pilote terrain |
| **P2** | Important, peut suivre le pilote |
| **P3** | Confort ou version ultérieure |

### Domaines

`GOUV` gouvernance et rôles · `CONF` configuration Mission/Projet ·
`REF` référentiels et listes standards · `STRUCT` FOSA et sites ·
`STOCK` · `DISP` dispensation · `INV` inventaires · `CMD` commandes ·
`ALERT` · `RAPP` rapports · `SYNC` offline et synchronisation ·
`SEC` sécurité · `UI` interfaces Web/mobile · `QA` qualité et tests ·
`DOC` documentation.

## Règles de tenue du registre

1. Toute amélioration démarrée doit d'abord exister dans le backlog.
2. Lorsqu'elle est livrée, elle est **déplacée** vers le registre des
   réalisations avec la date, le commit et les tests exécutés.
3. Un changement de règle métier cite sa source (cahier des charges,
   clarification écrite du porteur, décision `docs/06`).
4. Aucune amélioration n'est marquée `Livrée` si ses tests échouent ou si
   elle n'est pas commitée.
5. Le registre est mis à jour dans le même commit que le code concerné.

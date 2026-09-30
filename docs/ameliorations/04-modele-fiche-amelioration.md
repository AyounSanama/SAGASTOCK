# Modèle de fiche d'amélioration

Copier ce bloc dans [03-backlog-ameliorations.md](03-backlog-ameliorations.md)
pour toute nouvelle amélioration, puis ajouter une ligne au tableau de synthèse.

```markdown
### AM-NNN · <Titre court> · P<0-3> · <DOMAINE>

- **Statut :** Proposée | Validée | En cours | En recette | Livrée | Bloquée | Abandonnée
- **Source :** <règle RG-xxx, cahier des charges §, clarification du porteur, anomalie>
- **Contexte :** <situation actuelle et problème constaté, avec fichiers concernés>
- **Travail :**
  - <base de données / migration>
  - <API / règles métier>
  - <Web>
  - <mobile / offline>
- **Critères d'acceptation :**
  - <résultat observable et testable>
  - <contrôle de permission et de périmètre>
  - <tests automatisés attendus>
- **Dépend de :** <AM-xxx, DEC-xx>
- **Risques :** <impact sur les données existantes, migration, sécurité>
```

## Au moment de la livraison

Déplacer l'entrée vers [02-registre-ameliorations-realisees.md](02-registre-ameliorations-realisees.md) :

```markdown
| AM-NNN | <DOMAINE> | <description courte> | <commit> · <tests exécutés et résultat> |
```

## Définition de terminé

Reprend `docs/17-criteres-acceptation-officiels.md` :

- backend, base, Web et mobile concernés reliés ;
- permissions et périmètres contrôlés côté serveur ;
- validations et messages en français ;
- migrations valides sur SQLite et PostgreSQL ;
- tests concernés réussis, `flutter analyze` sans erreur ;
- hors ligne et synchronisation testés lorsque concernés ;
- aucune régression ;
- validation explicite du porteur avant l'étape suivante.

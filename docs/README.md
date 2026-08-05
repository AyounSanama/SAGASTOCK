# Documentation officielle PharmaCare

La source métier unique est le cahier des charges validé. Les clarifications
écrites du porteur prévalent sur les documents historiques. La matrice des
permissions, le modèle de données, les formules validées et les critères
d'acceptation constituent sa traduction technique exécutable.

## Références de Phase 1

- [Exigences consolidées](01-exigences-consolidees.md)
- [Architecture cible](02-architecture.md)
- [Modèle conceptuel et dictionnaire](03-modele-donnees.md)
- [Matrice officielle des rôles et permissions](04-roles-permissions.md)
- [MVP et feuille de route](05-mvp-roadmap.md)
- [Décisions à valider](06-decisions-ouvertes.md)
- [Workflow officiel de configuration](15-workflow-configuration-officiel.md)
- [Formules métier](16-formules-metier-officielles.md)
- [Critères d'acceptation](17-criteres-acceptation-officiels.md)
- [Modules et phasage officiels](18-modules-et-phasage-officiels.md)
- [Audit de conformité Phase 2](19-audit-conformite-phase2.md)

## Principes non négociables

- Flutter, Laravel, PostgreSQL, SQLite, REST et Offline-First.
- Cinq rôles uniquement : Owner, Coordination, Projet, Admin Site, Utilisateur.
- Autorisation dérivée de `rôle + permission + scope`.
- Un seul Login et un seul Dashboard Flutter.
- Stock géré au niveau du site.
- Registre immuable des mouvements comme source de vérité.
- Aucune règle médicale ou formule métier inventée ou codée en dur.
- Une étape doit être terminée, testée et validée avant la suivante.
- Android et iOS partagent le même code et les mêmes critères.

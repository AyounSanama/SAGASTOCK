# Rôles et permissions

Les rôles sont des modèles initiaux. Les permissions restent configurables et
leur application côté API est obligatoire.

| Fonction | Plateforme | ONG | Projet | FOSA | Pharmacien | Clinicien | Superviseur |
|---|---:|---:|---:|---:|---:|---:|---:|
| Gérer les organisations | Oui | Non | Non | Non | Non | Non | Lecture |
| Gérer missions/projets | Global | Oui | Limité | Non | Non | Non | Lecture |
| Gérer les structures | Global | Oui | Oui | Limité | Non | Non | Lecture |
| Gérer utilisateurs | Global | ONG | Projet | Site | Non | Non | Non |
| Gérer rôles/permissions | Oui | Périmètre | Périmètre | Limité | Non | Non | Non |
| Modifier référentiels | Oui | Oui | Selon droit | Non | Non | Non | Lecture |
| Publier listes standards | Oui | Oui | Selon droit | Non | Non | Non | Lecture |
| Enregistrer réception | Lecture | Validation | Validation | Oui | Oui | Non | Lecture |
| Enregistrer dispensation | Lecture | Lecture | Lecture | Oui | Oui | Selon droit | Lecture |
| Réaliser inventaire | Lecture | Analyse | Validation | Oui | Oui | Non | Observation |
| Valider inventaire | Selon droit | Oui | Oui | Selon droit | Non par défaut | Non | Selon mandat |
| Préparer commande | Lecture | Validation | Validation | Oui | Oui | Non | Lecture |
| Voir prix/coûts | Configurable | Configurable | Configurable | Configurable | Configurable | Non par défaut | Configurable |
| Voir données patients | Non par défaut | Agrégé | Selon mandat | Périmètre | Périmètre | Périmètre clinique | Selon mandat |
| Exporter données sensibles | Autorisé/audité | Autorisé/audité | Selon droit | Selon droit | Non par défaut | Non par défaut | Selon mandat |
| Résoudre conflit critique | Oui | Oui | Selon droit | Non par défaut | Non | Non | Non |
| Consulter audit | Global | ONG | Projet | Site limité | Propre activité | Propre activité | Selon mandat |

## Règles

- Le propriétaire de la plateforme ne bénéficie pas automatiquement d'un accès
  clinique aux patients.
- Toute permission est liée à un périmètre explicite.
- La possession d'un rôle ne contourne jamais l'isolation de l'organisation.
- Les permissions sensibles exigent audit et, si nécessaire, justification.
- Les comptes sont personnels ; aucun compte partagé de formation sanitaire.
- Les appareils sont enregistrés et révocables.


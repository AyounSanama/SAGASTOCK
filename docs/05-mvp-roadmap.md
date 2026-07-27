# MVP et feuille de route

## Définition du MVP

Le MVP est un pilote terrain complet, pas une démonstration. Il inclut :

1. socle Laravel/PostgreSQL, portail Web et CI ;
2. authentification, appareils, rôles, permissions et audit ;
3. organisations, missions, projets, bailleurs et formations sanitaires ;
4. référentiels, produits, lots et listes standards ;
5. application Flutter Android/iOS et base SQLite ;
6. synchronisation bidirectionnelle et gestion des conflits ;
7. réceptions, mouvements, transferts, soldes et FEFO ;
8. dispensation simple, patient minimal et ordonnance sécurisée ;
9. inventaires, commandes et approbations ;
10. alertes essentielles et rapports PDF/Excel prioritaires ;
11. sauvegarde, restauration, sécurité, performance et pilote terrain.

## Versions suivantes

### Version 2

- Dossier patient longitudinal enrichi.
- Programmes VIH, TB, paludisme, nutrition et santé maternelle.
- Contrôle des prescriptions selon protocoles validés.
- Chaîne du froid, formation et supervision.
- Rapports bailleurs et indicateurs avancés.

### Version 3

- Prévisions statistiques et détection d'anomalies.
- Analyses pharmaco-épidémiologiques avancées.
- ABC/VEN, GS1 complet et optimisation financière.
- DHIS2, OpenMRS et autres intégrations.
- Paquets sécurisés de transfert sans Internet entre appareils.

## Tranches verticales

Une tranche n'est terminée que lorsque base, règles métier, API, permissions,
audit, Flutter, SQLite, synchronisation, erreurs, tests et documentation sont
traités.

## Critères de sortie du MVP

- Android et iOS passent les scénarios critiques.
- Deux appareils peuvent travailler hors ligne sans doublon silencieux.
- Toutes les opérations de stock sont explicables par le registre.
- Aucune fuite inter-organisation dans les tests d'autorisation.
- Réception, dispensation, inventaire et commande fonctionnent hors ligne.
- Sauvegarde restaurée avec succès sur un environnement vierge.
- Rapports prioritaires rapprochés avec les données sources.
- Pilote validé par des utilisateurs terrain.


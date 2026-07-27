# Exigences consolidées

## Vision

SAGASTOCK centralise la gestion des produits médicaux depuis leur réception
jusqu'à leur dispensation. La solution doit réduire les ruptures, les
péremptions et les erreurs, tout en produisant des données fiables pour les ONG,
les autorités sanitaires et les bailleurs.

## Utilisateurs et périmètres

La hiérarchie cible est :

`Plateforme → Organisation → Mission/Pays → Projet → Bailleur/Programme →
Formation sanitaire → Site de stock/dispensation → Utilisateur/Appareil`

Le système est multi-organisation, multi-pays, multi-projet, multi-bailleur,
multi-site, multi-utilisateur et multi-appareil. Toute donnée métier porte le
périmètre nécessaire à son isolation.

## Produits à livrer

### Application mobile

- Flutter/Dart pour Android et iOS dès la version 1.
- Téléphone et tablette, avec priorité ergonomique aux tablettes terrain.
- Consultation et opérations critiques sans connexion.
- SQLite locale chiffrée, synchronisation automatique et manuelle.
- Lecture de codes-barres lorsque le matériel le permet.
- Impression ou partage des documents lorsque le système le permet.

### Portail Web

- Administration de la plateforme et des organisations.
- Configuration des missions, projets, programmes et bailleurs.
- Utilisateurs, rôles, permissions et appareils.
- Référentiels, produits et listes standards.
- Supervision, tableaux de bord, rapports et exports.
- Gestion des conflits de synchronisation et consultation des audits.

### Backend

- API REST HTTPS Laravel.
- PostgreSQL comme base centrale.
- Stockage objet privé pour ordonnances, photos et rapports.
- Tâches asynchrones pour rapports, alertes et traitements lourds.
- Documentation OpenAPI.

## Catalogue fonctionnel

### Administration et identité

- Organisations, missions, pays, projets, bailleurs et programmes.
- Formations sanitaires, départements, pharmacies et sites de dispensation.
- Utilisateurs, rôles personnalisables, permissions et périmètres.
- Activation des modules par organisation, projet ou formation sanitaire.
- Appareils autorisés, révocation, sessions et verrouillage.
- Journal d'audit et journal d'accès aux fichiers sensibles.

### Référentiels

- Géographie, niveaux de soins et types d'activité.
- Produits, catégories, familles thérapeutiques, unités, formes, dosages et
  voies d'administration.
- Pathologies, populations cibles, protocoles, fournisseurs et partenaires.
- Import/export Excel et versionnement lorsque nécessaire.

### Produits, kits et listes standards

- Médicaments, consommables, dispositifs, réactifs et intrants de programme.
- Codes internes, codes-barres, QR et architecture extensible GS1.
- Listes standards versionnées par organisation, projet, programme, bailleur,
  niveau de soins, activité, pathologie, population et structure.
- Publication contrôlée ; modification réservée aux rôles de coordination.
- Gestion explicite des produits hors liste selon autorisation.

### Lots et stocks

- Lots, dates de fabrication/expiration, prix, fournisseur et provenance.
- Stock théorique, physique, disponible et réservé.
- Réceptions, entrées, sorties, transferts, retours et ajustements.
- Pertes, détériorations, péremptions, quarantaines et destructions.
- FEFO lors des sorties.
- Absence de suppression physique des mouvements validés.
- Mouvement compensatoire pour toute correction.
- Rapport de réception avec quantités commandées/reçues et écarts.

### Dispensation et patients

- Destinations : patient, service hospitalier, communauté ou autre destination
  configurée.
- Patient avec identifiant unique et données minimisées.
- Ordonnance, prescripteur, service d'origine et pièce jointe privée.
- Dispensation totale, partielle, en attente, retour et rupture.
- Suggestion FEFO et substitution uniquement selon règle validée.
- Programmes VIH, tuberculose, paludisme, nutrition, santé maternelle et
  infantile activables.
- Historique longitudinal et rappels lorsque le projet les autorise.
- Contrôles de protocoles et de posologie uniquement après validation clinique.

### Inventaires et commandes

- Inventaires mensuels ou exceptionnels, par produit et lot.
- Gel logique du périmètre, comptage physique, écarts et justifications.
- Validation, ajustements tracés et valorisation financière.
- Commandes mensuelles ou exceptionnelles, circuit d'approbation et réception.
- Proposition de commande seulement après inventaire conforme lorsque la règle
  du projet l'impose.

### Alertes et indicateurs

- Rupture, pré-rupture, surstock, article dormant.
- Péremption et pré-péremption.
- Inventaire en retard, commande en attente et synchronisation échouée.
- Priorité, destinataire, acquittement, résolution et historique.
- Déduplication des alertes actives.
- CMM, couverture, jours de rupture, consommations et écarts d'inventaire.
- Formules centralisées, versionnées, auditées et testées.

### Rapports et outils

- Réception, stock, consommation, mouvements et dispensation.
- Inventaire, écarts, pertes, péremptions, ruptures et commandes.
- Supervision, programmes et analyses autorisées.
- PDF, Excel et CSV lorsque pertinent.
- Signatures et archivage selon le type de document.
- Bons de commande, fiches de stock, feuilles d'inventaire, certificats de
  retrait/donation/retour, registre de stupéfiants et étiquettes.

### Formation, supervision et chaîne du froid

- Contenus, cours, progression et évaluations.
- Protocoles, bonnes pratiques, stockage et chaîne du froid.
- Missions de supervision, grilles configurables, photos, notation,
  recommandations et plans d'action.
- Équipements, relevés de température, seuils, alertes et historique.

### Analyses avancées

- Analyse ABC/VEN et optimisation financière.
- Antibiotiques, antimicrobiens et antipaludiques.
- VIH, tuberculose, nutrition, grossesse, pédiatrie et morbidité.
- Conformité aux protocoles et détection d'anomalies.
- Intégrations futures DHIS2, OpenMRS, ERP et systèmes nationaux.

## Exigences non fonctionnelles

- Interface accessible à des utilisateurs peu formés.
- Français au départ, architecture internationalisable.
- Bon fonctionnement sur appareils de capacité moyenne.
- Reprise après interruption réseau et électrique.
- Chiffrement en transit et protection du stockage local.
- RBAC et contrôle de périmètre côté backend.
- Sauvegardes chiffrées et restaurations testées.
- Observabilité des erreurs, performances et synchronisations.
- Environnements développement, test, préproduction et production.
- Tests unitaires, API, autorisations, widgets, intégration, offline et terrain.


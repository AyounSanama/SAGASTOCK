# État d'avancement par rapport au cahier des charges · 06/10/2026

Vérifié dans le code (backend Laravel et application Flutter), à partir de l'audit du 01/10 (`07-audit-cahier-des-charges.md`) mis à jour avec les livraisons des 02 au 06/10. Référence : cahier des charges du client et sa version corrigée (octobre 2026).

Légende : ✅ fait · 🟡 partiel · ❌ à faire · ⚠️ décision à prendre. « Niveau » = feuille de route V1 (`docs/feuille-de-route-v1.md`).

Limites : vérification dans le code et par les tests automatiques (354 tests backend au vert), pas écran par écran sur des données réelles (base réelle absente de ce PC). L'assistant mobile n'a pas été compilé (pas de Flutter sur ce PC). Les livraisons du 06/10 ne sont pas encore commitées.

## Synthèse

| Domaine | ✅ | 🟡 | ❌ | Niveau qui le traite |
|---|---:|---:|---:|---|
| 1. Comptes, rôles, cloisonnement | 9 | 1 | 1 | 2, 3 |
| 2. Configuration Coordination (projets) | 7 | 4 | 1 | 2, 6 |
| 3. Configuration Projet et FOSA | 5 | 2 | 1 | 5 |
| 4. Liste Standard | 2 | 2 | 5 | 6 |
| 5. Entrées en stock | 2 | 2 | 2 | 7 |
| 6. Dispensation | 6 | 2 | 9 | 7, 8 |
| 7. Inventaire et bon de commande | 3 | 1 | 4 | 7, 9 |
| 8. Analyses et rapports | 1 | 0 | 7 | 9 (V1), V3 |
| 9. Hors ligne et sécurité | 5 | 1 | 2 | 10 |
| 10. Tableau de bord Admin Sago | 2 | 1 | 3 | 2, V4 |
| 11. Modifications, formation (V3/V4) | 0 | 2 | 4 | V3, V4 |
| 12. Écrans des maquettes | 2 | 1 | 3 | 3, 4, 5 |

## 1. Comptes, rôles, cloisonnement (cahier §3)

| Fonction | État | Détail |
|---|---|---|
| Comptes individuels, mot de passe changé à la 1re connexion | ✅ | Web et mobile |
| 6 niveaux d'accès (Sago, Coordination, Coordination lecture seule, Projet, Admin Site, Utilisateur Site) | ✅ | Lecture seule = rôle Coordination + indicateur `read_only`, refus serveur |
| Coordination crée les comptes Admin Projet et Coordination (lecture seule) | ✅ | « Ma Coordination », Web et mobile |
| Admin Projet crée Admin Site **et** Utilisateur Site, seulement pour une FOSA validée | ✅ | Règle serveur (`GovernanceService`) ; écrans Admin Projet au niveau 5 |
| FOSA déclarée « en attente », validée une seule fois, refus avec motif, suspension | ✅ | Par la Coordination ⚠️ le cahier dit « Admin Sago » (décision du 01/10 à confirmer avec le client) |
| Journal des actions (création, désactivation, validation) | ✅ | Onglet « Journal des actions » |
| L'Admin Sago voit toutes les FOSA et leurs comptes, peut suspendre | ❌ | Fait par la Coordination ; à trancher (C1) |
| Cloisonnement organisation / coordination / projet / FOSA | ✅ | Tests de périmètre + Policies (niveau 3) |
| Langue français / anglais | ✅ | Sélecteur FR/EN |
| Permissions par rôle conformes aux décisions | 🟡 | Matrice proposée, **en attente de validation** (`11-matrice-roles-permissions.md`) |
| Seeder sans suppression, interdit sur la base réelle | ✅ | 06/10 |

## 2. Configuration Coordination (cahier II.1)

| Fonction | État | Détail |
|---|---|---|
| Pays, ONG/MoH, bailleurs (un bailleur peut soutenir plusieurs projets), codes projet / programme MoH, intitulé | ✅ | |
| Assistant « Créer un projet / programme » en 4 étapes, brouillon | ✅ Web · 🟡 mobile | 06/10 ; mobile écrit, à compiler sur Codemagic |
| Niveaux de soins + « Ajouter un service » | ✅ | |
| Niveau « Programme Laboratoire » | ❌ | Niveau 6 |
| Populations par défaut (Adultes, Femmes enceintes, Enfants < 5 ans) | ✅ | Ajout d'une population : possible, à confirmer (annexe C) |
| Pathologies selon le couple niveau de soins × population | 🟡 | Aujourd'hui par population seulement ; niveau 6 |
| Examens de laboratoire par population | 🟡 | Référentiel présent, pas de saisie dédiée ; niveau 6 |
| 5 catégories de FOSA (HR, HD, CMA, CSI, centre ambulatoire) | ✅ | |
| Liste générée en tableau, produits retenus ou non | ✅ | Assistant, étape 3 |
| Liste cochée selon la catégorie dès la configuration du projet | 🟡 | La catégorie s'applique à la FOSA ; niveau 6 |
| Paramètres d'approvisionnement du projet (1-12 mois, DL, stock de sécurité 0,25-2, 3 dates) | ✅ | 06/10 (migration à appliquer sur la base réelle après validation) |

## 3. Configuration Projet et FOSA (cahier II.2, II.3)

| Fonction | État | Détail |
|---|---|---|
| Informations héritées de la Coordination (pays, ONG, code, intitulé, durée) | ✅ | |
| Fiche FOSA : niveau, catégorie, populations, pathologies issus de la configuration | ✅ | |
| Paramètres FOSA préremplis depuis le projet, modifiables, historisés | ✅ | |
| « Appliquer à toutes les FOSA » avec confirmation | 🟡 | Serveur (API) ; bouton Web à ajouter au niveau 5 |
| Liste Standard de chaque FOSA générée, en consultation | ✅ | Calculée par FOSA, filtrée par la liste validée du projet |
| Site de dispensation invisible sous la FOSA (DEC-05) | ✅ | |
| Écrans Admin Projet des maquettes (tableau de bord, Projet & FOSA, fiche FOSA, Liste Standard) | ❌ | Niveau 5 (8 maquettes, Web et mobile) |
| Accès FOSA seulement si FOSA autorisée et projet actif | 🟡 | FOSA validée contrôlée ; projet clôturé à vérifier |
| Écrans d'entrée par code mission / projet / FOSA | ⚠️ | Remplacés par la connexion par compte (C-02) |

## 4. Liste Standard (cahier 4-i)

| Fonction | État | Détail |
|---|---|---|
| Seule la Coordination modifie la liste (refus serveur) | ✅ | |
| Code, désignation, conditionnement ; en-tête ONG / bailleur | ✅ | |
| Organisation par pathologie / activité, filtres | 🟡 | Colonne pathologie ; filtres de la maquette Admin Projet au niveau 5 |
| Ajout de produit, codification propre à l'ONG, import Excel | 🟡 | Ajout dans l'assistant ✅ ; catalogue et import existants mais fermés à la Coordination en V1 ; niveau 6 |
| Décocher des articles **par FOSA** | ❌ | Niveau 6 |
| Article hors liste livré par un tiers | ❌ | Règle à valider par le client ; niveau 6 |
| Transfert d'une liste de pathologies d'un protocole à un autre | ❌ | V2 |
| Lien code-barres ↔ produit géré par la Coordination | ❌ | Niveau 6 |
| Prix unitaires dans la liste | ❌ | V3 (« Modification de l'application ») |

## 5. Entrées en stock (cahier 4-ii)

| Fonction | État | Détail |
|---|---|---|
| Lots, dates de péremption, quantités livrées, plusieurs lots par produit | ✅ | |
| Liste des produits issue de la Liste Standard | ✅ | |
| Origine « ONG/Bailleur » (ex. MDM/GFFO5) ou « Autre » | ❌ | Champ texte libre / fournisseur ; niveau 7 |
| Quantités commandées et « rapport de réception » | 🟡 | Écarts commandé / reçu existants ; formule à recevoir du client |
| Risque de péremption par lot, rouge sous 2 mois | 🟡 | Calcul par lot à vérifier à l'écran ; formule corrigée au niveau 9 |
| Saisie par l'Utilisateur Site | ❌ | Permission `receipts.manage` (matrice en attente) |

## 6. Dispensation (cahier 4-iii)

| Fonction | État | Détail |
|---|---|---|
| Destinations patient, service, autre | ✅ | « Communauté » masquée en V1 |
| Destinations « Périmés/détériorés » et « Retour pharmacie ONG » | ❌ | Niveau 7 |
| Photo d'ordonnance obligatoire avant l'ordonnance | 🟡 | Obligatoire sur mobile ; facultative sur le Web |
| Photo jamais depuis la galerie, chiffrée, inaccessible au personnel | ❌ | Le bouton « galerie » existe ; photo non chiffrée ; niveaux 8 et 10 |
| Date et heure bloquées (date du jour) | ❌ | Saisie libre ≤ maintenant ; niveau 7 |
| En-têtes par type de sortie (patient : nom, âge, sexe, n° d'ordonnance, prescripteur) | 🟡 | Présents, champs obligatoires à aligner (annexe C) |
| Couple ONG/Bailleur | ❌ | Niveau 7 |
| Statut de l'ordonnance : délivrée / en attente / partielle | ✅ | |
| Pas plus que le stock, pas de lot périmé, FEFO | ✅ | |
| Statut « R » (rupture) | ✅ | |
| Pré-rupture et pré-péremption à la dispensation | ❌ | Dépend de la CMM (niveau 9) ; formule à trancher (D2) |
| Contrôle code-barres | ❌ | V4 (GS1) |
| Substitutions, motif femme enceinte (CPN1-CPN4), indication des antibiotiques | ❌ | V3/V4 |
| Dispensation programme (type de prise en charge, examens, rappel) | ❌ | V3 |
| Identifiant unique du patient | ✅ | |
| Règle « nouveau patient » | ❌ | À trancher (D3) |
| Validation clinique (protocoles, posologies) | ✅ masquée | Prévue en V4 |

## 7. Inventaire et bon de commande (cahier 4-iv)

| Fonction | État | Détail |
|---|---|---|
| Inventaire avec participants, date, quantités par lot, justification des écarts, validation | ✅ | |
| « Sans inventaire, pas de BC » | ✅ | Refus serveur |
| Commande soumise, décision, préparation | ✅ | |
| Couple ONG/Bailleur | ❌ | Niveau 7 |
| Bon de commande **proposé automatiquement** après l'inventaire | ❌ | Quantités saisies à la main ; dépend de la CMM (niveau 9) |
| Analyse de l'inventaire (% d'écart, écarts positifs / négatifs, rapport signé) | ❌ | Niveau 9 / V3 (signature) |
| Signe de l'écart | ⚠️ | Code : inventorié − théorique ; cahier : théorique − inventorié. À aligner |
| Saisie par l'Utilisateur Site | ❌ | Permission `inventories.manage` (matrice en attente) |
| Présentation simple pour des utilisateurs peu scolarisés | 🟡 | À revoir avec les maquettes du niveau 7 |

## 8. Analyses et rapports (cahier 4-v)

| Fonction | État | Détail |
|---|---|---|
| Rapports opérationnels (compteurs, mouvements) | ✅ | |
| Consommations hebdo / CMM / jours de rupture | ❌ | Niveau 9 |
| Dormants, pré-périmés, risque de péremption, rupture, pré-rupture, surstock | ❌ | Niveau 9 ; le tableau de bord Coordination affiche « Disponible avec les analyses de base » |
| Commande exceptionnelle calculée | ❌ | Niveau 9 |
| Pharmaco-épidémiologie (antibiotiques, antipaludiques, SRO, femmes enceintes) | ❌ | V3 |
| Morbidité, services, programmes VIH/TB | ❌ | V3 |
| Analyses financières | ❌ | V3 |
| Rapports signés, consommation journalière, commande mensuelle | ❌ | V3 |

## 9. Hors ligne et sécurité (cahier III-2, contraintes)

| Fonction | État | Détail |
|---|---|---|
| Travail hors ligne, file d'attente, synchronisation automatique, sans doublon | ✅ | |
| 1re connexion en ligne, puis hors ligne | ✅ | |
| Code PIN de l'application | ✅ | |
| Mesures de sécurité serveur (comptes désactivés, HTTPS, journal sans donnée patient…) | ✅ | Lot 16 (S-01 à S-11) |
| Suivi des échecs de synchronisation | ✅ | Tableau de bord Coordination |
| Écran des opérations en échec / conflits sur le téléphone | 🟡 | Niveau 10 |
| Base locale et photos chiffrées sur le téléphone | ❌ | Niveau 10 |
| Partage sans internet entre tablettes (paquet chiffré et signé) | ❌ | V2 |

## 10. Tableau de bord Admin Sago (cahier « Tableau de bord »)

| Fonction | État | Détail |
|---|---|---|
| Liste des ONG, validation des accès ONG | ✅ | |
| Références communes (standards plateforme) | ✅ | |
| Accès aux Listes Standard | 🟡 | Par organisation, pas de vue transversale |
| Liste des formations sanitaires | ❌ | Lié à C1 |
| Formations en ligne | ❌ | V4 |
| Mises à jour de l'application | ❌ | V4 |

## 11. « Modification de l'application » et formation (V3/V4)

| Fonction | État | Détail |
|---|---|---|
| Prix unitaires, coûts, perte en argent | 🟡 | Valeur des écarts d'inventaire calculée ; prix dans la liste à faire |
| Gestion des kits | 🟡 | Kits dans le catalogue, masqués en V1 |
| Codes-barres GS1 | ❌ | V4 |
| Duplication des alertes | ❌ | V3 |
| Module formation / évaluation, outils de la pharmacie | ❌ | V4 |
| Suivi de température | ❌ | V4 |

## 12. Écrans des maquettes

| Écrans | État | Détail |
|---|---|---|
| « Ma Coordination » (6 maquettes Web et mobile) | ✅ | Écarts mineurs : bouton « Modifier » des comptes |
| Tableau de bord Coordination | 🟡 | Indicateurs FOSA et synchro ; ruptures, péremptions, graphique et filtres au niveau 9 |
| Menu Coordination (Référentiels) et assistant en 4 étapes | ✅ | 06/10 (mobile à compiler) |
| Écrans Admin Projet (8 maquettes) | ❌ | Niveau 5 |
| Mode clair / sombre | ❌ | Niveau 4 |
| Bandeau « base de démonstration / de travail » | ❌ | Prévu avec la copie de travail |

## Reste à faire, dans l'ordre de la feuille de route

| Niveau | Contenu restant |
|---|---|
| **Bloquant** | Fichier de la base réelle (ancien PC) ; validation de la matrice rôles × permissions ; décisions C1 (validation des FOSA), D1 à D8 (questions au client) |
| 1 | Scénarios hors ligne sur le téléphone avec captures |
| 2 | Commit des livraisons du 06/10 ; compilation mobile ; check-up des 16 maquettes ; audit des doublons |
| 3 | Bouton « Modifier » des comptes de la coordination ; filtres et badge « Synchronisé il y a » du tableau de bord ; captures téléphone |
| 4 | Mode clair / sombre (Web et mobile) |
| 5 | Écrans Admin Projet (8 maquettes) ; comptes Utilisateur Site ; « Appliquer à toutes les FOSA » sur le Web |
| 6 | Programme Laboratoire ; pathologies par niveau × population ; liste par catégorie ; import Excel et codification ONG par la Coordination ; décochage par FOSA ; hors liste ; codes-barres |
| 7 | Origine ONG/Bailleur (entrées, dispensation, inventaire) ; destinations « Périmés » et « Retour pharmacie ONG » ; date bloquée ; Utilisateur Site : entrées et inventaire ; signe de l'écart |
| 8 | Capture par le téléphone ; photo sans galerie |
| 9 | Analyses de base (CMM, rupture, pré-rupture, surstock, dormants, péremption) ; BC proposé ; analyse de l'inventaire ; indicateurs du tableau de bord |
| 10 | Chiffrement de la base locale et des photos ; écran des conflits et opérations en échec |
| 11-12 | Tests finaux, recette V1, déploiement |
| V2 à V4 | Partage hors ligne, pharmaco-épidémiologie, programmes VIH/TB, analyses financières, signature, GS1, kits, formation, température |

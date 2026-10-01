# Audit par rôle — Cahier des charges corrigé (octobre 2026)

Statut : **audit uniquement, aucun code modifié**. En attente de validation.
Date : 01/10/2026. Base de travail : commit `9017117`.
Mise à jour du 01/10 : la validation des FOSA passe à l'Admin Coordination (le rôle de l'Admin Sago est inchangé) ; deux ajouts en V1 (analyses de base, capture par le téléphone) ; évaluation des librairies dans `08-evaluation-librairies-capture.md`.

## 0. Sources et méthode

**Ordre de priorité appliqué**

1. Décisions validées (registre `02`, audits `05` et `06`) : DEC-01 à DEC-12, règles V1, charte.
2. Document « Configuration Mission » (2 captures).
3. Cahier des charges corrigé.

Chaque contradiction est signalée en section 4, sans arbitrage de ma part.

**Ce qui a été vérifié**

- Menus Web : `ApplicationNavigationService` et `config/pharmacare_v1.php`.
- Menus mobile : `ApplicationAccess` et `main_navigation_shell.dart`.
- Routes et restrictions V1 : `EnforceV1ModuleAvailability`.
- Permissions par rôle : `DatabaseSeeder`, matrice stricte.
- Champs et règles des contrôleurs : réceptions, dispensation, inventaire, commandes, rapports.
- Écrans réellement affichés : captures du 01/10 pour les 4 rôles opérationnels, base de test isolée.

Les points marqués « à vérifier » n'ont pas été contrôlés écran par écran.

**Corrections du cahier appliquées (rien de l'ancien texte n'est codé)**

- Sortie impossible au-delà du stock disponible.
- Risque de péremption : `stock du lot − CMM × mois restants`, si le résultat est positif.
- Moyenne hebdomadaire : divisée par le nombre de semaines **clôturées**.
- « Retour pharmacie ONG », générique par organisation.
- Comptes individuels uniquement.
- Le volet matériel (tablettes, sacs, power banks, SAV) est hors périmètre.

**Légende**

- Statuts : ✅ CONFORME · 🟡 À AJUSTER · ❌ MANQUANTE · ⛔ EN TROP · ⚠️ CONTRADICTION.
- Version cible : V1 à V4, selon l'annexe A, corrigée en section 6.

---

## 1. Tableaux par rôle

### 1.1 Admin Sago (propriétaire)

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Valider les organisations et leurs accès (création de l'organisation, du premier Admin Coordination) | ✅ | Configuration → Organisations | Organisations | — | V1 |
| Références communes (standards plateforme, assistance aux organisations, historique) | ✅ | Standards & Référentiels | Standards | — | V1 |
| Liste de toutes les ONG | ✅ | Organisations | Organisations | — | V1 |
| Liste des ONG **et des formations sanitaires** (tableau de bord du cahier) | 🟡 | ONG ✓ ; FOSA ✗ | idem | Liste des FOSA par organisation, en consultation (sans validation : elle revient à la Coordination). | V1 |
| Accès aux Listes Standard de toutes les ONG | 🟡 | via l'assistance par organisation | idem | Consultation possible par organisation, pas de vue transversale. | V2 |
| Formations en ligne | ❌ | — | — | Module formation/évaluation. | V4 |
| Mises à jour de l'application | ❌ | — | — | Diffusion des versions (pas de mécanisme dans l'application). | V4 |
| Données opérationnelles des FOSA (stocks, dispensations) | ✅ (absentes) | — | — | Conforme : le Sago n'a pas accès aux données de santé. | — |

### 1.2 Admin Coordination

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Menu V1 : Tableau de bord, Ma Coordination, Configuration des projets, Liste Standard, Profil | ✅ | ✓ | ✓ (barre basse : Accueil, Projets, Liste, Profil) | Décision Q-3 / règles V1. | V1 |
| Pays : liste de tous les pays (avec recherche) | 🟡 | Coordination déjà affectée (pays affiché) | idem | La liste mondiale existe (ISO 3166) ; la Coordination est rattachée à une mission existante, donc le pays est affiché en lecture seule (voir C-01). | V1 |
| ONG / Ministère, codes bailleur et programmes MoH, intitulé | ✅ | ✓ | ✓ | AM-110, AM-170. | V1 |
| Bailleurs de la mission (un bailleur peut soutenir plusieurs projets) | ✅ | Bailleurs & Programmes | ✓ | Un projet = un bailleur (AM-170). | V1 |
| Niveaux de soins + « Ajouter un service » | 🟡 | ✓ | ✓ | « **Programme Laboratoire** » absent du référentiel par défaut ; « malnutrition (UNT et UNTI) » non détaillé. | V1 |
| Populations par défaut (Adultes, Femmes enceintes, Enfants < 5 ans) | ✅ | ✓ | ✓ | AM-170. « Ajouter une population » : existe déjà (référentiel), à confirmer (annexe C). | V1 |
| Pathologies **selon le couple niveau de soins × population** | 🟡 | ✓ par population | ✓ par population | Le cahier liste les pathologies par (population, niveau) ; l'application les relie à la population du projet seulement. | V1 |
| Examens de laboratoire par population | 🟡 | référentiel `laboratory_exam` | — | Pris en compte par la génération, pas de saisie dédiée dans la configuration médicale. | V1 |
| Catégories de FOSA (Hôpital régional, Hôpital de district, CMA, CSI, Centre de santé ambulatoire) | ❌ | — | — | Référentiel `facility_category` sans valeurs par défaut (DEC-11 validée). | V1 |
| Liste Standard générée (tableau, articles cochés / non cochés) | ✅ | ✓ | ✓ | AM-113. | V1 |
| Seule la Coordination modifie la Liste Standard | ✅ | ✓ | ✓ | Refus serveur testé (Admin Projet). | V1 |
| Ajouter une molécule / **import Excel** / codification propre à l'ONG (code, désignation, conditionnement) | 🟡 | catalogue masqué en V1 pour la Coordination | — | Le catalogue par organisation et l'import existent, mais la Coordination n'y a pas accès en V1 (seule la section « listes » est ouverte). | V1 |
| Décocher des articles **par FOSA** (restrictions propres à l'ONG) | ❌ | — | — | Il n'existe qu'une liste par projet, pas de Liste Standard par FOSA. | V1 |
| Transférer une liste de pathologies d'un protocole à un autre | ❌ | — | — | — | V2 |
| Article hors liste livré par un tiers, signalé à la Coordination | ❌ | — | — | Règle proposée, à valider. | V2 |
| Paramètres d'approvisionnement du projet | 🟡 | ✓ | ✓ | « Stock de sécurité » en mois entiers (1 à 12) alors que le cahier propose 0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 (voir C-05). | V1 |
| Créer les comptes Admin Projet (projet choisi dans la liste) et Coordination (lecture seule) | ✅ | Ma Coordination | Ma Coordination | AM-171. | V1 |
| **Valider les FOSA déclarées par les Admin Projet (une seule fois)** | ❌ | — | — | Pas de statut « en attente » ni d'écran de validation (section 5). La Coordination en lecture seule ne valide pas. | V1 |
| **Liste des FOSA et des comptes de sa coordination, suspension d'une FOSA ou d'un compte** | ❌ | — | — | À créer (écrans Coordination). | V1 |
| **Journal des actions de sa coordination** (création / désactivation des comptes FOSA) | 🟡 | enregistré, non consultable | — | `AuditService` enregistre ; vue filtrée par coordination à créer. | V1 |
| Analyses de base de sa coordination (CMM, ruptures, pré-rupture, péremptions, surstock, dormants) | ❌ | — | — | **Avancées en V1** (section 8). | V1 |
| Pas de création de comptes FOSA | ✅ | — | — | Masqué par réglage V1. | V1 |
| « Définir les fonctionnalités actives » (modules par projet) | 🟡 | écran Sécurité (modules par périmètre) hors menu V1 | — | Le mécanisme existe (`modules.manage`) mais n'est pas proposé à la Coordination. | V2 |
| « Accès à toutes les vues des FOSA du pays » | ⚠️ | masqué | masqué | Contradiction avec le menu V1 validé (C-03). | — |
| Écran d'entrée dynamique « Code mission / ID organisation » | ⚠️ | — | — | Contradiction avec la connexion par compte individuel (C-02). | — |

### 1.3 Coordination (lecture seule)

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Voit les mêmes informations que l'Admin Coordination de sa coordination | ✅ | ✓ | ✓ | AM-171, même périmètre (mission). | V1 |
| Aucune création, modification ni suppression (refus serveur) | ✅ | ✓ (403) | ✓ (403, boutons masqués) | Middleware `EnforceReadOnlyAccount` et permissions filtrées ; 7 tests. | V1 |
| Indication visible de l'accès en lecture seule | ✅ | badge « Lecture seule » | bandeau | — | V1 |
| Ne peut pas valider les FOSA ni suspendre (refus serveur) | ✅ (à étendre) | — | — | Le middleware refuse déjà toute écriture ; à couvrir par un test lors de la création de la validation. | V1 |
| Gestion de son propre compte (profil, mot de passe, langue) | ✅ | ✓ | ✓ | Seules écritures autorisées. | V1 |

### 1.4 Admin Projet

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Menu V1 : Tableau de bord, Projet & FOSA, Liste Standard, Profil (Q-3) | 🟡 | 6 entrées (Mon projet, Formations sanitaires, Équipe FOSA séparés) | barre basse 4 onglets à aligner | Lot b (AM-161). | V1 |
| Informations du projet héritées, en lecture seule (pays, ONG, codes, intitulé, durée) | 🟡 | « Mon projet » | ✓ | Présentes ; à afficher dans l'en-tête de chaque FOSA (maquettes). | V1 |
| **Déclarer une FOSA → statut « en attente »** | 🟡 | création directe, FOSA active | idem | La FOSA est active dès sa création ; il n'existe ni statut ni validation (section 5). | V1 |
| Détails FOSA : niveau de soins, populations, pathologies, catégorie, **issus de la configuration validée** | ❌ | — | — | Seuls `facility_type` et `care_level` (texte libre) existent (AM-162). | V1 |
| Liste Standard **de la FOSA** générée (consultation seule) | ❌ | — | — | Il n'existe qu'une liste par projet (AM-162/163). | V1 |
| Paramètres d'approvisionnement de la FOSA (préremplis depuis le projet, historisés, « Appliquer à toutes les FOSA ») + dates d'inventaire, de soumission et de réception | ❌ | — | — | DEC-08 validée, non implémentée. | V1 |
| Créer les comptes **Admin Site et Utilisateur Site** d'une FOSA **validée**, et les désactiver | 🟡 | Équipe FOSA : Admin Site seulement | idem | L'Admin Projet ne peut créer que des Admins Site (l'Admin Site crée les Utilisateurs Site) ; aucune condition de validation de la FOSA. | V1 |
| Consulter la Liste Standard sans pouvoir la modifier | ✅ | ✓ | ✓ | Refus serveur testé. | V1 |
| Onglets du catalogue sur la Liste Standard (Produits, Référentiels, Fournisseurs, Lots, Kits) | ⛔ | visibles | — | À masquer (lot c2). | — |
| Filtres Organisation et Mission sur les FOSA | ⛔ | visibles | — | À masquer (lot c2). | — |
| Permission `products.manage` (gestion du catalogue produits) | ⛔ | API catalogue/produits ouverte en V1 | — | Le cahier réserve la codification à la Coordination. | — |
| Sites de dispensation sous la FOSA | ⚠️ | gérés dans la FOSA | idem | Le cahier ne connaît pas de niveau « site » sous la FOSA (DEC-05 encore ouverte, C-04). | — |
| Tableau de bord Admin Projet (indicateurs FOSA, état de synchro, alertes) | 🟡 | générique | générique | Lots c2/c3 (maquettes 01, 05). | V1 |
| Analyses de base de son projet | ❌ | — | — | **Avancées en V1** (section 8). | V1 |
| « Gérer le tableau de bord avec accès complet aux applications des FOSA » | ⚠️ | masqué | masqué | Contradiction avec le menu V1 validé (C-03). | — |
| Fonctionnement hors ligne de l'application Admin Projet | 🟡 | — | lecture en cache, file d'attente | Conflits, opérations en échec et chiffrement manquent (lot d, AM-165). | V1 |
| Écran d'entrée « Code projet / ID organisation » | ⚠️ | — | — | C-02. | — |

### 1.5 Admin Site (FOSA)

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Accès limité à sa FOSA, compte individuel, mot de passe changé à la 1re connexion | ✅ | ✓ | ✓ | — | V1 |
| Accès seulement si la FOSA est autorisée **et** le projet actif | 🟡 | projet actif contrôlé à vérifier | idem | Aucun contrôle « FOSA validée » (section 5) ; le contrôle « projet clôturé » reste à faire (registre lot 11). | V1 |
| 1re connexion en ligne, puis travail hors ligne | ✅ | — | ✓ | — | V1 |
| Liste Standard (consultation) | ✅ | ✓ | ✓ | Liste du projet, à remplacer par la liste de la FOSA (lot c1). | V1 |
| Menu « Produits » (catalogue) | ⛔ ? | ✓ | ✓ | Absent du cahier pour la FOSA, doublon de la Liste Standard. Proposé : masquer (P-07). | — |
| Stocks, lots, péremptions, FEFO | ✅ | ✓ | ✓ | La sortie FEFO exclut les lots périmés. | V1 |
| **Dispensation** : destinations Patient / Service / Périmés-détériorés / Retour pharmacie ONG | 🟡 | patient, service, communauté, autre | idem | « Périmés/détériorés » et « Retour pharmacie ONG » manquent ; « communauté » est présent mais non prévu. | V1 |
| Photo d'ordonnance obligatoire avant l'ordonnance, jamais dans la galerie | 🟡 | facultative | obligatoire à l'étape 1, rangée dans l'espace privé | Sur mobile, l'import **depuis la galerie** est autorisé et la photo n'est **pas chiffrée** ; sur le Web la pièce jointe est facultative. | V1 (voir 6) |
| Date et heure de l'ordonnance bloquées (date du jour) | 🟡 | saisie libre (≤ maintenant) | idem | À verrouiller. | V1 |
| Entêtes par type de sortie (patient : nom, âge, sexe, n° d'ordonnance, prescripteur ; service : service, prescripteur) | 🟡 | partiel | partiel | Âge et sexe présents ; n° d'ordonnance et prescripteur sur l'ordonnance ; champs obligatoires à aligner (annexe C). | V1 |
| Couple ONG/Bailleur à la dispensation | ❌ | — | — | Dépend du couple ONG/Bailleur des entrées. | V2 |
| Statut de l'ordonnance : délivrée / en attente / partielle | ✅ | ✓ | ✓ | `allow_partial` et manques enregistrés. | V1 |
| Impossible de sortir plus que le stock ou un lot périmé | ✅ | ✓ | ✓ | — | V1 |
| Statut produit « R », pré-rupture, pré-péremption à la dispensation | 🟡 | « R » partiel | partiel | La pré-rupture dépend de la formule (P-01) et de la CMM (non calculée). | V2 |
| Validation clinique de l'ordonnance (protocole, posologie, contre-indications) | ⚠️ | présente (Coordination / Projet) | présente | Non prévue en V1 : le cahier la place dans les contrôles protocolaires (V4) (C-07). | — |
| Entrées en stock : origine ONG/Bailleur, quantités commandées / livrées, lots dupliqués | 🟡 | fournisseur, lot, quantités | idem | Origine « ONG/Bailleur » absente (fournisseur à la place) ; le rapport de réception existe partiellement (écarts commandé/reçu). | V2 |
| Inventaire : participants, couple ONG/Bailleur, lots dupliqués, analyse des écarts | 🟡 | inventaire et validation | ✓ | Participants et écarts présents ; couple ONG/Bailleur et analyse (% d'écart, positifs / négatifs) à compléter. | V2 |
| Bon de commande proposé après inventaire (« sans inventaire, pas de BC ») | 🟡 | commandes saisies manuellement | ✓ | Pas de proposition calculée depuis l'inventaire. | V2 |
| Rapports et analyses (CMM, rupture, surstock, dormants, pré-périmés) | 🟡 | compteurs opérationnels | ✓ | Aucune analyse calculée. | V2 |
| Synchronisation (état, opérations en attente) | 🟡 | ✓ | ✓ | Écran des opérations en échec et conflits : lot d. | V1 |
| Partage hors ligne (paquet chiffré et signé, fusion sans doublon) | ❌ | — | — | — | V2 |
| Pharmaco-épidémiologie, programmes VIH/TB, analyses financières, signature | ❌ | — | — | — | V3 |
| Codes-barres GS1, kits, formation, température | ❌ | — | — | Des éléments « kits » existent dans le catalogue (masqués). | V4 |

### 1.6 Utilisateur Site (FOSA)

Mêmes écrans que l'Admin Site, avec moins d'actions. Écarts propres à ce rôle :

| Fonctionnalité | Statut | Web | Mobile | Écart / remarque | Version |
|---|---|---|---|---|---|
| Entrées, dispensation, inventaire | 🟡 | dispensation ✓ ; entrées et inventaire en consultation | idem | Le cahier donne « entrées, dispensation, inventaire » aux deux rôles FOSA. L'Utilisateur Site n'a aujourd'hui ni `receipts.manage` ni `inventories.manage` (C-06). | V1 |
| Compte créé par l'Admin Projet | 🟡 | créé par l'Admin Site | idem | Nouvelle règle : l'Admin Projet crée les deux types de comptes FOSA. | V1 |
| Menu « Produits » | ⛔ ? | ✓ | ✓ | Comme pour l'Admin Site (P-07). | — |
| Toutes les autres lignes de l'Admin Site | — | — | — | Mêmes statuts que la section 1.5. | — |

---

## 2. Fonctionnalités EN TROP à masquer (sans supprimer le code ni les données)

| Rôle | Élément | Masquage proposé |
|---|---|---|
| Admin Projet | Onglets Produits, Référentiels, Fournisseurs, Lots, Kits sur la Liste Standard | Vue + route + API refusées (lot c2). |
| Admin Projet | Filtres Organisation et Mission (FOSA) | Vue (lot c2). |
| Admin Projet | Gestion du catalogue produits (`products.manage`, API `catalog/products`) | Permission retirée en V1 + motif API refusé. |
| Admin Projet | Menus séparés « Mon projet », « Formations sanitaires », « Équipe FOSA » | Regroupés en onglets de « Projet & FOSA » (lot b), routes conservées. |
| Admin Site, Utilisateur Site | Menu « Produits » (catalogue) | Proposé (P-07) : masquer ; la Liste Standard suffit. **À valider.** |
| Admin Site, Utilisateur Site | Destination de sortie « Communauté » | À masquer s'il n'est pas retenu (C-08). |
| Coordination, Admin Projet | Validation clinique des ordonnances | À masquer en V1 si C-07 est confirmé. |

---

## 3. Cloisonnement

| Rôle | Périmètre attendu | État | Preuve |
|---|---|---|---|
| Admin Sago | Plateforme, sans données de santé | ✅ | Menus et API limités (`V1ModuleAvailabilityTest`, `GovernanceMatrixTest`). |
| Admin Coordination | Son organisation et sa coordination | ✅ | `CoordinationCountryScopeTest`, `AdminCoordinationMissionOwnershipTest`. |
| Coordination (lecture seule) | Idem, sans écriture | ✅ | `ReadOnlyCoordinationAccountTest`. |
| Admin Projet | Son projet et ses FOSA | ✅ | `GovernanceMatrixTest` (pas d'extension aux projets voisins), `ProjectManagementTest`. |
| Admin Site / Utilisateur Site | Sa FOSA (site) | ✅ | `GovernanceMatrixTest`, tests mobiles `fosa_user_scope_options_test`. |

**Points à renforcer**

- Il manque un test transversal unique « un rôle × un objet d'une autre organisation / coordination / projet / FOSA → 404 » couvrant chaque module V1, Web et API. Proposé au lot e.
- Les futures listes standard par FOSA et les paramètres par FOSA devront entrer dans ce test.

---

## 4. Contradictions et points ambigus

| N° | Sujet | Cahier | Décision / existant | Proposition |
|---|---|---|---|---|
| C-01 | Pays de la mission | Liste de tous les pays pour la Coordination | Coordination rattachée à une mission existante, créée par le Sago (règle V1) | Garder : la liste mondiale sert à la création de la mission par le Sago ; la Coordination voit son pays en lecture seule. |
| C-02 | Écrans d'entrée dynamiques (« Code mission », « Code projet », « Code FOSA ») | Saisie d'un code puis chargement de la configuration | Connexion par compte individuel ; le périmètre est déduit du compte | Ne pas coder : le compte porte déjà l'organisation, le projet ou la FOSA. Le contrôle « autorisé / refusé » devient : FOSA validée + projet actif + compte actif. |
| C-03 | Accès de la Coordination et de l'Admin Projet aux applications FOSA | « accès à toutes les vues des FOSA » | Menus V1 validés sans modules opérationnels | Garder le V1 ; proposer en V2 une **supervision en lecture** des stocks et dispensations des FOSA. |
| C-04 | Niveau « site de dispensation » sous la FOSA | Le cahier ne connaît que la FOSA | Le modèle a FOSA → sites, et les comptes FOSA sont rattachés à un site (DEC-05 ouverte) | Trancher DEC-05. Proposition : un site principal créé automatiquement par FOSA, invisible tant qu'il est unique. |
| C-05 | Stock de sécurité | Liste 0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois | Configuration Mission : « en mois (1 mois = 1) » ; implémenté en mois entiers 1 à 12 (colonne entière) | Retenir la liste du cahier (projet **et** FOSA) : colonne décimale, migration sans perte des valeurs entières. **À valider.** |
| C-06 | Droits de l'Utilisateur Site | « entrées, dispensation, inventaire » | Utilisateur Site : dispensation seulement (entrées et inventaire en consultation) | Donner `receipts.manage` et `inventories.manage` à l'Utilisateur Site, la validation de l'inventaire restant à l'Admin Site. **À valider.** |
| C-07 | Validation clinique des ordonnances | Contrôle des protocoles et posologies en V4 | Écran présent pour la Coordination et le Projet | La masquer en V1 (code conservé), la réactiver en V4. |
| C-08 | Destinations de sortie | Patient, Service, Périmés/détériorés, Retour pharmacie ONG | Patient, Service, Communauté, Autre | Aligner sur les 4 du cahier ; « Communauté » à garder seulement si vous le confirmez (dispensation communautaire, prévue en V3). |
| C-09 | Photo d'ordonnance | Annexe A : V2 | Règle validée « ordonnance obligatoire » ; déjà obligatoire sur mobile | Garder l'obligation en V1, interdire la galerie, chiffrer la photo avec la base locale (lot d). |
| C-10 | Programme Laboratoire | Niveau de soins listé | Absent de la Configuration Mission | L'ajouter au référentiel par défaut (pas de contradiction réelle : complément). |
| A-01 | Alerte « > 50 % » | Placée dans la section antibiotiques, mais l'ancienne version parlait d'antipaludiques | — | Ne pas coder. Seuil antibiotiques 50 % proposé par défaut, paramétrable ; règle antipaludique distincte (doublement / historique). **À valider par un pharmacien.** |
| A-02 | Alerte artésunate | « artésunate injectable pour un paludisme simple » | — | Ne pas coder avant validation pharmaceutique (V3/V4). |
| A-03 | Champs obligatoires de l'entête « Patient » | 5 champs listés pour « 4 annoncés » | — | Proposé : nom et prénom, âge, sexe, n° d'ordonnance, prescripteur (5 obligatoires) ; téléphone et service facultatifs. |
| A-04 | Rapport de réception | % par article, taux global = moyenne | — | Proposé tel quel (articles commandés uniquement, plafonné à 100 % ? à décider). V2. |
| A-05 | Langue | « Language » en haut | FR/EN sur le Web ; mobile en français uniquement | Sélecteur mobile déjà présent (profil) ; traduction anglaise complète du mobile en V2. |
| A-06 | Nouveau patient | Case « déjà suivi avant l'application » | — | Proposé tel quel (V3, dispensation programme). |

### P-01 — Formule de pré-rupture proposée (unités : mois)

- **CMM** : consommation moyenne mensuelle, en unités par mois. Elle est calculée sur les mois clôturés ; la période retenue est le nombre de mois paramétré, 3 par défaut.
- **Mois de stock** = stock disponible ÷ CMM.
- **Délai avant la prochaine livraison (mois)** = jours restants jusqu'à la prochaine date de soumission de commande ÷ 30 + DL.
- **Pré-rupture** si : mois de stock < délai avant la prochaine livraison + stock de sécurité.
- **Quantité de commande exceptionnelle** = CMM × (délai avant la prochaine livraison + stock de sécurité) − stock disponible, si elle est positive.
- **Cas particulier** : si CMM = 0, il n'y a pas de pré-rupture. Le produit est alors signalé « dormant » si son stock est supérieur à 0.

Justification : une seule unité (le mois) ; le stock de sécurité joue son rôle de coussin. Le cahier corrigé l'exclut (« mois de stock < délai ») et l'annexe C laisse la question ouverte. **Choisissez : avec ou sans stock de sécurité.** La même formule servira à la dispensation et aux analyses.

---

## 5. Règle des comptes FOSA (validation par la Coordination) : existant et manquant

| Étape / garde-fou | Existant | Manquant |
|---|---|---|
| 1. L'Admin Projet déclare une FOSA → « en attente » | Création de FOSA par l'Admin Projet | Statut `en attente / validée / suspendue` ; une FOSA en attente ne doit ouvrir ni comptes ni accès. |
| 2. L'Admin Coordination (qui a créé le projet) valide la FOSA une seule fois ; la lecture seule ne peut pas | — | Écran Coordination « FOSA à valider » (par projet), action Valider, notification à l'Admin Projet ; refus serveur pour la lecture seule et hors de sa coordination. |
| 3. Une fois validée, l'Admin Projet crée et désactive les comptes Admin Site **et** Utilisateur Site | Création d'Admin Site par l'Admin Projet ; archivage d'un compte | Création d'Utilisateur Site par l'Admin Projet ; refus serveur si la FOSA n'est pas validée. |
| Comptes individuels, mot de passe changé à la 1re connexion | ✅ | — |
| Journal des actions (qui a créé / désactivé quel compte, quand) | ✅ enregistré (`AuditService`) | Vue filtrée « comptes FOSA » consultable par l'Admin Coordination pour sa coordination. |
| Liste des FOSA et des comptes de sa coordination, visible par l'Admin Coordination, avec suspension | — | Écran Coordination + action Suspendre (FOSA ou compte), effet immédiat (jetons révoqués). |
| 1re connexion FOSA en ligne, puis hors ligne | ✅ | — |
| La Coordination ne crée pas de comptes FOSA | ✅ | — |

**Migration** : les FOSA existantes passent au statut « validée » pour ne rien casser.

---

## 6. Découpage en versions : corrections proposées

- **Photo d'ordonnance chiffrée : V2 → V1.** Elle existe déjà et elle est obligatoire sur mobile (règle validée). Il reste en V1 le chiffrement et l'interdiction de la galerie.
- **Entrées en stock, inventaire, commandes et rapports opérationnels** (prévus en V2) existent déjà et fonctionnent. Ils restent visibles pour les rôles FOSA (règle « ne pas retirer ce qui marche »), sans évolution avant la V2. Les compléments (couple ONG/Bailleur, analyse des écarts, proposition de bon de commande) restent en V2.
- **Liste Standard par FOSA, catégories de FOSA, paramètres par FOSA, validation des FOSA par la Coordination** : V1. C'est la « configuration à 3 niveaux » de l'annexe A.
- **Formation en ligne et mises à jour** pour le Sago : V4. C'est le module formation de l'annexe A.

---

## 7. Plan de lots V1, ordonné (révisé le 01/10)

| Ordre | Lot | ID | Contenu | Dépend de |
|---|---|---|---|---|
| 1 | b | AM-161 | Structure Admin Projet : menu 4 entrées, barre supérieure, barre basse mobile ; **masquage des éléments EN TROP** (section 2) côté menu, route et API. | — |
| 2 | c1 | AM-162 | Modèle FOSA : statut en attente / validée / suspendue (FOSA existantes → validées) ; catégories (5 par défaut) ; niveau, populations et pathologies issus de la configuration ; paramètres d'approvisionnement de la FOSA (DEC-08, 3 dates) ; Liste Standard par FOSA ; comptes Admin Site **et** Utilisateur Site par l'Admin Projet, refusés tant que la FOSA n'est pas validée. | b |
| 3 | c1-bis | AM-172 | **« Ma Coordination » (maquettes 01–06 du 01/10)** — 4 onglets : *Projets et programmes* (indicateurs, bandeau des FOSA en attente, liste des projets et programmes nationaux, formulaire unique « Créer un projet / programme » avec champ Type) ; *FOSA à valider* (liste + détail complet, « Valider la FOSA », « Refuser avec un motif » obligatoire et visible par l'Admin Projet) ; *FOSA et comptes* (filtres projet et statut, comptes dépliables, suspension / réactivation) ; *Comptes de la coordination* (Admin Projet et Coordination lecture seule + journal). Mobile : mêmes onglets en cartes, boutons ≥ 44 px ; validation, refus et suspension en ligne seulement, listes consultables hors ligne. Lecture seule : aucun bouton, refus serveur. Journal pour chaque validation, refus, suspension, réactivation. FOSA suspendue : plus de connexion, données conservées, opérations hors ligne déjà faites synchronisées et signalées. | c1 |
| 4 | c2 / c3 | AM-163 / 164 | Écrans Web 01–04 et mobiles 05–08 de l'Admin Projet, tablette. | c1 |
| 5 | f | AM-173 | Listes Standard (Coordination) : « Programme Laboratoire », pathologies par niveau × population, ajout de molécule et import Excel, décochage par FOSA, **lien code-barres ↔ produit**. | c1 |
| 6 | g | AM-174 | Dispensation FOSA : 4 destinations, date verrouillée, champs obligatoires de l'entête, droits de l'Utilisateur Site (C-06), masquage de la validation clinique (C-07). | c1 |
| 7 | g-bis | AM-175 | **Capture par le téléphone** : ordonnance (photo ou scan de document, appareil photo uniquement, compressée, chiffrée, jamais dans la galerie) ; scan de code-barres à l'entrée en stock, à la dispensation et à l'inventaire. **Après validation des librairies (`08`).** | g, f |
| 8 | h | AM-176 | **Analyses de base** : CMM, rupture et jours de rupture, pré-rupture, risque de péremption par lot, surstock, dormants, pré-périmés ; calcul serveur, filtres période et ONG/Bailleur, derniers résultats synchronisés sur mobile hors ligne. | g (C-11) |
| 9 | d | AM-165 | Hors ligne : conflits, opérations en échec, indicateur, chiffrement de la base locale **et des photos** (DEC-10), refus d'accès si la FOSA est suspendue ou le projet clôturé. | h |
| 10 | e | AM-166 | Tests : matrice de cloisonnement transversale, jeux de données d'analyses calculés à la main, tests Flutter des écrans, scénarios manuels. | d |

Hors V1 (aucun développement sans décision) : partage hors ligne, rapport de réception, analyse des écarts d'inventaire, proposition de bon de commande (V2) ; programmes VIH/TB, pharmaco-épidémiologie, finances, signature (V3) ; protocoles, décodage GS1 complet, kits, formation, température (V4).

### 7.1 Formules validées pour les analyses (AM-176)

- **CMM** = consommation des X derniers mois clôturés ÷ X. X est paramétrable, 3 par défaut. Consommation = sorties hors périmés et hors retours (C-12).
- **Mois de stock** = stock disponible ÷ CMM.
- **Délai avant la prochaine livraison (mois)** = (date de la prochaine commande − date du jour, en jours) ÷ 30 + DL.
- **Pré-rupture** (alerte, **sans** stock de sécurité) : mois de stock < délai avant la prochaine livraison.
- **Quantité de commande exceptionnelle** (**avec** stock de sécurité) = CMM × (délai avant la prochaine livraison + stock de sécurité) − stock disponible, si elle est positive.
- **Rupture** : CMM > 0 et stock = 0.
- **Jours de rupture** : somme des jours entre chaque passage à 0 et le réapprovisionnement suivant (ou la fin de période).
- **Risque de péremption par lot**, en ordre FEFO : quantité à risque = stock du lot − CMM × mois restants avant péremption, si elle est positive. La consommation est d'abord imputée aux lots qui périment avant.
- **Surstock** : mois de stock > DL + périodicité de commande + stock de sécurité.
- **Dormants** (sur X mois) : CMM = 0 et stock > 0. % dormants = dormants ÷ articles en stock × 100.
- **Pré-périmés** : date de péremption < aujourd'hui + X mois.
- **Cas CMM = 0** : ni pré-rupture ni surstock ; l'article apparaît en « dormant » s'il y a du stock.

## 8. Décisions attendues

Nouveaux points liés aux ajouts V1 :

- **C-11 — Couple ONG/Bailleur.** Les analyses et le scan doivent être « filtrables par couple ONG/Bailleur », mais le stock n'est pas encore rattaché à un couple : les entrées enregistrent un fournisseur, pas une origine ONG/Bailleur (prévue en V2). Proposition : avancer en V1 le seul champ « Origine (ONG/Bailleur) » des entrées en stock, avec les lots rattachés à ce couple. Le reste des entrées (rapport de réception) reste en V2. Sans cela, les filtres de la V1 se limitent à la période et au projet.
- **C-12 — Consommation pour la CMM.** Proposition : sorties « Patient » et « Service » uniquement ; les sorties « Périmés/détériorés » et « Retour pharmacie ONG » sont exclues.
- **L-01 — Librairies de capture.** Voir `08-evaluation-librairies-capture.md` ; validation obligatoire avant installation.

Points déjà ouverts :

1. C-04 / DEC-05 : site principal automatique par FOSA ?
2. C-05 : stock de sécurité en liste décimale (0,25 → 2) pour le projet et les FOSA ?
3. C-06 : l'Utilisateur Site saisit les entrées et l'inventaire ?
4. C-07 : masquer la validation clinique en V1 ?
5. C-08 : garder la destination « Communauté » ?
6. P-01 : pré-rupture avec ou sans stock de sécurité ?
7. P-07 : masquer le menu « Produits » pour les FOSA ?
8. DEC-10 : chiffrement de la base locale, option 1 (migration sur place) à confirmer après étude.

## 8 bis. Points soulevés par les maquettes « Ma Coordination » (01/10)

| N° | Sujet | Proposition |
|---|---|---|
| M-01 | Champ **Type** du projet (Projet bailleur / Programme national) ; un programme national a pour « bailleur » le Ministère de la Santé | Nouveau champ `type` sur le projet (même formulaire). Pour un programme national, le bailleur n'est pas obligatoire et s'affiche « Ministère de la Santé » ; la règle « un projet = un bailleur » reste valable pour les projets bailleurs. |
| M-02 | Statut **« Refusée »** d'une FOSA | Statuts : en attente → validée, ou refusée (motif obligatoire, visible par l'Admin Projet) ; l'Admin Projet corrige la FOSA refusée, qui repasse « en attente » (nouvelle validation). |
| M-03 | Entrée **« Analyses »** dans le menu (Web) et la barre basse (mobile) de la Coordination | Ajoutée avec le lot des analyses (étape 8), pas avant : aucun module à moitié fonctionnel. D'ici là, la barre basse mobile Coordination reste Accueil, Coordination, Liste, Profil. |
| M-04 | Opérations hors ligne d'une FOSA **suspendue** (« synchronisées quand même et signalées ») | La suspension bloque toute nouvelle connexion et toute nouvelle opération ; le jeton de l'appareil garde **uniquement** le droit d'envoyer les opérations créées **avant** la date de suspension, marquées « reçues après suspension » dans le journal. Les opérations datées après la suspension sont refusées. |
| M-05 | Statut de compte **« À activer »** | Compte jamais connecté (premier mot de passe non changé) ; calculé, pas de nouveau statut stocké. |

## 9. Décisions du porteur (01/10/2026)

| Sujet | Décision |
|---|---|
| Plan V1 (section 7) | Validé. |
| Librairie codes-barres | `flutter_zxing` validée (licence MIT, traitement sur l'appareil, aucun envoi, hors ligne, Android et iOS). `mobile_scanner` retiré **uniquement** après des essais réussis sur un vrai téléphone Android et sur iOS. |
| Librairie ordonnance | Option B (`opencv_dart` + `camera`) : précisions envoyées, validation en attente. |
| C-11 Origine ONG/Bailleur | Validée en V1, **obligatoire** pour toute nouvelle entrée en stock. Stock existant : couple déduit du projet ; liste des cas non déductibles à fournir **avant** toute modification. |
| C-12 Consommation (CMM) | Sorties « Patient » et « Service » uniquement. Jours de rupture affichés à côté de chaque CMM. |
| DEC-05 Sites | Un site principal créé automatiquement par FOSA, invisible ; le modèle « sites » est conservé. |
| C-05 Stock de sécurité | Liste 0,25 / 0,5 / 0,75 / 1 / 1,5 / 2 mois (projets et FOSA) ; périodicité 1 à 12 mois. Liste des valeurs actuelles hors liste à fournir avant le changement (lecture seule). |
| C-06 Utilisateur Site | Saisit les entrées en stock et les inventaires ; l'analyse de l'inventaire reste réservée à l'ONG. |
| C-07 Validation clinique | Masquée en V1 (code conservé), prévue en V4. |
| C-08 « Communauté » | Masquée en V1 (code conservé) ; l'historique reste visible. |
| P-01 Pré-rupture | Formule de la section 7.1 validée. |
| P-07 Menu « Produits » (FOSA) | Masqué ; la consultation du stock par produit (quantités, lots, péremptions) reste accessible. |
| DEC-10 Chiffrement local | Étude de faisabilité de la migration sur place, puis rapport avant tout code ; terminé avant tout déploiement sur le terrain. |
| c1 | FOSA existantes → « validées » ; seules les nouvelles passent par « en attente ». |

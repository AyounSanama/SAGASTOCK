# Feuille de route V1 — PharmaCare

Adoptée le 02/10/2026. **Remplace le plan précédent** (`ameliorations/07-audit-cahier-des-charges.md`, section 7). Le registre (`ameliorations/02-registre-ameliorations-realisees.md`) est mis à jour à la fin de chaque niveau ; chaque niveau se termine par un rapport et attend la validation du porteur avant le suivant.

| Niveau | Contenu | État |
|---|---|---|
| 0 | Règles permanentes | En vigueur |
| 1 | Finaliser l'en-cours | **En cours** |
| 2 | Structure et conformité | **Livré le 06/10, à valider** (menu Référentiels, assistant en 4 étapes ; restent le check-up des maquettes et l’audit des doublons) |
| 3 | Écrans Coordination (c1-bis) | **Livré le 06/10, à valider** (Policies, « Modifier » les comptes, filtres et badge de synchronisation ; reste la vérification sur le téléphone) |
| 4 | Mode clair / sombre (3 bis) | À faire |
| 5 | Écrans Admin Projet (c2 / c3) | **Livré le 06/10, à valider** (Web et mobile ; mobile à compiler sur Codemagic) |
| 6 | Listes Standard (f) | À faire |
| 7 | Stock et dispensation FOSA (g) | À faire |
| 8 | Capture par téléphone | À faire |
| 9 | Analyses de base (h) | À faire |
| 10 | Hors ligne et chiffrement (d) | À faire |
| 11 | Tests finaux et recette V1 (e) | À faire |
| 12 | Préparation du déploiement | À faire |

## Niveau 0 — Règles permanentes (valables pour tous les niveaux)

1. Sources, par ordre de priorité : décisions validées (registre) → document « Configuration Mission » → cahier des charges corrigé → maquettes (`docs/maquettes/`).
2. Aucun doublon : chaque fonction existe à un seul endroit. Exceptions : raccourcis du tableau de bord et du bloc « À traiter ».
3. Écran ou fonction hors V1 ou remplacé : masqué (menu, route, API refusée), jamais supprimé.
4. Web + Android + iOS pour chaque fonction ; mobile utilisable hors ligne avec synchronisation automatique, sans perte ni doublon.
5. Charte : orange #F57C00 (marque), #B85D00 (boutons et texte orange sur fond clair), menu #1E2329, Inter, 5 couleurs d'état (succès vert, information bleu, erreur rouge, neutre gris, péremption violet), jamais d'orange pour un état, contraste minimum 4,5:1. À partir du niveau 4 : modes clair ET sombre.
6. Sécurité : toutes les règles côté serveur, cloisonnement Organisation / Coordination / Projet / FOSA, aucune donnée patient dans les journaux, checklist sécurité dans chaque recette.
7. Données réelles : jamais modifiées sans confirmation de sauvegarde. Les tests se font sur la base de test isolée.
8. Téléphone : adresse du serveur de `app_config`, installation avec `adb install -r` uniquement, jamais de désinstallation.
9. Compilation lourde : prévenir au début et à la fin.
10. Point ambigu ou contradictoire : marquer « DÉCISION NÉCESSAIRE », proposer une solution, continuer avec le reste.
11. Critères de réception d'un niveau : tests backend et Flutter verts, `flutter analyze` propre, contrôle des couleurs, vérification sécurité, captures Web et téléphone À CÔTÉ des maquettes avec tableau de conformité, liste explicite de ce qui est reporté. Puis rapport et attente de validation avant le niveau suivant.

## Niveau 1 — Finaliser l'en-cours

- R1 : stock de sécurité de PSM-2026 à 2 mois, après confirmation de sauvegarde.
- R8 : liste des anciens programmes sur la base réelle (lecture seule).
- Chiffrement : réponses attendues (clé dans Android Keystore / iOS Keychain, licence, opérations en attente conservées en cas d'oubli du PIN).
- Compilation OpenCV + mesure de taille de l'APK (avant/après OpenCV et chiffrement), proposition de découpage par architecture.
- Téléphone : nouvelle compilation (PIN et corrections de sécurité), installation, puis les 6 scénarios hors ligne (a à f) avec captures.

Réception : rapport des points ci-dessus.

## Niveau 2 — Structure et conformité

- Check-up des 16 maquettes (étape 1) : tableau de conformité par écran, pourcentage conforme, captures côte à côte.
- Audit des doublons pour les 6 rôles, Web et mobile, avec l'endroit unique proposé pour chaque cas (liste à valider).
- Nouveau menu de l'Admin Coordination : Tableau de bord, Ma Coordination, Référentiels (Bailleurs, Référentiel médical), Liste Standard, Profil (Analyses au niveau 9).
- Fusion projet / programme : un seul bouton et un seul formulaire en étapes « Créer un projet / programme » ; masquer l'onglet Programmes, le bouton « Bailleurs & Programmes » et « Configuration des projets ».

Réception : plus aucun doublon validé, menus conformes.

## Niveau 3 — Écrans Coordination (c1-bis)

- « Ma Coordination » : Projets et programmes, FOSA à valider (valider / refuser avec motif), FOSA et comptes (suspendre / réactiver), Comptes de la coordination, Journal des actions. Maquettes Coordination_01 à 06.
- Tableau de bord de la Coordination (maquettes 07 et 08) : chiffres réels pour les FOSA et la synchronisation ; « Disponible avec les analyses de base » pour le reste.
- Statut « Refusée » et retour en attente après correction.
- Suspension d'une FOSA : révocation des accès ; les opérations créées hors ligne avant que le téléphone reçoive l'information sont acceptées et signalées ; ensuite blocage.
- Coordination (lecture seule) : tout visible, aucune action.
- Policies : utilisateurs, FOSA, projets.

Réception : 100 % de conformité aux maquettes Coordination (ou écarts justifiés).

## Niveau 4 — Mode clair / sombre (3 bis)

- D'abord : page de comparaison des couleurs du mode sombre avec contrastes mesurés, à valider.
- Ensuite : bouton Clair / Sombre / Système (barre du haut sur le Web, Profil sur mobile), préférence enregistrée dans le profil, création du mode sombre sur mobile.
- Contrôle automatique des couleurs dans les deux modes.

Réception : tous les écrans existants vérifiés dans les deux modes.

## Niveau 5 — Écrans Admin Projet (c2 / c3)

- Maquettes AdminProjet_01 à 08, Web et mobile, mise en page tablette sur 2 colonnes.
- Déclaration de FOSA, comptes Admin Site et Utilisateur Site (après validation de la FOSA).
- Corrections déjà relevées : onglet FOSA sans « Mission / pays », « Missions couvertes » ni « Sites rattachés » ; boutons secondaires en contour ; périmètre = nom de la FOSA ; impossible d'archiver son propre compte.
- Policies : sites.

Réception : 100 % de conformité aux maquettes Admin Projet, dans les deux modes.

## Niveau 6 — Listes Standard (f)

- Niveau de soins « Programme Laboratoire ».
- Pathologies proposées selon le couple niveau de soins × population.
- Ajout de produit et import Excel par la Coordination (permission de gestion du catalogue pour l'Admin Coordination).
- Décochage d'articles par FOSA.
- Article hors liste livré par un tiers : règle du cahier des charges corrigé.
- Lien code-barres ↔ produit géré par la Coordination.
- Policies : catalogue et listes.

## Niveau 7 — Stock et dispensation FOSA (g)

- Champ « Origine » (couple ONG/Bailleur) obligatoire sur les entrées ; couple du stock existant déduit du projet, cas particuliers soumis à validation.
- Utilisateur Site : saisie des entrées en stock et des inventaires.
- Destinations : Patient, Service, Périmés/détériorés, Retour pharmacie ONG (« Communauté » masquée).
- Date de l'ordonnance bloquée sur la date du jour.
- Statut « validation non requise (V1) » pour les ordonnances.
- Restriction : jamais plus que le stock disponible, jamais de lot périmé, règle FEFO.
- DÉCISION NÉCESSAIRE : champs obligatoires de l'entête Patient (4 ou 5).
- Policies : patients et ordonnances.

## Niveau 8 — Capture par téléphone

- Ordonnance : photo ou scan de document (opencv_dart + camera), appareil photo uniquement, compression, stockage dans la base chiffrée, suppression après confirmation du serveur.
- Compression validée le 02/10 : 1 600 px de large, qualité 70 %. Mesure de la taille réelle sur le téléphone avec des documents imprimés de test uniquement, jamais une vraie ordonnance. Bouton « Galerie » masqué.
- Codes-barres : flutter_zxing, scan simple à l'entrée, à la dispensation et à l'inventaire ; code inconnu = message clair et recherche manuelle.
- Retrait de mobile_scanner seulement après essais réussis sur Android et iOS.
- Taille de l'APK et découpage par architecture.

## Niveau 9 — Analyses de base (h)

- CMM (sorties Patient et Service uniquement, avec jours de rupture affichés), rupture et jours de rupture, pré-rupture (alerte sans stock de sécurité, commande exceptionnelle avec), risque de péremption par lot, surstock, dormants, pré-périmés.
- Filtres : couple ONG/Bailleur, projet, période.
- Chiffres réels dans les tableaux de bord ; ajout du menu « Analyses » (Web et barre mobile).
- Mobile hors ligne : derniers résultats synchronisés avec leur date.
- Tests avec jeux de données calculés à la main.
- Policies : analyses.

## Niveau 10 — Hors ligne et chiffrement (d)

- Chiffrement de la base locale avec **SQLite3 Multiple Ciphers** (validé le 02/10 : licence MIT, sans OpenSSL), configuré au **format compatible SQLCipher v4** (`PRAGMA cipher = 'sqlcipher'` et `PRAGMA legacy = 4` avant `PRAGMA key`), pour pouvoir passer plus tard à SQLCipher sans migration lourde. Clé aléatoire de 256 bits dans le coffre du téléphone (Android Keystore / iOS Keychain), indépendante du PIN, jamais effacée à la fermeture de session. Migration sans perte des téléphones existants (copie, chiffrement, contrôle, retour arrière).
- Détection des conflits (pas d'écrasement silencieux), écran des opérations en échec (corriger / abandonner).
- Bandeau hors ligne sur le Web.
- Policies : appareils.

Réception : aucun téléphone avec de vraies données patients avant la fin de ce niveau.

## Niveau 11 — Tests finaux et recette V1 (e)

- Test de cloisonnement commun aux 6 rôles.
- Les 6 scénarios hors ligne sur le vrai téléphone, plus coupure pendant la synchronisation et conflit de modification.
- Nouvel audit de sécurité complet.
- Performance : temps de chargement des écrans principaux et de la synchronisation avec un volume de données réaliste.
- Vérification complète dans les deux modes et dans les deux langues.

Réception : rapport de recette V1 complet.

## Niveau 12 — Préparation du déploiement

- iOS : compilation et tests via Codemagic (dès que le compte Apple est actif).
- Serveur de production : HTTPS, sauvegardes automatiques testées (restauration comprise), surveillance des erreurs.
- Documentation technique, manuel d'utilisation par rôle, support de formation.
- Plan du test pilote dans 1 ou 2 FOSA : critères de réussite, recueil des retours, corrections avant le déploiement général.

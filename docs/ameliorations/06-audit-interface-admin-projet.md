# Audit — Interface Admin Projet (Web + mobile) · 30/09/2026

Source : prompt « Nouvelle interface Admin Projet » et 8 maquettes (01–04 Web, 05–08 mobile).
Statut : **audit uniquement, aucun code modifié**. En attente de validation.

## 1. Couleur principale relevée

| Élément | Web (`resources/css`) | Flutter (`core/theme/app_tokens.dart`) |
|---|---|---|
| Orange principal | `#F57C00` (`--pc-color-primary`, `--color-pharmacare`) | `#F57C00` (`AppColors.primary`) |
| Teinte foncée | `#A65000` (`--pc-color-primary-dark`) | `#C86500` (`AppColors.primaryDark`) — **incohérent avec le Web** |
| Teinte claire | `#FFF3E8` (codée en dur à plusieurs endroits) | `#FFF3E8` (`AppColors.primarySoft`) |
| Autre orange parasite | `#FF7A00` (`dashboard.css`), `#FF9C2A` (dégradé `shell-compat.css`) | `#B85C00` (badge « warning ») |

**Contraste texte blanc** (WCAG) :
- sur `#F57C00` : **2,7:1 → insuffisant** (seuil 4,5:1) ;
- sur `#A65000` : **5,6:1 → conforme** ;
- sur `#C86500` : 3,9:1 → insuffisant.

**Proposition** : garder `#F57C00` comme couleur de marque (menu actif, liens, focus, bordure, FAB avec icône) et utiliser `#A65000` pour **tous les boutons à texte blanc**. Web et Flutter partagent les mêmes 3 teintes : `#F57C00` / `#A65000` / `#FFF3E8`.

## 2. Écarts par rapport au prompt

Légende : ✅ existe · 🟡 partiel / différent · ❌ absent.

### Thème et structure commune

| Point | État | Constat |
|---|---|---|
| Tokens centralisés | 🟡 | Tokens présents (`design-system.css`, `app_tokens.dart`), mais de nombreuses couleurs sont codées en dur (`dashboard.css`, `shell-compat.css`, `app_badge.dart`, `app_theme.dart`). |
| Neutres (`#F5F5F3`, `#E3E3E0`, `#1C1F23`, `#5F6368`) | ❌ | Palette actuelle bleutée (`#F8FAFC`, `#E4E7EC`, `#202939`, `#667085`). |
| Couleurs d'état | 🟡 | Couleurs vives (`#22A447`, `#E53935`, `#2563EB`) ; **l'état « warning » utilise de l'orange**, ce qui est interdit par le prompt. |
| Police Inter, ~12 px | ✅ | Déjà en place (AM-102). |
| Menu latéral Web 232 px, fond `#1E2329` | 🟡 | Menu clair de 248 px. |
| Menu Admin Projet à 4 entrées | 🟡 | 6 entrées aujourd'hui : Tableau de bord, Mon projet, FOSA, Équipe FOSA, Liste standard, Profil (Q-3 validée, non encore réalisée). |
| Encart Coordination en bas du menu | ❌ | |
| Barre supérieure : fil d'Ariane ONG / Coordination / Projet | 🟡 | La barre existe (langue FR/EN, avatar, notifications) mais sans ce fil d'Ariane. |
| Indicateur de synchronisation (Web) | ❌ | |
| Mobile : barre de navigation basse à 4 onglets | 🟡 | `main_navigation_shell.dart` construit les destinations depuis le manifeste : 6 entrées, donc un menu « Plus ». |

### Écrans

| Écran | État | Ce qui manque |
|---|---|---|
| 01 / 05 Tableau de bord | 🟡 | Tableau de bord générique. Manquent : les 4 indicateurs demandés, l'état de synchronisation par FOSA, l'alerte « > 3 jours », et les cartes Projet / Approvisionnement. Pas d'endpoint dédié. |
| 02 / 06 Projet & FOSA | 🟡 | FOSA, Équipe et « Mon projet » sont 3 écrans séparés. Pas d'onglets, pas de filtres catégorie / statut, pas de colonnes niveau / population / comptes. Pagination à vérifier. |
| 03 / 07 Configuration FOSA | ❌ (modèle) | Voir « Modèle de données FOSA » ci-dessous. |
| 04 / 08 Liste Standard (consultation) | 🟡 | La consultation existe (AM-113), mais sans sélecteur de FOSA, sans pastilles pathologie ni colonne « Retenu ». Pas d'export Excel pour ce rôle (`phpspreadsheet` est déjà installé). Le backend limite bien l'écriture à la Coordination (AM-101 / AM-113). |

**Modèle de données FOSA.** Aujourd'hui, `health_facilities` ne contient que `facility_type` et `care_level` (texte libre). Il manque :
- la catégorie (référentiel `facility_category`) ;
- le niveau de soins relié à la hiérarchie AM-111 ;
- les populations cibles et les pathologies de la FOSA ;
- les paramètres d'approvisionnement de la FOSA et les 3 dates ;
- un Form Request commun Web / API ;
- un endpoint « nombre de produits de la Liste Standard pour cette FOSA ».

### Hors connexion (mobile, rôle Admin Projet)

| Exigence | État | Constat |
|---|---|---|
| Base locale et lecture hors ligne | ✅ | Drift + `LocalFirstRepository` : FOSA, projet et configuration médicale en cache. Le tableau de bord n'est pas daté. |
| File d'attente locale + UUID + idempotence | ✅ | `OfflineOperationService` + `Idempotency-Key` ; le backend est idempotent (`EnsureIdempotentApiRequest`). Les créations et modifications de FOSA passent déjà par `mutate`. |
| Synchronisation automatique au retour du réseau | ✅ | `SyncService`. |
| Opérations en échec : raison, « corriger » / « abandonner » | 🟡 | Statut `failed` enregistré, mais **aucun écran** pour le consulter ou agir. |
| Conflits de modification | ❌ | Aucun verrouillage optimiste côté backend : le dernier qui écrit gagne, en silence. Le 409 actuel ne signale qu'une réutilisation de clé d'idempotence. |
| Indicateur de synchronisation toujours visible | 🟡 | Des compteurs existent, mais pas de pastille « Synchronisé · il y a X min » cliquable. |
| Stockage local chiffré | ❌ | La base Drift n'est pas chiffrée (pas de SQLCipher). |
| Jetons dans le stockage sécurisé | ✅ | `flutter_secure_storage`. |
| « Dernière synchro » par FOSA côté serveur | ❌ | `devices` n'a que `last_seen_at` par utilisateur. Le serveur ne connaît ni les opérations en attente sur un appareil, ni la date de dernière synchro par FOSA. |
| Web hors ligne (bandeau + formulaires conservés) | ❌ | |

## 3. Plan proposé

| Lot | ID | Contenu |
|---|---|---|
| a | AM-160 | **Tokens** Web + Flutter : neutres, 4 couleurs d'état, 3 teintes d'orange, menu sombre. Suppression des couleurs codées en dur dans les composants partagés ; le badge « warning » n'est plus orange. |
| b | AM-161 | **Structure** : menu Admin Projet à 4 entrées (Q-3). Les anciennes routes FOSA / Équipe restent accessibles et deviennent des onglets : **masquées, pas supprimées**. Encart Coordination, fil d'Ariane, indicateur de synchro, barre basse mobile à 4 onglets. |
| c1 | AM-162 | **Backend FOSA** : catégorie, niveau (FK hiérarchie), populations et pathologies (limitées à la configuration du projet), paramètres d'approvisionnement et dates. `SaveHealthFacilityRequest` commun, endpoint d'aperçu de la liste, endpoint tableau de bord Admin Projet, export Excel. Migrations non destructives. |
| c2 | AM-163 | **Écrans Web** 01–04. |
| c3 | AM-164 | **Écrans mobiles** 05–08, avec mise en page tablette en 2 colonnes. |
| d | AM-165 | **Hors ligne** : écran des opérations en échec (corriger / abandonner), détection de conflit (version de la FOSA envoyée → 409 + choix de l'utilisateur), pastille de synchro, compte rendu de synchro par appareil (FOSA, opérations en attente), chiffrement SQLCipher de la base locale, bandeau hors ligne Web + sauvegarde du brouillon de formulaire. |
| e | AM-166 | **Tests** : Feature Laravel (permissions, refus d'écriture sur la Liste Standard, cloisonnement, idempotence, conflit), widgets Flutter, scénarios manuels. |

## 4. Décisions à prendre

- **DEC-08 — Paramètres d'approvisionnement de la FOSA.** Proposition : préremplis avec les valeurs du projet et modifiables par FOSA. Le stock tampon est exprimé en mois (0,25 à 2).
- **DEC-09 — Statut de synchronisation d'une FOSA.** Proposition : c'est la synchro la plus récente des appareils des comptes de cette FOSA, et chaque appareil envoie son nombre d'opérations en attente à chaque synchro. Seuil d'alerte : 3 jours, réglable dans `config/pharmacare_v1.php`.
- **DEC-10 — Chiffrement SQLCipher.** Il impose une **réinitialisation unique de la base locale** sur chaque appareil. Seules les opérations déjà synchronisées sont conservées : il faut synchroniser avant la mise à jour.
- **DEC-11 — Création de FOSA par l'Admin Projet.** Oui, d'après les maquettes. Catégories = référentiel `facility_category`, avec les 5 valeurs du prompt ajoutées au référentiel global.

### Décisions du porteur (30/09/2026)

- **DEC-08 — Validée.** Les paramètres du projet préremplissent la FOSA **à sa création**, puis restent modifiables par FOSA (avec historique). Une modification ultérieure du projet **n'écrase jamais** les FOSA : action explicite « Appliquer à toutes les FOSA » avec confirmation.
- **DEC-09 — Validée.**
  - À jour : dernière synchro réussie dans le délai **et** 0 opération en attente.
  - En attente : au moins 1 opération en attente.
  - Échec : dernière tentative en échec, **ou** aucune synchro réussie depuis plus que le seuil (3 jours, réglable).
- **DEC-10 — Refusée telle quelle.** Aucune perte de données n'est acceptable.
  - Option 1, préférée : migration sur place vers une base chiffrée.
  - Option 2, sinon : blocage tant que la file n'est pas vide, synchronisation forcée, réinitialisation seulement après confirmation du serveur.
  - Étude de faisabilité à présenter avant tout code.
- **DEC-11 — Validée.** L'Admin Projet crée des FOSA dans son projet et choisit la catégorie dans la liste commune (5 catégories), sans pouvoir la modifier.
- **Plan** : lots a → b → c1 → c2/c3 → d → e validés, avec une validation du porteur à la fin de chaque lot.
- **Lot a** :
  - `#F57C00` reste la couleur de marque ;
  - supprimer `#FF7A00` et `#FF9C2A`, et retirer l'orange du badge « avertissement » ;
  - utiliser la même teinte foncée sur le Web et le mobile ;
  - montrer une capture de cette teinte avant de la généraliser.

## 5. Risques

- Lot b : les utilisateurs habitués aux entrées « FOSA » et « Équipe FOSA » les retrouveront sous forme d'onglets.
- Lot d : SQLCipher (DEC-10) et le verrouillage optimiste touchent tout le moteur de synchronisation. C'est le lot le plus risqué ; il faut le livrer séparément et le tester sur un vrai appareil.
- La validation iOS demande un Mac, qui n'est pas disponible dans cet environnement.

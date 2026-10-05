# Étude de faisabilité — chiffrement des données mobiles (S-02 / DEC-10)

02/10/2026 — étude sans code de production. Mise en œuvre au lot d, après validation. Règle en vigueur : **aucun téléphone avec de vraies données patients tant que la base locale n'est pas chiffrée**.

## 1. Situation actuelle

| Donnée sur le téléphone | Emplacement | Protection actuelle |
|---|---|---|
| Base locale Drift (patients, ordonnances, stock, file des opérations en attente) | `getApplicationSupportDirectory()/pharmacare.sqlite` | **Aucune** : fichier SQLite en clair |
| Photos d'ordonnances (pièces jointes) | `getApplicationDocumentsDirectory()/private/prescriptions/*.jpg` | **Aucune** : fichiers en clair, hors galerie |
| Jeton, profil, PIN (empreinte) | Stockage sécurisé (Keystore Android / Keychain iOS) | Chiffré par le système |
| Sauvegarde automatique Android | — | Était **active** (valeur par défaut) : base et photos copiables dans la sauvegarde du téléphone. **Désactivée le 02/10** (`allowBackup="false"`). |

Un téléphone perdu, ou accessible en mode débogage, expose donc les données de santé.

## 2. Options étudiées

| Option | Principe | Avantages | Limites | Avis |
|---|---|---|---|---|
| **A. SQLite3 Multiple Ciphers** (`sqlite3mc`) | Le paquet `sqlite3` **déjà utilisé** (3.5.2) fournit une version chiffrée de SQLite, sélectionnée par une option de configuration (`hooks: user_defines: sqlite3: source: sqlite3mc`). Drift ouvre la base avec `PRAGMA key`. | **Aucune nouvelle librairie.** Licence MIT. Pas d'OpenSSL. Chiffrement de toute la base (index et journal compris). `PRAGMA rekey` permet de **chiffrer une base existante sur place** (migration). Compatible avec les chiffrements SQLCipher. | Binaire natif un peu plus gros (à mesurer). Binaires téléchargés au moment de la compilation (réseau requis, Codemagic compris). | **Recommandée** |
| B. SQLCipher (`source: sqlcipher`) | Même mécanisme, avec la version SQLCipher Community. | Référence connue. | Dépend d'**OpenSSL** sur Android (taille, mises à jour de sécurité) ; version de SQLite parfois plus ancienne ; licence BSD avec mention. | Possible, moins favorable |
| C. Chiffrement champ par champ | Chiffrer certaines colonnes dans l'application. | Pas de binaire natif. | Recherche et tri impossibles sur les champs chiffrés ; oubli facile d'une colonne ; file d'attente et index restent en clair. | Écartée |
| D. Chiffrement du téléphone seul | Compter sur le chiffrement intégré d'Android et d'iOS. | Rien à faire. | Ne protège pas un téléphone allumé et déverrouillé, ni l'accès par débogage ou sauvegarde. | Insuffisant seul |

## 3. Solution proposée (option A)

1. **Clé** : 256 bits, aléatoire, générée au premier lancement et rangée dans le stockage sécurisé. Elle est liée à l'installation, indépendante du compte et du PIN : un changement d'utilisateur ne rend pas la file d'attente illisible.
2. **Ouverture** : `PRAGMA key` en premier ordre dans la connexion Drift ; contrôle immédiat (lecture de `sqlite_master`). Si la clé est absente ou fausse, l'application refuse de s'ouvrir, sans rien écraser.
3. **Migration des téléphones existants, sans perte** :
   1. copie de sécurité de la base en clair dans le dossier privé ;
   2. `PRAGMA rekey` pour chiffrer sur place ;
   3. vérification : nombre de lignes par table, dont la file des opérations en attente, identique avant et après ;
   4. si tout concorde, effacement de la copie ; sinon, retour à la copie et nouvel essai au lancement suivant. **Aucune opération en attente ne doit être perdue.**
4. **Photos d'ordonnances** : rangées **dans la base chiffrée** (table dédiée) jusqu'à leur envoi au serveur, puis effacées du téléphone. Aucune nouvelle librairie ; les fichiers en clair existants sont importés puis supprimés lors de la migration.
5. **Sauvegarde Android** désactivée (fait le 02/10), et exclusion équivalente sur iOS (fichiers marqués « non sauvegardés »).
6. **Effacement à distance** : un compte ou une FOSA suspendu reçoit 401 ; l'application ferme la session sans effacer la file d'attente (règle M-04). L'effacement complet ne se fera que sur décision explicite (hors V1).

## 4. Essais à faire au lot d (avant toute mise en œuvre définitive)

| Essai | Critère de réussite |
|---|---|
| Taille de l'APK par architecture, avec et sans `sqlite3mc` | Écart mesuré et communiqué (à faire pendant la même fenêtre que la mesure OpenCV) |
| Fichier de base sur le téléphone | L'en-tête `SQLite format 3` est absent ; l'ouverture sans la clé échoue |
| Migration d'une base de test contenant des opérations en attente | Mêmes comptes de lignes ; synchronisation ensuite sans doublon |
| Performances sur le vrai téléphone | Ouverture et listes : écart inférieur à 15 % |
| iOS (Codemagic) | Ouverture chiffrée et migration identiques à Android |
| Scénarios hors ligne a à f | Tous réussis avec la base chiffrée |

## 5. Risques

- **Clé perdue** : une réinitialisation des données de l'application efface aussi la base, donc rien n'est récupérable. Le risque réel est la perte d'opérations jamais synchronisées : l'indicateur « opérations en attente » et la synchronisation automatique le réduisent.
- **Binaire téléchargé à la compilation** : prévoir une copie interne si la compilation doit se faire sans Internet.
- **Mises à jour** : suivre les versions de `sqlite3` et de SQLite3 Multiple Ciphers à chaque lot (vérification de sécurité).

## 6. Décisions demandées

1. Valider l'option A (SQLite3 Multiple Ciphers, sans nouvelle librairie).
2. Valider le rangement des photos d'ordonnances dans la base chiffrée jusqu'à leur envoi.
3. Faire la mesure de taille `sqlite3mc` pendant la même fenêtre de compilation que la mesure OpenCV.

## 7. Réponses aux questions du 02/10

### Où est conservée la clé ?

- Dans le **coffre sécurisé du téléphone**, via `flutter_secure_storage` (déjà utilisé pour le jeton) : clé protégée par l'**Android Keystore** sur Android, entrée du **Keychain** sur iOS. Accessibilité iOS prévue : « après le premier déverrouillage, cet appareil uniquement », donc jamais copiée dans une sauvegarde iCloud ni sur un autre téléphone.
- **Jamais** dans le code, dans un fichier, dans la base elle-même, dans les journaux, ni envoyée au serveur.
- **Le PIN n'est pas la protection de la clé.** La clé est aléatoire (256 bits), générée par le téléphone, et protégée par le coffre matériel. Le PIN ajoute une barrière d'usage (verrouillage de l'écran). Les deux sont indépendants, donc oublier le PIN ne fait pas perdre la clé.
- La clé fait partie des valeurs **jamais effacées** à la fermeture de session (`AuthService.preservedStorageKeys`, en place depuis le 02/10).

### Quelle licence ?

| Moteur | Licence | Remarque |
|---|---|---|
| **SQLite3 Multiple Ciphers** (recommandé) | **MIT** | Aucune dépendance OpenSSL ; lit et écrit aussi le format SQLCipher |
| SQLCipher Community Edition | BSD (Zetetic), mention de copyright à conserver | Embarque **OpenSSL** (licence Apache 2.0) sur Android, dont les mises à jour de sécurité sont à suivre |

**DÉCISION NÉCESSAIRE** : votre validation mentionne « SQLCipher ». Je recommande SQLite3 Multiple Ciphers : même paquet `sqlite3`, même principe, chiffrement de même niveau (AES-256), licence MIT et pas d'OpenSSL à maintenir. Si vous préférez SQLCipher, il suffit de changer une option de configuration (`source: sqlcipher`), sans autre changement de code.

### Opérations en attente si le PIN est oublié ou après 5 codes faux

- La session est fermée : jeton, profil et empreinte du PIN sont effacés.
- La **base locale n'est pas effacée** : les opérations en attente y restent (chiffrées au niveau 10), avec la clé de la base et l'identifiant de l'installation.
- Après une **nouvelle connexion en ligne** du même utilisateur, l'utilisateur crée un nouveau PIN, puis la synchronisation envoie toutes les opérations, sans doublon (clés d'idempotence).
- Si un autre utilisateur se connecte sur le téléphone, les opérations du premier restent en attente pour lui et ne sont jamais envoyées sous un autre compte.
- **Tests ajoutés** (`pin_lockout_pending_operations_test.dart`) : « 5 codes faux » et « code oublié ». Dans les deux cas, les opérations sont conservées, la clé et l'identifiant restent, le reste de la session est effacé, et tout est envoyé après reconnexion.
- Défaut corrigé au passage : la fermeture de session supprimait les clés pendant qu'elle les parcourait. Elle parcourt maintenant une copie.

### Taille des photos d'ordonnances

- Réglage actuel : qualité 88 %, largeur maximale 2 200 px, soit **environ 0,6 à 1,2 Mo par photo** (estimation, à confirmer sur le téléphone).
- Réglage proposé au niveau 8 : largeur maximale 1 600 px, qualité 70 %, soit **environ 200 à 350 Ko** par ordonnance photographiée (texte lisible ; à confirmer sur le vrai téléphone avec une dizaine de documents de test imprimés, jamais de vraie ordonnance).
- Constat : l'écran actuel propose aussi un bouton « Galerie », contraire à la règle « appareil photo uniquement ». Il sera masqué au niveau 8.

## 8. Décisions du porteur (02/10)

- **SQLite3 Multiple Ciphers validé**, au format compatible SQLCipher v4 (`PRAGMA cipher = 'sqlcipher'`, `PRAGMA legacy = 4`, puis `PRAGMA key`), pour pouvoir changer plus tard sans migration lourde.
- Photos dans la base chiffrée, compressées (1 600 px, 70 %), supprimées du téléphone dès que le serveur confirme leur réception.
- Mesure de taille : dans la même compilation que la mesure OpenCV.

## 9. Mesure de taille de l'APK (niveau 1, 05/10)

Compilations `release --split-per-abi`, version `1aa1c79`, copie isolée `.tmp/essai-taille` (jamais commitée). OpenCV limité aux modules `core`, `imgproc` et `imgcodecs` ; `camera` 0.11.0 (0.12.x exige Dart 3.12).

| Version | arm64-v8a | armeabi-v7a | x86_64 (émulateurs) |
|---|---|---|---|
| Actuelle | 30,7 Mo | 27,0 Mo | 33,0 Mo |
| + chiffrement (`sqlite3mc`) | 32,6 Mo (+1,9) | 28,9 Mo (+1,9) | 35,0 Mo (+2,0) |
| + chiffrement + OpenCV + `camera` | **42,8 Mo** (+12,1) | **36,1 Mo** (+9,1) | non compilé |

Bibliothèques natives les plus lourdes (arm64) : `libflutter` 11,3 Mo, `libdartcv` (OpenCV) 10,5 Mo, `libapp` 9,1 Mo, `libbarhopper_v3` (lecteur de codes-barres) 4,9 Mo, `libsqlite3mc` 2,0 Mo, `libsqlite3` 1,7 Mo.

Constats :
- **Un APK par architecture est indispensable** : un APK unique cumulerait les trois architectures (environ 110 Mo avec OpenCV).
- `libsqlite3` (moteur non chiffré, apporté par `sqlite3_flutter_libs`, en fin de vie) reste inclus à côté de `libsqlite3mc` : le retirer au niveau 10 économise environ 1,7 Mo.
- OpenCV est l'ajout le plus lourd (+9 à +12 Mo selon l'architecture), dans l'ordre de grandeur annoncé dans `08-evaluation-librairies-capture.md`.

Proposition : distribuer `app-arm64-v8a-release.apk` (téléphones récents, dont le téléphone de test) et `app-armeabi-v7a-release.apk` (anciens téléphones 32 bits) ; sur Google Play, un « App Bundle » (`.aab`) fait ce découpage automatiquement. Option B (OpenCV) maintenue : environ 43 Mo, acceptable pour une installation unique. Repli C (`camera` seul) si ce poids est jugé trop lourd pour les FOSA à faible connexion.

Compilation : le PC a 7,7 Go de mémoire, alors que `mobile/android/gradle.properties` réserve 8 Go à Gradle. Deux compilations ont échoué faute de mémoire le 05/10. La mesure a réussi avec 3 Go, 2 tâches en parallèle et sans démon (33 min).

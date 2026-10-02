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

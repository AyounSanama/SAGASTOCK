# Évaluation des librairies de capture (AM-175) — à valider avant installation

Date : 01/10/2026. Aucune librairie n'a été installée.
Sources : pages pub.dev de chaque paquet et conditions de traitement des données ML Kit de Google, consultées le 01/10/2026.

**Exigences appliquées** (vos critères)

- Traitement 100 % sur l'appareil, sans envoi d'image ni de donnée.
- Fonctionnement entièrement hors ligne, **dès l'installation**.
- Android et iOS.
- Appareil photo uniquement, jamais la galerie.
- Licence connue.
- Maintenance active.

**Déjà présents dans l'application** (installés avant ce lot, le 02/09/2026)

- `image_picker` 1.2.3 : photo d'ordonnance actuelle.
- `mobile_scanner` 7.4.0 : scan des produits à la dispensation.

Ils sont réévalués ci-dessous, car l'un d'eux pose un problème de confidentialité.

---

## 1. Ordonnance : photo ou scan de document (bords, recadrage, redressement)

| Critère | A. `cunning_document_scanner` | B. `opencv_dart` + `camera` (écran de scan PharmaCare) | C. `camera` seul (photo + recadrage manuel) |
|---|---|---|---|
| Version / date | 3.0.3 (septembre 2026) | `opencv_dart` 2.2.2 (août 2026) ; `camera` 0.12.1 (septembre 2026) | `camera` 0.12.1 |
| Licence / coût | MIT, gratuit | Apache-2.0 et BSD-3-Clause, gratuits | BSD-3-Clause, gratuit |
| Éditeur | indépendant | rainyl.dev (vérifié) ; flutter.dev (officiel) | flutter.dev (officiel) |
| Android | Scanner **Google ML Kit, livré par Google Play services** : les modèles sont téléchargés par Play services, pas inclus dans l'application. Sans Play services, repli sur un recadrage manuel. | OpenCV **inclus dans l'application** (aucun téléchargement) ; détection des bords, correction de perspective et compression faites par nous. | Appareil photo intégré ; recadrage et rotation manuels. |
| iOS | VisionKit (Apple, système) : sur l'appareil, hors ligne. | OpenCV inclus + `camera`. | `camera`. |
| Traitement sur l'appareil | Oui (Google : « le traitement est entièrement sur l'appareil ») | Oui | Oui |
| Hors ligne dès l'installation | **Non garanti sur Android** : le module Play services doit être téléchargé une première fois, et il n'existe pas sur les téléphones sans services Google (Huawei récents, etc.). | **Oui** | **Oui** |
| Envoi de données à un tiers | Google ML Kit envoie des **mesures d'utilisation** (pas les images) ; non désactivable. | Aucun | Aucun |
| Galerie interdite | Oui (paramètre « source = appareil photo ») | Oui (pas de galerie dans notre écran) | Oui |
| Image jamais dans la galerie | Fichier rendu à l'application | Capture dans l'application (fichier temporaire, puis chiffré) | Idem B |
| Permissions | Android : aucune pour ML Kit (caméra de Play services), CAMERA pour le repli ; iOS : `NSCameraUsageDescription` | Android : CAMERA ; iOS : `NSCameraUsageDescription` (micro non demandé, pas de vidéo) | Idem B |
| Taille ajoutée | Faible | **Importante** : bibliothèques natives OpenCV par architecture. La taille n'est pas publiée ; elle peut être réduite (modules `core` et `imgproc` seuls, un APK par architecture). Mesure par un build d'essai avant décision. | Faible |
| Maintenance | Active | Active (plusieurs contributeurs) | Active (officielle) |
| Effort de développement | Faible | Moyen : écran de scan, détection, ajustement manuel des 4 coins | Faible |
| Conformité à vos critères | ❌ hors ligne non garanti, mesures envoyées à Google | ✅ | ✅, mais sans détection automatique des bords |

**Écartée** : `edge_detection` 1.1.3. Elle n'a pas eu de version depuis 2 ans, l'éditeur n'est pas vérifié, et elle a des problèmes connus avec Xcode récent.

**Recommandation : B.** C'est la seule option qui détecte les bords, fonctionne hors ligne dès l'installation, sans Google, de façon identique sur Android et iOS. **C** sert de solution de repli si la taille d'OpenCV mesurée était jugée excessive.

Dans les deux cas, `image_picker` n'est plus utilisé pour l'ordonnance. Sur Android, il ouvre l'application photo du téléphone, et certaines marques **copient alors la photo dans la galerie** : c'est contraire à la règle.

## 2. Code-barres produit (scan simple)

| Critère | A. `mobile_scanner` (déjà installé) | B. `flutter_zxing` |
|---|---|---|
| Version / date | 7.4.2 (septembre 2026) ; 7.4.0 installée | 3.1.0 (septembre 2026) |
| Licence / coût | BSD-3-Clause, gratuit | MIT, gratuit |
| Moteur Android | Google ML Kit, **modèle inclus dans l'application par défaut** (hors ligne) | zxing-cpp 3.1.1, natif (FFI) |
| Moteur iOS | Apple Vision | zxing-cpp |
| Hors ligne dès l'installation | Oui (modèle inclus) | Oui |
| Envoi de données à un tiers | Sur Android, ML Kit envoie des **mesures d'utilisation à Google** (pas les images), non désactivable | Aucun (aucun service Google, aucun réseau) |
| Formats utiles | EAN-13, EAN-8, Code 128, DataMatrix, QR… | EAN-13, EAN-8, Code 128, Code 39, DataMatrix, QR, GS1 DataBar… |
| Permissions | CAMERA ; iOS `NSCameraUsageDescription` | Idem |
| Taille ajoutée | + 3 à 10 Mo (modèle ML Kit inclus) | Non publiée (zxing-cpp est compact) ; mesure par un build d'essai |
| Versions minimales | — | Android 6 (API 23), iOS 13 |
| Maintenance | Active | Active |
| Conformité | ⚠️ conforme hors ligne, mais transmet des mesures à Google | ✅ |

**Recommandation : B (`flutter_zxing`).** Même moteur sur Android et iOS, aucune dépendance à Google, et les formats GS1 sont prêts pour la V4. `mobile_scanner` serait alors retiré des dépendances une fois le remplacement testé : il ne sert qu'au scan de la dispensation, et ce scan sera repris à l'identique.

Si vous préférez garder `mobile_scanner` (déjà en place, aucun travail), il faut l'accepter en connaissance de cause : il transmet des mesures d'utilisation à Google (pas d'images), et ce traitement doit être mentionné aux utilisateurs.

---

## Synthèse à valider

| Besoin | Recommandation | Repli |
|---|---|---|
| Ordonnance | **B** : `opencv_dart` 2.2.2 (modules `core` et `imgproc`) + `camera` 0.12.1 | C : `camera` seul |
| Code-barres | **B** : `flutter_zxing` 3.1.0 | `mobile_scanner` (déjà présent) |
| Abandon | `image_picker` pour l'ordonnance ; `mobile_scanner` après remplacement | — |

Avant l'intégration, un build d'essai mesurera la taille ajoutée par chaque librairie, Android et iOS ; ce build reste hors application, sans commit. Je vous l'enverrai pour accord final.

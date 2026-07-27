# DÃ©cisions Ã  valider

Les dÃ©cisions ci-dessous bloquent uniquement la partie mÃ©tier concernÃ©e. Le
socle technique peut avancer aprÃ¨s validation de l'architecture.

## DÃ©cisions produit

| ID | Question | Proposition initiale | Statut |
|---|---|---|---|
| PRD-001 | Plateformes mobiles V1 | Android et iOS simultanÃ©ment | ValidÃ© par le porteur |
| PRD-002 | Portail Web | Laravel Livewire pour le MVP | ValidÃ© |
| PRD-003 | Langue initiale | FranÃ§ais, architecture multilingue | ValidÃ© |
| PRD-004 | Dispensation dans le MVP | Dispensation simple incluse | ValidÃ© |
| PRD-005 | Patient dans le MVP | IdentitÃ© minimale et historique de dispensation | ValidÃ© |

## DÃ©cisions mÃ©tier

| ID | Question | Risque si non tranchÃ© |
|---|---|---|
| MET-001 | DÃ©finition officielle de la CMM et pÃ©riodes clÃ´turÃ©es | Commandes et alertes incorrectes |
| MET-002 | Formule officielle de prÃ©-rupture | Faux positifs/nÃ©gatifs |
| MET-003 | Formule et seuil de surstock | Surapprovisionnement |
| MET-004 | Formule du risque de pÃ©remption | Pertes ou alertes incorrectes |
| MET-005 | DÃ©nominateur du taux d'Ã©cart d'inventaire | Rapports contradictoires |
| MET-006 | RÃ¨gles de commande mensuelle/exceptionnelle | Approvisionnement incorrect |
| MET-007 | RÃ¨gles de substitution et protocoles | Risque clinique |
| MET-008 | Produits hors liste standard | Contournement des politiques bailleurs |
| MET-009 | Gestion du stock concurrent hors ligne | Stock nÃ©gatif ou double sortie |
| MET-010 | Source et visibilitÃ© des prix | ConfidentialitÃ© financiÃ¨re |

## DonnÃ©es personnelles

| ID | Question |
|---|---|
| DAT-001 | PÃ©rimÃ¨tre d'unicitÃ© de l'identifiant patient |
| DAT-002 | DonnÃ©es patient strictement nÃ©cessaires par programme |
| DAT-003 | DurÃ©e de conservation des patients et ordonnances |
| DAT-004 | RÃ´les autorisÃ©s Ã  consulter ou exporter les piÃ¨ces jointes |
| DAT-005 | Pays pilotes et exigences lÃ©gales applicables |

## Exploitation

| ID | Question |
|---|---|
| OPS-001 | HÃ©bergeur, rÃ©gion des donnÃ©es et nom de domaine |
| OPS-002 | Compte Google Play Console |
| OPS-003 | Compte Apple Developer et accÃ¨s Ã  un Mac de build |
| OPS-004 | ModÃ¨les de tablettes et tÃ©lÃ©phones du pilote |
| OPS-005 | FrÃ©quence minimale rÃ©aliste de synchronisation |
| OPS-006 | Organisations et formations sanitaires du pilote |


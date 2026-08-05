# Registre des formules métier

Statut : **propositions à valider, non codables en dur pour le moment**.

## Consommation moyenne mensuelle

```text
CMM = somme des consommations des mois clôturés
      / nombre de mois clôturés exploitables
```

La fenêtre et le traitement des mois en rupture doivent être configurables.

## Couverture

```text
couverture_mois = stock_disponible / CMM
```

Si `CMM = 0`, le résultat est `non_applicable`.

## Pré-rupture

```text
pré_rupture si couverture_mois
< délai_approvisionnement + délai_avant_livraison + stock_tampon
```

Tous les délais utilisent la même unité.

## Risque de péremption

```text
quantité_à_risque =
max(0, stock_du_lot - consommation_prévisionnelle_avant_expiration)
```

Calcul lot par lot.

## Écart d'inventaire

```text
écart = stock_physique - stock_théorique
```

Négatif = manquant ; positif = excédent.

## Taux de réception

```text
taux = quantité_reçue / quantité_commandée × 100
```

Quantité commandée nulle : `non_applicable`.

## Besoin de commande

```text
besoin_brut =
CMM × (délai_approvisionnement + périodicité + stock_tampon)

quantité_proposée =
max(0, besoin_brut - stock_disponible - commandes_en_cours)
```

La commande mensuelle est bloquée sans inventaire validé si le projet active
ce paramètre.

## FEFO

Ordre : lot distribuable, expiration croissante, réception croissante,
identifiant stable. Toute dérogation exige permission, motif et audit.

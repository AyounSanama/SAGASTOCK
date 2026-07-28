# Audit du module Référentiels et produits

## Statut

**Terminé et validé le 28 juillet 2026.**

## Périmètre réalisé

- Référentiels paramétrables : catégories, familles thérapeutiques, unités, formes, dosages, voies d'administration, pathologies, populations cibles, protocoles, types d'activité et niveaux de soins.
- Fournisseurs, partenaires, fabricants et bailleurs.
- Produits, codes internes, codes-barres et caractéristiques pharmaceutiques.
- Lots avec fournisseur, dates de fabrication et d'expiration, coût, devise, origine et statut.
- Kits et composition en produits.
- Listes standards versionnées, périmètre organisation/projet/programme/bailleur/formation sanitaire et publication contrôlée.
- Recherche, pagination, archivage et restauration sans suppression physique.
- Import et export des produits en CSV et en classeur Excel XLSX natif.
- Activation du module par organisation.
- Contrôle des permissions et isolation des données par organisation.
- Journalisation des opérations sensibles.
- API REST versionnée et portail web responsive reprenant l'identité PharmaCare.

## Contrôles effectués

- Migrations appliquées sur la base réelle.
- Permissions et données de référence réinjectées par le seeder.
- Compilation complète des vues Blade.
- Vérification des 31 routes web du catalogue.
- Tests du cycle complet produits, lots, kits et listes standards.
- Tests de recherche, import/export CSV et XLSX, archivage/restauration, publication, permissions, isolation inter-organisations et désactivation du module.
- Suite de non-régression globale : **39 tests réussis, 271 assertions**.

## Accès

Depuis le portail web : **Organisations → sélectionner une organisation → Référentiels et produits**.

L'utilisateur doit disposer de `catalog.view`. Les opérations de gestion nécessitent `catalog.manage`, la publication `catalog.publish` et la gestion des lots `batches.manage`.

## Prochaine étape

Conformément à l'ordre du cahier des charges, le prochain module est **Stocks et mouvements**, avec gestion des niveaux de stock par site, entrées/sorties, ajustements, traçabilité, valorisation et règles FEFO.

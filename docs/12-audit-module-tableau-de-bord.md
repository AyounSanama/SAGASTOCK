# Audit du module Tableau de bord

## Statut

**Terminé et validé le 28 juillet 2026.**

## Architecture

- `/dashboard` est la page d'accueil indépendante après authentification.
- `/users` est désormais la page dédiée au module Utilisateurs.
- Les créations, archivages et restaurations d'utilisateurs reviennent vers `/users`.
- Le Dashboard est accessible à tout utilisateur authentifié, même sans permission de gestion des utilisateurs.
- Les indicateurs et raccourcis sont filtrés selon les permissions et périmètres de l'utilisateur.

## Interface web

- Menu latéral persistant sur ordinateur et barre de navigation basse sur petit écran.
- Accès au Tableau de bord, Utilisateurs, Organisation, Structures, Référentiels, Stocks, Sécurité et Mon profil.
- Indicateurs : utilisateurs visibles, actifs, archivés, organisations, lignes et quantité de stock.
- Activités récentes, mouvements de stock récents, organisations accessibles et raccourcis contextuels.
- Identité visuelle et logo PharmaCare.

## Application Android

- Le Dashboard reste la destination automatique après connexion.
- Menu de navigation latéral professionnel.
- Indicateurs chargés depuis l'API sécurisée `/api/v1/dashboard`.
- Navigation directe vers Organisations, Stocks et Mon profil.
- Les modules non encore finalisés sont identifiés comme indisponibles au lieu d'ouvrir une page vide.

## Validation

- Compilation des vues Blade réussie.
- Analyse Flutter sans erreur.
- Tests Flutter réussis.
- Suite backend : **45 tests réussis, 318 assertions**.
- Tests dédiés authentification/navigation/utilisateurs : **12 tests, 83 assertions**.

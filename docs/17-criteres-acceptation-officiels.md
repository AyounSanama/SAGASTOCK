# Critères d'acceptation officiels

## Définition de terminé

Une étape est terminée seulement si :

- besoins et cas limites tracés ;
- backend, base, Web et mobile concernés reliés ;
- permissions et scopes contrôlés côté serveur ;
- validations et messages en français ;
- migrations valides ;
- tests concernés réussis ;
- analyse Flutter sans erreur ;
- Android compilé et testé sur appareil ;
- même code iOS, validé sur Mac dès disponibilité ;
- hors-ligne/synchronisation testés lorsque concernés ;
- aucune régression ;
- validation explicite avant l'étape suivante.

## Authentification

- Une seule page de connexion.
- Aucun choix de rôle.
- Réponse : profil, rôle, permissions et scope.
- Compte inactif, archivé ou appareil révoqué refusé.
- Connexion hors ligne seulement après une authentification en ligne valide.

## Hiérarchie

- Owner crée seulement Coordination.
- Coordination crée seulement Projet dans son organisation.
- Projet crée seulement Admin Site dans son projet.
- Admin Site crée seulement Utilisateur de son site.
- Toute tentative hors scope est refusée côté serveur.

## Assistant

- Étape suivante verrouillée avant validation.
- Brouillon persistant.
- Modification structurante invalide les dépendances.
- Résumé signale toute donnée manquante.
- Configuration validée ouvre le Centre de contrôle.

## Dashboard

- Un seul composant Flutter.
- Cartes et menus issus de `rôle + permissions + scope`.
- Aucun chiffre hors scope.
- Consultation locale possible hors connexion.

## Stock

- Toute transaction validée produit un mouvement immuable.
- Sortie supérieure au disponible bloquée.
- FEFO proposé.
- Dérogation justifiée et auditée.

## Synchronisation

- UUID et clé d'idempotence pour chaque opération.
- Aucun doublon après renvoi.
- Conflits conservés et explicables.
- Une erreur ne supprime pas les données locales.
- Aucun transfert de la base SQLite brute.

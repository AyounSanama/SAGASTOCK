# Audit final du module Utilisateurs

Date de contrôle : 28 juillet 2026

## Verdict

Le module Utilisateurs est **fonctionnel et validé pour le portail Web et l'API**,
mais il n'est pas encore déclarable à 100 % au sens de la définition de tranche
verticale du cahier des charges. Les dépendances restantes sont listées plus bas.

## Fonctionnalités validées

- Création sur une page dédiée avec prénom, nom, identifiant, e-mail, téléphone,
  mot de passe, rôle et périmètre.
- Connexion par adresse e-mail ou identifiant sur le Web et l'API.
- Consultation, recherche, filtres, modification et archivage logique.
- Liste et fiche des utilisateurs archivés.
- Restauration avec retour immédiat dans la liste active.
- Conservation de l'utilisateur, de ses rôles, appareils et journaux d'audit.
- Confirmation avant archivage et avant restauration dans l'interface.
- Protection du compte courant et du dernier propriétaire de plateforme.
- Isolation des listes, fiches et mutations selon le périmètre organisation/projet.
- Interdiction d'attribuer un rôle supérieur au périmètre de l'administrateur.
- Rôles système protégés et rôles personnalisés limités à leur périmètre.
- Changement, oubli, réinitialisation et génération de mot de passe.
- Politique forte uniforme : 12 caractères, majuscules/minuscules, chiffres et symboles.
- Verrouillage temporaire après cinq échecs sur le Web et l'API.
- Enregistrement, affichage et révocation des appareils.
- Refus de reconnexion d'un appareil explicitement révoqué.
- Affichage et révocation des sessions Web, hors session courante.
- Audit des créations, modifications, archivages, restaurations, mots de passe,
  profils, appareils et rôles.
- Interfaces Utilisateurs, Sécurité et Mon profil homogènes et responsives.
- Logo officiel présent aux emplacements d'identité prévus.

## Fonctionnement de l'archivage

L'archivage utilise `SoftDeletes` et la colonne `users.deleted_at`.

1. Une confirmation est demandée dans l'interface.
2. Le compte est désactivé.
3. Ses jetons mobiles sont supprimés et ses appareils sont révoqués.
4. L'enregistrement utilisateur est marqué comme archivé, jamais supprimé
   physiquement.
5. Les rôles, appareils et événements d'audit restent conservés.
6. L'utilisateur apparaît dans la section « Utilisateurs archivés ».
7. Après confirmation de restauration, `deleted_at` est annulé, le compte est
   réactivé et réapparaît dans la liste principale.
8. L'archivage et la restauration sont inscrits dans le journal d'audit.

Aucune route de suppression physique d'un utilisateur n'est exposée.

## Vérifications exécutées

- Migrations appliquées sans erreur.
- 30 tests Laravel réussis, 155 assertions.
- Tests dédiés à l'archivage/restauration Web et API.
- Test de conservation du rôle et de l'historique.
- Test d'isolation inter-organisation.
- Test Flutter réussi.
- L'analyse statique Flutter n'a pas produit de résultat dans le délai de
  contrôle de trois minutes ; elle doit être relancée hors du blocage actuel de
  l'outil avant une livraison.

## Dépendances restant avant le 100 % global

1. Les périmètres « formation sanitaire » et « site » seront ajoutés lorsque les
   entités correspondantes seront développées dans le prochain module
   Organisation et structures.
2. La disponibilité hors ligne SQLite et la synchronisation de l'administration
   mobile relèvent des étapes Flutter/SQLite et synchronisation de la feuille de
   route.
3. Le scénario réel iOS doit être validé sur macOS avec Xcode et un simulateur ou
   appareil iOS.
4. L'envoi réel de récupération de mot de passe doit être validé avec le serveur
   SMTP de l'environnement cible.

## État des espaces connexes

- **Sécurité** : rôles, permissions, périmètres, protections des rôles système et
  audit sont organisés dans une interface homogène.
- **Mon profil** : informations personnelles, changement de mot de passe,
  appareils mobiles et sessions Web sont regroupés dans une interface dédiée.
- **Organisation** : les API et premiers écrans d'organisations, missions,
  projets, bailleurs et programmes existent, mais le module n'est pas finalisé.
  Il constitue le prochain module de la feuille de route.

## Prochain module

Le prochain module est **Organisations et structures** :

- organisations ;
- missions et pays ;
- projets ;
- bailleurs et programmes ;
- formations sanitaires ;
- départements, pharmacies et sites de stockage/dispensation ;
- activation des modules par organisation, projet ou formation sanitaire ;
- rattachement final des utilisateurs aux périmètres formation sanitaire/site.

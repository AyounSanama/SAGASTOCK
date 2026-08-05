# Workflow officiel de configuration

## Entrée

1. Authentification sur l'unique écran de connexion.
2. Laravel retourne profil, rôle, permissions et scope.
3. Recherche d'une configuration validée pour ce scope.
4. Si elle existe : Centre de contrôle.
5. Sinon, si le rôle est autorisé : assistant.
6. Sinon : blocage et invitation à contacter l'administrateur supérieur.

## Machine d'état

Chaque étape est `not_started`, `in_progress`, `valid` ou
`needs_correction`. L'étape suivante reste verrouillée tant que la précédente
n'est pas `valid`.

| Étape | Données obligatoires | Validateur | Résultat |
|---:|---|---|---|
| 1 | Organisation, pays, identité légale | Owner | Organisation active |
| 2 | Mission, pays, organisation | Coordination | Mission active |
| 3 | Projet, mission, dates | Coordination | Projet actif |
| 4 | Bailleurs | Coordination | Financements configurés |
| 5 | Programmes | Coordination | Programmes affectés |
| 6 | Modules | Coordination | Modules versionnés |
| 7 | Fonctionnalités/paramètres | Coordination/Projet | Fonctions actives |
| 8 | Liste importée et contrôlée | Coordination | Version publiée |
| 9 | Formation sanitaire, catégorie, soins | Projet | Structure active |
| 10 | Site et rattachement | Projet | Site actif |
| 11 | Administrateur inférieur | Supérieur direct | Compte activé |
| 12 | Résumé sans anomalie | Autorité du scope | Configuration validée |

## Règles

- Brouillon persistant à chaque étape.
- Validation atomique côté serveur.
- Une modification structurante invalide les étapes dépendantes.
- Chaque validation/invalidation est auditée.
- L'assistant ne crée aucune transaction de stock.
- Le premier Admin Site est créé par l'Admin Projet.
- L'Admin Site crée uniquement les Utilisateurs Site.

## Centre de contrôle

Il affiche organisation, projet, formation sanitaire, site, état de
synchronisation, versions de configuration/liste et le bouton « Entrer dans
l'application ». Il ne remplace pas le Dashboard.

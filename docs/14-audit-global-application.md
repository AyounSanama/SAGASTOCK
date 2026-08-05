# Audit global PharmaCare

Date de contrôle : 29 juillet 2026

## Statut général

Le socle Laravel/API et les modules déjà implémentés sont stables et couverts par
les tests existants. L'application complète ne doit toutefois pas encore être
qualifiée de « prête pour la production », car plusieurs modules mobiles sont
encore provisoires ou non accessibles.

## Validations réussies

- Analyse statique Flutter : aucune erreur ni aucun avertissement.
- Tests Flutter : 4 tests réussis.
- Tests Laravel : 49 tests, 383 assertions réussies.
- Migrations Laravel : toutes appliquées.
- Compilation des vues Blade : réussie.
- Compilation Vite de production : réussie.
- Installation JavaScript reproductible : `package-lock.json` généré.
- API versionnée, authentification Sanctum, permissions et isolation par
  organisation couvertes par les tests.
- Archivage/restauration des utilisateurs, organisations, structures et
  référentiels couverts par les tests métier existants.

## Corrections réalisées pendant l'audit

- Suppression du double état actif du menu Web « Organisations et
  établissements » : seul le sous-menu correspondant à la route est actif.
- Ajout d'un état sélectionné explicite au tiroir de navigation mobile.
- Suppression de l'entrée mobile « Stock » qui pointait vers la même route que
  « Médicaments » et créait un doublon de navigation.
- Ajout des états désactivés cohérents aux boutons et éléments de navigation
  Web.
- Suppression du dernier avertissement de l'analyseur Flutter.
- Validation du Splash Screen commun Android/iOS/Flutter et de sa redirection.

## Écarts bloquant la production

### Mobile

- Les écrans « Prescriptions » et « Dispensations » utilisent encore
  `ModulePlaceholderPage`.
- Les entrées Formations sanitaires, Transferts, Alertes, Rapports,
  Utilisateurs, Rôles, Journal d'audit et Paramètres sont encore désactivées
  dans le tiroir mobile.
- La route `/stocks` redirige vers `/medications`; les domaines Catalogue et
  Stock devront être séparés lorsque le module Catalogue mobile sera développé.
- La couverture de tests UI reste faible par rapport au nombre d'écrans.

### Web

- 25 vues Blade possèdent encore un bloc `<style>` local. Le shell partagé
  impose déjà le Design System, mais ces styles locaux constituent une dette
  technique et peuvent créer des divergences.
- Les anciennes pages monolithiques doivent être migrées progressivement vers
  une mise en page Blade commune et des composants partagés.
- La recherche globale et l'indicateur de notifications du shell sont
  présentés visuellement, mais ne constituent pas encore des modules métier
  complets.

### Fonctionnel

- Les modules Prescriptions, Dispensation, Inventaires, Commandes,
  Approbations, Alertes et Rapports ne sont pas tous implémentés de bout en bout.
- Les tests de performance, d'accessibilité, de sécurité dynamique et les tests
  sur appareils iOS réels restent à exécuter avant mise en production.

## Ordre de correction recommandé

1. Terminer le module Prescriptions de bout en bout.
2. Terminer le module Dispensation et le relier aux prescriptions, patients,
   lots et mouvements.
3. Séparer clairement Catalogue/Médicaments et Stock dans la navigation mobile.
4. Développer Transferts et FEFO, puis Inventaires et Commandes.
5. Développer Alertes et Rapports.
6. Migrer les styles Blade locaux vers les composants Web partagés.
7. Étendre les tests Flutter, effectuer l'audit d'accessibilité et les tests de
   performance.


# PharmaCare UI audit — 22 septembre 2026

Audit réalisé avant modification : Laravel Blade, Vite 7/Tailwind 4 déjà
configurés, CSS partagé `public/css/pharmacare-portal.css`, nombreux styles
historiques dans les vues. Pas de migration vers Bootstrap. Tailwind conservé,
sans nouvelle dépendance ni migration massive.

Flutter : Material 3, GoRouter, AppColors/Spacing/Radius/Typography/Breakpoints,
MainLayout, AppNavigationDrawer, composants de cards, tableaux et états déjà
présents. Drift, services métier, cache et Sync Queue à préserver.

Écarts constatés : showAppFormSheet utilise showModalBottomSheet ; Web affiche
un panneau à droite puis un panneau inférieur sur mobile. Actions Web sticky
à l'intérieur du body. FOSA dispose déjà d'un PageView à quatre étapes, mais
sa fermeture ne protège pas le brouillon. Tokens et styles locaux hétérogènes.

Réutiliser thèmes, navigation et contrôles d'accès, composants partagés et
workflow FOSA. Créer un conteneur centré, une garde de fermeture et des styles
Web de formulaire autonomes. Priorité : pilote Admin Project, FOSA, sites,
équipe ; propagation par composants communs puis vérifications par rôle.

Références d'inspiration consultées :
- https://dreamsemr.dreamstechnologies.com/html/template/
- https://preclinic.dreamstechnologies.com/

Aucune donnée de démonstration n'est introduite dans les dashboards.

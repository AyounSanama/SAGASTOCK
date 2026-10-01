# Fenêtre centrée PharmaCare

Test d'intégration sans dépendance npm supplémentaire. Nécessite PHP avec les
dépendances Laravel installées, Node 22+ et Chrome. `CHROME_BIN` permet de
choisir un autre chemin Chrome ; le chemin Windows standard est utilisé sinon.

Depuis la racine du dépôt :

```powershell
php backend/tests/Browser/render_centered_sheet.php
node backend/tests/Browser/centered_sheet.mjs
```

Le test rend le vrai composant Blade avec les CSS et JS partagés, puis ouvre un
profil Chrome de test dans `.tmp`. Il vérifie cinq résolutions, les transitions
validées, le résumé, le footer fixe, l'association des boutons au formulaire et
la conservation du brouillon. Il n'envoie aucune requête de création métier.
Une capture de contrôle est produite dans `.tmp/ui-centered-sheet-tablet.png`.

Ce test ne remplace pas un parcours authentifié ni un test sur appareil réel.

## Parcours Web authentifiés

La base du parcours est isolée dans `.tmp/pharmacare-ui-browser.sqlite` ; le serveur
de test force cette connexion et n'utilise pas la base configurée dans `.env`.
Les comptes de démonstration restent dans cette base. Ne pas exposer ce serveur
au réseau : son adresse de lancement est uniquement `127.0.0.1`.

Depuis la racine, compiler puis créer la base une seule fois :

```powershell
npm --prefix backend run build
php backend/tests/Browser/ui_server.php
php -S 127.0.0.1:18765 backend/tests/Browser/ui_server.php
```

Dans un autre terminal :

```powershell
node backend/tests/Browser/pharmacare_ui.mjs
```

Le script refuse de réinitialiser une base de test existante. Les exécutions
suivantes réutilisent les données et l'état lu/non lu. Pour tester à nouveau une
première lecture, créer une nouvelle notification dans cette base isolée.

Le parcours couvre les trois administrateurs Sago, Coordination et Projet,
quatre résolutions (1920, 1366, 800 et 360 px), leurs pages de navigation,
les notifications réelles via session, la lecture, le badge, une erreur réseau
suivie d'une actualisation, et l'ouverture du formulaire Projet. Les captures
et mesures sont enregistrées dans `.tmp/ui-screenshots` et
`.tmp/ui-browser-results.json`. Le cycle métier inventaire → notification →
lecture est vérifié séparément par `InventoryManagementTest`.

## Serveur de démonstration sur le réseau local (PC + téléphone)

Sert l'application sur `http://192.168.137.1:8000` avec la base isolée
`.tmp/pharmacare-stabilisation-browser.sqlite` (jamais la base de `.env`). Les
variables d'environnement imposées (base, file `sync`, e-mails en journal) sont
prioritaires sur `.env`. Le processus reste actif après la fermeture du terminal.

```powershell
npm --prefix backend run build
& .\backend\tests\Browser\serve-demo.ps1    # démarrer
& .\backend\tests\Browser\stop-demo.ps1     # arrêter
```

Comptes de démonstration : `project_admin@ui.example`, `coordination_admin@ui.example`,
`site_admin@ui.example`, `site_user@ui.example` (mot de passe `UiBrowser123!`).
Le téléphone doit être connecté au point d'accès du PC ; le pare-feu Windows doit
autoriser le port 8000 en entrée.

<?php
$root = dirname(__DIR__, 3);
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$html = Illuminate\Support\Facades\Blade::render(<<<'BLADE'
<!doctype html><html lang="fr"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="../backend/public/css/pharmacare-portal.css">
<link rel="stylesheet" href="../backend/public/css/pharmacare-forms.css">
<script src="../backend/public/js/pharmacare-forms.js" defer></script>
<style>.form-grid{display:grid;gap:16px}.field input,.field select{display:block;width:100%;padding:12px;border:1px solid #ddd;border-radius:12px}.btn{padding:12px;border-radius:12px}.btn-primary{background:#f57c00}body{padding:24px}</style>
<h1>Test du composant PharmaCare</h1><button data-sheet-open="fixture-sheet">Ajouter une FOSA</button>
<x-form-sheet id="fixture-sheet" title="Ajouter une formation sanitaire" description="Vérification locale du composant, sans enregistrement métier.">
<form data-facility-workflow><div class="form-grid">
<label class="field">Nom<input name="name" required></label>
<label class="field">Code<input name="code" required></label>
<fieldset class="field"><legend>Projets associés</legend><label><input type="checkbox" name="project_ids[]" value="project-test" checked>Projet de test</label></fieldset>
<label class="field">Catégorie<select name="facility_type"><option value="health_center">Centre de santé</option></select></label>
<label class="field">Niveau de soins<select name="care_level" required><option value="">Sélectionner</option><option value="primary">Primaire</option></select></label>
<label class="field">E-mail<input name="email" type="email"></label>
<label class="field">Adresse<input name="address"></label>
</div><div class="sheet-actions"><button type="button" data-sheet-close="fixture-sheet">Annuler</button><button type="submit">Enregistrer</button></div></form>
</x-form-sheet></html>
BLADE);
if (!is_dir($root.'/.tmp')) mkdir($root.'/.tmp', 0777, true);
file_put_contents($root.'/.tmp/ui-web-fixture.html', $html);

<?php
// Isolated browser fixture. Never loads the application's configured database.
$root = dirname(__DIR__, 3);
$database = $root.'/.tmp/pharmacare-stabilisation-browser.sqlite';
foreach (['APP_ENV'=>'testing', 'APP_DEBUG'=>'true', 'APP_URL'=>'http://127.0.0.1:18765', 'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$database, 'DB_URL'=>'', 'SESSION_DRIVER'=>'file', 'CACHE_STORE'=>'array', 'QUEUE_CONNECTION'=>'sync'] as $key=>$value) {
    putenv("$key=$value"); $_ENV[$key]=$value; $_SERVER[$key]=$value;
}
require $root.'/backend/vendor/autoload.php';
$app = require $root.'/backend/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$app['config']->set('database.default', 'sqlite');
$app['config']->set('database.connections.sqlite.database', $database);
$app['config']->set('database.connections.sqlite.url', null);
$app['config']->set('session.files', $root.'/.tmp/ui-browser-sessions');
if (!is_dir($root.'/.tmp/ui-browser-sessions')) mkdir($root.'/.tmp/ui-browser-sessions', 0777, true);

if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? null) === '--extend-stabilisation') {
        if (!file_exists($database)) { fwrite(STDERR, "Create the isolated fixture first.\n"); exit(1); }
        require __DIR__.'/stabilisation_data.php';
        exit;
    }
    if (file_exists($database)) { fwrite(STDERR, "Fixture database already exists; reuse it instead of reseeding.\n"); exit(1); }
    touch($database);
    Illuminate\Support\Facades\Artisan::call('migrate', ['--force'=>true]);
    Illuminate\Support\Facades\Artisan::call('db:seed', ['--class'=>Database\Seeders\DatabaseSeeder::class, '--force'=>true]);
    $organization = App\Models\Organization::create(['code'=>'UI-DEMO', 'name'=>'Organisation de démonstration']);
    $mission = App\Models\Mission::create(['organization_id'=>$organization->id, 'country_id'=>App\Models\Country::where('iso2','CM')->firstOrFail()->id, 'code'=>'UI-CM', 'name'=>'Coordination Cameroun']);
    $project = App\Models\Project::create(['organization_id'=>$organization->id, 'mission_id'=>$mission->id, 'code'=>'UI-PROJECT', 'name'=>'Projet santé communautaire']);
    $facility = App\Models\HealthFacility::create(['organization_id'=>$organization->id, 'mission_id'=>$mission->id, 'code'=>'UI-FOSA', 'name'=>'Centre de santé Maroua', 'facility_type'=>'health_center', 'locality'=>'Maroua']);
    $facility->projects()->attach($project);
    $site = $facility->sites()->create(['organization_id'=>$organization->id, 'code'=>'UI-SITE', 'name'=>'Pharmacie ambulatoire', 'site_type'=>'stock_and_dispensing']);
    foreach (['sago_admin'=>['platform',null], 'coordination_admin'=>['mission',$mission->id], 'project_admin'=>['project',$project->id], 'site_admin'=>['site',$site->id], 'site_user'=>['site',$site->id]] as $role=>[$scope,$id]) {
        $user = App\Models\User::factory()->create(['name'=>'MS MAROUA', 'email'=>"$role@ui.example", 'password'=>'UiBrowser123!', 'organization_id'=>$role==='sago_admin'?null:$organization->id, 'is_active'=>true, 'must_change_password'=>false]);
        $user->roles()->attach(App\Models\Role::where('code',$role)->firstOrFail(), ['scope_type'=>$scope,'scope_id'=>$id]);
        $user->notify(new App\Notifications\OperationalNotification(['title'=>'Bienvenue dans votre espace', 'message'=>'Notification interne de contrôle.', 'action_path'=>'/profile']));
    }
    echo "Isolated browser database ready.\n";
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$public = realpath($root.'/backend/public');
$asset = realpath($public.$path);
if ($asset && str_starts_with($asset, $public.DIRECTORY_SEPARATOR) && is_file($asset) && pathinfo($asset, PATHINFO_EXTENSION) !== 'php') {
    $types = ['css'=>'text/css', 'js'=>'application/javascript', 'png'=>'image/png', 'svg'=>'image/svg+xml', 'woff2'=>'font/woff2'];
    header('Content-Type: '.($types[pathinfo($asset, PATHINFO_EXTENSION)] ?? 'application/octet-stream'));
    readfile($asset); return;
}
$app->handleRequest(Illuminate\Http\Request::capture());

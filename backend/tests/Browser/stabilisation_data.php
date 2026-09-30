<?php
// Loaded only by ui_server.php against its hard-coded isolated SQLite fixture.
$organization = App\Models\Organization::where('code', 'UI-DEMO')->firstOrFail();
$site = App\Models\Site::where('code', 'UI-SITE')->firstOrFail();
$actor = App\Models\User::where('email', 'site_admin@ui.example')->firstOrFail();
foreach (range(1, 27) as $number) {
    $code = sprintf('UI-MED-%02d', $number);
    $product = $organization->products()->firstOrCreate(['code'=>$code], ['name'=>"Médicament de contrôle $number", 'product_type'=>'medicine', 'is_active'=>true]);
    $batch = $organization->batches()->firstOrCreate(['product_id'=>$product->id, 'batch_number'=>$code], ['expires_on'=>today()->addYear(), 'status'=>'available']);
    if (!App\Models\StockBalance::where('site_id', $site->id)->where('batch_id', $batch->id)->exists()) {
        app(App\Services\StockLedgerService::class)->record($organization, $site, $batch, 'opening', 10, $actor->id);
    }
}
echo "Isolated stock pagination fixture ready.\n";

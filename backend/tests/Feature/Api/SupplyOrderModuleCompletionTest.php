<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplyOrderModuleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_web_and_mobile_use_real_order_workspaces(): void
    {
        $webRoutes=file_get_contents(base_path('routes/web.php'));
        $view=file_get_contents(resource_path('views/orders/index.blade.php'));
        $router=file_get_contents(base_path('../mobile/lib/src/core/routing/app_router.dart'));
        $service=file_get_contents(base_path('../mobile/lib/src/features/orders/data/order_service.dart'));

        $this->assertStringContainsString("[SupplyOrderController::class, 'index']",$webRoutes);
        $this->assertStringNotContainsString("defaults('module', 'orders')",$webRoutes);
        $this->assertStringContainsString('Commandes et approbations',$view);
        $this->assertStringContainsString('const OrdersPage()',$router);
        $this->assertStringContainsString('_operations.execute(',$service);
        $this->assertStringContainsString("entityType: 'orders'",$service);
        $this->assertStringContainsString("'offline_uuid'",$service);
    }

    public function test_shared_layout_uses_vite_shell_styles_and_existing_logo(): void
    {
        $sidebar=file_get_contents(resource_path('views/components/app-sidebar.blade.php'));
        $css=file_get_contents(public_path('css/pharmacare-portal.css'));
        $shell=file_get_contents(resource_path('css/shell-compat.css'));
        $assets=file_get_contents(resource_path('views/components/assets.blade.php'));
        $this->assertStringContainsString('body.pc-app.portal-body .portal-workspace',$shell);
        $this->assertStringContainsString('@vite', $assets);
        $this->assertStringContainsString('/images/pharmacare-logo.png',$sidebar);
        $this->assertStringContainsString('.configuration-wizard{width:100%;min-width:0',$css);
        $this->assertFileExists(public_path('images/pharmacare-logo.png'));
        $this->assertGreaterThan(1000,filesize(public_path('images/pharmacare-logo.png')));
    }
}

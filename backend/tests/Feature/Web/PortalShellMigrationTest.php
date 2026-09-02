<?php

namespace Tests\Feature\Web;

use Tests\TestCase;

class PortalShellMigrationTest extends TestCase
{
    public function test_all_v1_role_pages_use_the_shared_portal_shell(): void
    {
        $views = [
            'dashboard/sago.blade.php',
            'dashboard/home.blade.php',
            'organizations/index.blade.php',
            'configuration/platform-standard-home.blade.php',
            'configuration/platform-standard-organizations.blade.php',
            'configuration/platform-standard-assistance.blade.php',
            'configuration/platform-standard-history.blade.php',
            'configuration/platform-standard-history-detail.blade.php',
            'missions/index.blade.php',
            'missions/show.blade.php',
            'projects/scope.blade.php',
            'projects/standard-list.blade.php',
            'funding/index.blade.php',
            'profile/show.blade.php',
        ];

        foreach ($views as $view) {
            $source = file_get_contents(resource_path('views/'.$view));

            $this->assertStringContainsString(
                "@extends('layouts.portal')",
                $source,
                "La vue {$view} doit utiliser le shell Web PharmaCare V1 partagé.",
            );
            $this->assertStringNotContainsString(
                '<!doctype html>',
                strtolower($source),
                "La vue {$view} ne doit pas recréer un document HTML autonome.",
            );
        }
    }

    public function test_sago_organization_page_does_not_expose_operational_shortcuts(): void
    {
        $source = file_get_contents(resource_path('views/organizations/index.blade.php'));

        $this->assertStringContainsString('@unless($isSagoAdmin ?? false)', $source);
        $this->assertStringContainsString('GovernanceService::SAGO_ADMIN', $source);
    }
}

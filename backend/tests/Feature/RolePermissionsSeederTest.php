<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Support\DatabaseGuard;
use App\Support\RolePermissions;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Seeder des rôles : liste exacte par rôle, aucune suppression, garde-fous
 * sur la base réelle et la copie de travail.
 */
class RolePermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    private const SAGO = [
        'activity_logs.view', 'audit.view', 'configuration.platform.manage', 'configuration.view',
        'dashboard.view', 'organizations.manage', 'organizations.view', 'platform_standards.manage',
        'platform_standards.view', 'standards.assign',
    ];

    /** Permissions effectives actuelles (avant validation de la nouvelle matrice). */
    public static function roles(): array
    {
        return [
            'sago_admin' => ['sago_admin', self::SAGO],
            'owner' => ['owner', self::SAGO],
            'coordination_admin' => ['coordination_admin', [
                'activity_logs.view', 'catalog.view', 'dashboard.view', 'dispensations.manage', 'dispensations.view',
                'dispensing.manage', 'dispensing.view', 'dispensing_sites.manage', 'dispensing_sites.view',
                'funding.manage', 'funding.view', 'health_facilities.manage', 'health_facilities.view',
                'inventories.manage', 'inventories.validate', 'inventories.view', 'missions.manage', 'missions.view',
                'orders.approve', 'orders.manage', 'orders.view', 'patients.manage', 'patients.view',
                'prescriptions.manage', 'prescriptions.validate', 'prescriptions.view', 'products.view',
                'projects.manage', 'projects.view', 'receipts.manage', 'receipts.view', 'reports.export',
                'reports.view', 'standard_lists.manage', 'standard_lists.view', 'stocks.view',
                'synchronization.view', 'users.manage', 'users.view',
            ]],
            'project_admin' => ['project_admin', [
                'activity_logs.view', 'catalog.view', 'dashboard.view', 'dispensations.manage', 'dispensations.view',
                'dispensing.manage', 'dispensing.view', 'dispensing_sites.manage', 'dispensing_sites.view',
                'health_facilities.manage', 'health_facilities.view', 'inventories.manage', 'inventories.view',
                'orders.manage', 'orders.view', 'patients.manage', 'patients.view', 'prescriptions.manage',
                'prescriptions.validate', 'prescriptions.view', 'products.manage', 'products.view',
                'project_settings.view', 'projects.view', 'receipts.manage', 'receipts.view', 'reports.export',
                'reports.view', 'standard_lists.view', 'stocks.view', 'synchronization.view',
                'users.create_site_admin', 'users.suspend_site_admin', 'users.update_site_admin', 'users.view',
            ]],
            'site_admin' => ['site_admin', [
                'activity_logs.view_local', 'catalog.view', 'dashboard.view', 'dispensations.manage',
                'dispensations.view', 'dispensing.manage', 'dispensing.view', 'inventories.manage',
                'inventories.view', 'orders.manage', 'orders.prepare', 'orders.view', 'patients.manage',
                'patients.view', 'prescriptions.manage', 'prescriptions.view', 'products.view', 'receipts.manage',
                'receipts.view', 'reports.export_local', 'reports.view', 'site_settings.view', 'standard_lists.view',
                'stocks.manage', 'stocks.view', 'synchronization.manage', 'synchronization.view',
            ]],
            'site_user' => ['site_user', [
                'activity_logs.view_local', 'catalog.view', 'dashboard.view', 'dispensations.manage',
                'dispensations.view', 'dispensing.view', 'inventories.view', 'orders.view', 'patients.view',
                'prescriptions.manage', 'prescriptions.view', 'products.view', 'receipts.view', 'reports.view',
                'standard_lists.view', 'stocks.view', 'synchronization.view',
            ]],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_has_exactly_its_permissions(string $code, array $expected): void
    {
        $this->seed(DatabaseSeeder::class);

        $actual = Role::where('code', $code)->firstOrFail()->permissions()->pluck('code')->sort()->values()->all();
        sort($expected);
        $this->assertSame($expected, $actual, "Permissions du rôle $code");
    }

    public function test_read_only_coordination_profile_keeps_only_consultation(): void
    {
        $this->assertSame([
            'dashboard.view', 'missions.view', 'standard_lists.view', 'catalog.view', 'products.view', 'stocks.view',
            'receipts.view', 'dispensing.view', 'patients.view', 'prescriptions.view', 'dispensations.view',
            'inventories.view', 'orders.view', 'reports.view', 'synchronization.view', 'users.view',
            'activity_logs.view', 'projects.view', 'funding.view', 'health_facilities.view', 'dispensing_sites.view',
        ], RolePermissions::profilePermissions('coordination_read_only'));
    }

    public function test_seeder_never_deletes_a_role_nor_an_existing_assignment(): void
    {
        $this->seed(DatabaseSeeder::class);
        $legacy = Role::create(['code' => 'ancien_role', 'name' => 'Ancien rôle', 'is_system' => true, 'is_active' => true, 'scope_type' => 'site']);
        $custom = Role::create(['code' => 'role_maison', 'name' => 'Rôle maison', 'is_system' => false, 'is_active' => true, 'scope_type' => 'site']);
        $user = User::factory()->create();
        $user->roles()->attach($legacy->id, ['scope_type' => 'site', 'scope_id' => null]);
        $user->roles()->attach(Role::where('code', 'owner')->value('id'), ['scope_type' => 'platform', 'scope_id' => null]);
        $assignments = \DB::table('role_user')->count();
        $roles = Role::count();

        $this->seed(DatabaseSeeder::class);

        $this->assertSame($roles, Role::count());
        $this->assertSame($assignments, \DB::table('role_user')->count());
        $this->assertFalse($legacy->fresh()->is_active, 'Rôle obsolète désactivé');
        $this->assertTrue($custom->fresh()->is_active, 'Rôle non système inchangé');
        $this->assertTrue($user->roles()->whereKey($legacy->id)->exists());
    }

    public function test_labels_are_accented(): void
    {
        foreach (RolePermissions::PERMISSIONS as $code => $label) {
            $this->assertDoesNotMatchRegularExpression('/\b(Gerer|Creer|reactiver|receptions|Preparer|parametres)\b/u', $label, $code);
        }
        foreach (RolePermissions::ROLES as $code => [, , $permissions]) {
            foreach ($permissions as $permission) {
                $this->assertArrayHasKey($permission, RolePermissions::PERMISSIONS, "$code : permission inconnue $permission");
            }
        }
    }

    public function test_seeding_is_refused_on_real_and_working_databases(): void
    {
        $memory = config('database.connections.sqlite.database');
        // Simule une base sur disque : le refus intervient avant toute écriture.
        config(['database.connections.sqlite.database' => storage_path('jamais-cree.sqlite')]);
        try {
            foreach (['reelle', 'travail'] as $kind) {
                config(['pharmacare_database.kind' => $kind, 'pharmacare_database.allow_seed' => false]);
                try {
                    DatabaseGuard::assertSeedingAllowed();
                    $this->fail("Seeder accepté sur la base « $kind »");
                } catch (RuntimeException $exception) {
                    $this->assertStringContainsString('db:seed refusé', $exception->getMessage());
                }
            }
            config(['pharmacare_database.kind' => 'demo']);
            DatabaseGuard::assertSeedingAllowed();
            config(['pharmacare_database.kind' => 'reelle', 'pharmacare_database.allow_seed' => true]);
            DatabaseGuard::assertSeedingAllowed();
        } finally {
            config(['database.connections.sqlite.database' => $memory, 'pharmacare_database.kind' => 'test', 'pharmacare_database.allow_seed' => false]);
        }
        $this->assertFileDoesNotExist(storage_path('jamais-cree.sqlite'));
    }
}

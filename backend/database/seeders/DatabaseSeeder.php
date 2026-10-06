<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\DatabaseGuard;
use App\Support\RolePermissions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

/**
 * Permissions et rôles système, depuis la définition unique RolePermissions.
 *
 * - Refusé sur la base réelle et la copie de travail (DatabaseGuard), sauf
 *   dérogation explicite PHARMACARE_ALLOW_SEED=true.
 * - Ne supprime jamais un rôle et ne détache jamais un utilisateur : un rôle
 *   système absent de la définition est désactivé et signalé.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DatabaseGuard::assertSeedingAllowed();

        $permissions = collect(RolePermissions::PERMISSIONS)->map(
            fn (string $name, string $code) => Permission::updateOrCreate(['code' => $code], ['name' => $name]),
        );

        foreach (RolePermissions::ROLES as $code => [$name, $scopeType, $codes]) {
            $role = Role::updateOrCreate(['code' => $code], [
                'name' => $name,
                'is_system' => true,
                'is_active' => true,
                'scope_type' => $scopeType,
                'scope_id' => null,
            ]);
            $role->permissions()->sync($permissions->only($codes)->pluck('id'));
        }

        Role::where('is_system', true)
            ->whereNotIn('code', array_keys(RolePermissions::ROLES))
            ->where('is_active', true)
            ->get()
            ->each(function (Role $role): void {
                $role->update(['is_active' => false]);
                $message = sprintf('Rôle obsolète désactivé (non supprimé) : « %s » (%s), %d compte(s) rattaché(s).',
                    $role->name, $role->code, $role->users()->count());
                Log::warning($message);
                $this->command?->warn($message);
            });

        $email = env('SAGASTOCK_ADMIN_EMAIL');
        $password = env('SAGASTOCK_ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::firstOrCreate(
                ['email' => $email],
                ['name' => 'Administrateur PharmaCare', 'password' => $password, 'is_active' => true],
            );
            $owner = Role::where('code', 'sago_admin')->firstOrFail();
            $admin->roles()->syncWithoutDetaching([
                $owner->id => ['scope_type' => 'platform', 'scope_id' => null],
            ]);
        }
    }
}

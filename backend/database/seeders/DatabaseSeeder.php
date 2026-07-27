<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = collect([
            'organizations.view' => 'Consulter les organisations',
            'organizations.manage' => 'Gérer les organisations',
            'users.view' => 'Consulter les utilisateurs', 'users.manage' => 'Gérer les utilisateurs',
            'roles.manage' => 'Gérer les rôles', 'audit.view' => "Consulter l'audit",
        ])->map(fn ($name, $code) => Permission::firstOrCreate(['code' => $code], ['name' => $name]));
        $role = Role::firstOrCreate(['code' => 'platform_owner'], ['name' => 'Propriétaire plateforme', 'is_system' => true]);
        $role->permissions()->sync($permissions->pluck('id'));
        $email = env('SAGASTOCK_ADMIN_EMAIL');
        $password = env('SAGASTOCK_ADMIN_PASSWORD');
        if ($email && $password) {
            $admin = User::firstOrCreate(['email' => $email], ['name' => 'Administrateur SAGASTOCK', 'password' => $password, 'is_active' => true]);
            $admin->roles()->syncWithoutDetaching([$role->id => ['scope_type' => 'platform', 'scope_id' => null]]);
        }
    }
}

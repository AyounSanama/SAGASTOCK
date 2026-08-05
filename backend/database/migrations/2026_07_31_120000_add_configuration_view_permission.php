<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $permission = Permission::firstOrCreate(
            ['code' => 'configuration.view'],
            ['name' => 'Accéder à la configuration métier'],
        );

        Role::whereIn('code', ['coordination_admin', 'organization_admin'])
            ->get()
            ->each(fn (Role $role) => $role->permissions()->syncWithoutDetaching([$permission->id]));

        Role::whereIn('code', [
            'owner', 'platform_owner', 'project_admin', 'project_coordinator',
            'site_admin', 'facility_manager', 'site_user',
        ])->get()->each(fn (Role $role) => $role->permissions()->detach($permission->id));
    }

    public function down(): void
    {
        $permission = Permission::where('code', 'configuration.view')->first();
        if ($permission) {
            $permission->roles()->detach();
            $permission->delete();
        }
    }
};

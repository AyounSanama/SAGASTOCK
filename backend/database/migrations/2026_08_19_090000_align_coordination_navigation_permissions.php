<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        $codes = [
            'projects.view', 'projects.manage',
            'health_facilities.view', 'health_facilities.manage',
            'dispensing_sites.view', 'dispensing_sites.manage',
            'products.view', 'stocks.view', 'users.view',
        ];
        $permissions = collect($codes)->map(
            fn (string $code) => Permission::firstOrCreate(['code' => $code], ['name' => $code]),
        );
        Role::where('code', 'coordination_admin')->first()?->permissions()->syncWithoutDetaching($permissions->pluck('id'));
    }

    public function down(): void
    {
        // Les permissions peuvent être partagées avec des déploiements existants : aucun retrait destructif.
    }
};

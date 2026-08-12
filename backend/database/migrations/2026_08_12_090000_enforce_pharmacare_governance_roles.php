<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $now = now();
        $legacy = DB::table('roles')->where('code', 'owner')->first();
        $sago = DB::table('roles')->where('code', 'sago_admin')->first();
        if ($legacy && ! $sago) {
            DB::table('roles')->where('id', $legacy->id)->update(['code'=>'sago_admin','name'=>'Admin Sago','scope_type'=>'platform','scope_id'=>null,'is_system'=>true,'is_active'=>true,'updated_at'=>$now]);
        } elseif ($legacy && $sago) {
            foreach (DB::table('role_user')->where('role_id', $legacy->id)->get() as $assignment) {
                DB::table('role_user')->insertOrIgnore(['role_id'=>$sago->id,'user_id'=>$assignment->user_id,'scope_type'=>'platform','scope_id'=>null,'created_at'=>$assignment->created_at ?? $now,'updated_at'=>$now]);
            }
            DB::table('role_user')->where('role_id', $legacy->id)->delete();
            DB::table('permission_role')->where('role_id', $legacy->id)->delete();
            DB::table('roles')->where('id', $legacy->id)->delete();
        }
        DB::table('roles')->where('code','sago_admin')->update(['name'=>'Admin Sago','scope_type'=>'platform','scope_id'=>null,'is_system'=>true,'is_active'=>true,'updated_at'=>$now]);
        DB::table('roles')->where('code','coordination_admin')->update(['name'=>'Admin Coordination','scope_type'=>'organization','scope_id'=>null,'is_system'=>true,'is_active'=>true,'updated_at'=>$now]);

        foreach ($this->matrix() as $roleCode => $codes) {
            $roleId = DB::table('roles')->where('code', $roleCode)->value('id');
            if (! $roleId) continue;
            DB::table('permission_role')->where('role_id', $roleId)->delete();
            foreach (DB::table('permissions')->whereIn('code', $codes)->pluck('id') as $permissionId) {
                DB::table('permission_role')->insertOrIgnore(['role_id'=>$roleId,'permission_id'=>$permissionId]);
            }
        }

        $coordinationId = DB::table('roles')->where('code','coordination_admin')->value('id');
        if ($coordinationId) {
            foreach (DB::table('role_user')->where('role_id',$coordinationId)->get() as $assignment) {
                $organizationId = $assignment->scope_type === 'organization'
                    ? $assignment->scope_id
                    : DB::table('users')->where('id',$assignment->user_id)->value('organization_id');
                if ($organizationId) {
                    DB::table('role_user')->where('role_id',$coordinationId)->where('user_id',$assignment->user_id)
                        ->update(['scope_type'=>'organization','scope_id'=>$organizationId,'updated_at'=>$now]);
                }
            }
        }
        foreach (['project_admin'=>'project','site_admin'=>'site','site_user'=>'site'] as $code=>$scope) {
            DB::table('roles')->where('code',$code)->update(['scope_type'=>$scope,'scope_id'=>null,'updated_at'=>$now]);
        }
    }

    private function matrix(): array
    {
        return [
            'sago_admin'=>['dashboard.view','configuration.view','configuration.platform.manage','organizations.view','organizations.manage','missions.view','missions.manage','projects.view','standard_lists.view','standard_lists.manage','standards.assign','catalog.publish','users.view','users.manage','roles.manage','audit.view','activity_logs.view','settings.view'],
            'coordination_admin'=>['dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage','prescriptions.view','prescriptions.manage','prescriptions.validate','dispensations.view','dispensations.manage','inventories.view','inventories.manage','inventories.validate','orders.view','orders.manage','orders.approve','reports.view','reports.export','synchronization.view','users.view','users.manage','activity_logs.view'],
            'site_admin'=>['dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','stocks.manage','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','patients.manage','prescriptions.view','prescriptions.manage','dispensations.view','dispensations.manage','inventories.view','inventories.manage','orders.view','orders.manage','orders.prepare','reports.view','reports.export_local','synchronization.view','synchronization.manage','site_settings.view','activity_logs.view_local'],
            'site_user'=>['dashboard.view','standard_lists.view','catalog.view','products.view','stocks.view','receipts.view','receipts.manage','dispensing.view','dispensing.manage','patients.view','prescriptions.view','prescriptions.manage','dispensations.view','dispensations.manage','inventories.view','inventories.manage','orders.view','orders.manage','reports.view','synchronization.view','activity_logs.view_local'],
        ];
    }

    public function down(): void {}
};

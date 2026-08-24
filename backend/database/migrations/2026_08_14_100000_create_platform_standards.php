<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_standards', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('category', 50)->index();
            $table->string('code', 80)->unique();
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->json('definition')->nullable();
            $table->string('status', 20)->default('draft')->index();
            $table->boolean('is_active')->default(true)->index();
            $table->uuid('created_by')->nullable();
            $table->uuid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });

        $permissions = [
            'platform_standards.view' => 'Consulter les standards et référentiels plateforme',
            'platform_standards.manage' => 'Gérer les standards et référentiels plateforme',
        ];
        foreach ($permissions as $code => $name) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['name' => $name, 'updated_at' => now(), 'created_at' => now()]);
        }
        $roleId = DB::table('roles')->where('code', 'sago_admin')->value('id');
        if ($roleId) {
            $ids = DB::table('permissions')->whereIn('code', array_keys($permissions))->pluck('id');
            foreach ($ids as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_standards');
        $ids = DB::table('permissions')->whereIn('code', ['platform_standards.view', 'platform_standards.manage'])->pluck('id');
        DB::table('permission_role')->whereIn('permission_id', $ids)->delete();
        DB::table('permissions')->whereIn('id', $ids)->delete();
    }
};

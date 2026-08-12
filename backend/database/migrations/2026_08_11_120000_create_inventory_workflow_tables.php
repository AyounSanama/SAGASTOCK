<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->string('reference', 80);
            $table->string('inventory_type', 30)->default('monthly');
            $table->date('period_date');
            $table->string('status', 30)->default('draft');
            $table->timestamp('frozen_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->decimal('theoretical_value', 20, 4)->default(0);
            $table->decimal('physical_value', 20, 4)->default(0);
            $table->decimal('variance_value', 20, 4)->default(0);
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['site_id', 'status']);
        });
        Schema::create('inventory_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('inventory_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('theoretical_quantity', 18, 4);
            $table->decimal('physical_quantity', 18, 4)->nullable();
            $table->decimal('variance_quantity', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('variance_value', 20, 4)->nullable();
            $table->text('justification')->nullable();
            $table->foreignId('counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();
            $table->unique(['inventory_id', 'batch_id']);
        });
        DB::table('permissions')->updateOrInsert(['code' => 'inventories.validate'], ['name' => 'Valider les inventaires', 'created_at' => now(), 'updated_at' => now()]);
        $permissionId = DB::table('permissions')->where('code', 'inventories.validate')->value('id');
        $roleIds = DB::table('roles')->whereIn('code', ['owner','coordination_admin','organization_admin','project_admin','site_admin','facility_manager','pharmacist','supervisor'])->pluck('id');
        foreach ($roleIds as $roleId) DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
    }
    public function down(): void
    {
        Schema::dropIfExists('inventory_lines'); Schema::dropIfExists('inventories');
        DB::table('permissions')->where('code', 'inventories.validate')->delete();
    }
};

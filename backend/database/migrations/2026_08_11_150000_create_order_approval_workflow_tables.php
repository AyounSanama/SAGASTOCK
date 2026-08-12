<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('supply_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('requesting_site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('supplying_site_id')->nullable()->constrained('sites')->nullOnDelete();
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->string('reference', 80);
            $table->date('requested_delivery_date')->nullable();
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->default('draft');
            $table->unsignedTinyInteger('required_approval_levels')->default(1);
            $table->unsignedTinyInteger('current_approval_level')->default(0);
            $table->text('notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('prepared_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
            $table->index(['organization_id', 'status']);
            $table->index(['requesting_site_id', 'status']);
        });

        Schema::create('supply_order_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supply_order_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('requested_quantity', 18, 4);
            $table->decimal('approved_quantity', 18, 4)->nullable();
            $table->decimal('prepared_quantity', 18, 4)->nullable();
            $table->text('justification')->nullable();
            $table->timestamps();
            $table->unique(['supply_order_id', 'product_id']);
        });

        Schema::create('supply_order_approvals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('supply_order_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('decision', 20);
            $table->text('comment')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at');
            $table->timestamps();
            $table->unique(['supply_order_id', 'level']);
        });

        foreach (['orders.approve' => 'Approuver les commandes', 'orders.prepare' => 'Préparer les commandes'] as $code => $name) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
            $permissionId = DB::table('permissions')->where('code', $code)->value('id');
            $roles = $code === 'orders.approve'
                ? ['owner', 'coordination_admin', 'organization_admin', 'project_admin', 'supervisor']
                : ['owner', 'coordination_admin', 'organization_admin', 'site_admin', 'facility_manager', 'pharmacist'];
            foreach (DB::table('roles')->whereIn('code', $roles)->pluck('id') as $roleId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_order_approvals');
        Schema::dropIfExists('supply_order_lines');
        Schema::dropIfExists('supply_orders');
        DB::table('permissions')->whereIn('code', ['orders.approve', 'orders.prepare'])->delete();
    }
};

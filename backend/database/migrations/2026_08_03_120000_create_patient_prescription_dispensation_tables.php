<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('first_name', 120);
            $table->string('last_name', 120);
            $table->date('date_of_birth')->nullable();
            $table->string('sex', 20)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('external_identifier', 120)->nullable();
            $table->text('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });
        Schema::create('prescriptions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->string('reference', 80);
            $table->date('prescribed_on');
            $table->string('prescriber_name', 190);
            $table->string('status', 30)->default('draft');
            $table->text('diagnosis')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
        });
        Schema::create('prescription_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_prescribed', 18, 4);
            $table->decimal('quantity_dispensed', 18, 4)->default(0);
            $table->string('dosage', 190)->nullable();
            $table->string('frequency', 120)->nullable();
            $table->string('duration', 120)->nullable();
            $table->text('instructions')->nullable();
            $table->timestamps();
        });
        Schema::create('dispensations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('patient_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('prescription_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->uuid('offline_uuid')->nullable()->unique();
            $table->string('reference', 80);
            $table->dateTime('dispensed_at');
            $table->string('status', 30)->default('validated');
            $table->text('notes')->nullable();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
        });
        Schema::create('dispensation_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('dispensation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('prescription_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
        });

        $permissions = [
            'patients.view' => 'Consulter les patients', 'patients.manage' => 'Gérer les patients',
            'prescriptions.view' => 'Consulter les ordonnances', 'prescriptions.manage' => 'Gérer les ordonnances',
            'prescriptions.validate' => 'Valider les ordonnances',
            'dispensations.view' => 'Consulter les dispensations', 'dispensations.manage' => 'Réaliser les dispensations',
        ];
        foreach ($permissions as $code => $name) {
            DB::table('permissions')->updateOrInsert(['code' => $code], ['name' => $name, 'updated_at' => now(), 'created_at' => now()]);
        }
        $permissionIds = DB::table('permissions')->whereIn('code', array_keys($permissions))->pluck('id');
        $roleIds = DB::table('roles')->whereIn('code', ['coordination_admin', 'organization_admin', 'project_admin', 'project_coordinator', 'site_admin', 'facility_manager', 'site_user', 'pharmacist', 'clinician', 'supervisor'])->pluck('id');
        foreach ($roleIds as $roleId) {
            foreach ($permissionIds as $permissionId) {
                DB::table('permission_role')->updateOrInsert(['role_id' => $roleId, 'permission_id' => $permissionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dispensation_items');
        Schema::dropIfExists('dispensations');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('patients');
        DB::table('permissions')->whereIn('code', ['patients.view', 'patients.manage', 'prescriptions.view', 'prescriptions.manage', 'prescriptions.validate', 'dispensations.view', 'dispensations.manage'])->delete();
    }
};

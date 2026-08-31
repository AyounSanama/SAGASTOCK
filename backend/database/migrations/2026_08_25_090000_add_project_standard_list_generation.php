<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
 public function up(): void {
  Schema::table('standard_list_versions', function (Blueprint $table) {
   $table->foreignUuid('care_level_id')->nullable()->constrained('catalog_references')->nullOnDelete();
   $table->foreignUuid('facility_category_id')->nullable()->constrained('catalog_references')->nullOnDelete();
   $table->json('target_population_ids')->nullable(); $table->json('pathology_ids')->nullable(); $table->json('laboratory_exam_ids')->nullable();
  });
  Schema::create('product_standard_mappings', function (Blueprint $table) {
   $table->uuid('id')->primary(); $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete(); $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
   foreach (['care_level_id','target_population_id','pathology_id','laboratory_exam_id','facility_category_id'] as $column) $table->foreignUuid($column)->nullable()->constrained('catalog_references')->cascadeOnDelete();
   $table->timestamps(); $table->index(['organization_id','care_level_id']);
  });
  $permission = DB::table('permissions')->where('code','standard_lists.manage')->value('id'); $role = DB::table('roles')->where('code','coordination_admin')->value('id');
  if ($permission && $role) DB::table('permission_role')->insertOrIgnore(['permission_id'=>$permission,'role_id'=>$role]);
 }
 public function down(): void {
  Schema::dropIfExists('product_standard_mappings');
  Schema::table('standard_list_versions', function (Blueprint $table) { $table->dropConstrainedForeignId('care_level_id'); $table->dropConstrainedForeignId('facility_category_id'); $table->dropColumn(['target_population_ids','pathology_ids','laboratory_exam_ids']); });
 }
};

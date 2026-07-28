<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('catalog_references', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('reference_type', 50)->index();
            $table->string('code', 60);
            $table->string('name', 180);
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'reference_type', 'code'], 'catalog_reference_scope_unique');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 190);
            $table->string('supplier_type', 40)->default('supplier');
            $table->string('email', 190)->nullable();
            $table->string('phone', 40)->nullable();
            $table->text('address')->nullable();
            $table->string('country_code', 2)->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained('catalog_references')->nullOnDelete();
            $table->foreignUuid('therapeutic_family_id')->nullable()->constrained('catalog_references')->nullOnDelete();
            $table->foreignUuid('base_unit_id')->nullable()->constrained('catalog_references')->nullOnDelete();
            $table->foreignUuid('dosage_form_id')->nullable()->constrained('catalog_references')->nullOnDelete();
            $table->foreignUuid('administration_route_id')->nullable()->constrained('catalog_references')->nullOnDelete();
            $table->string('code', 60);
            $table->string('name', 190);
            $table->string('generic_name', 190)->nullable();
            $table->string('product_type', 40);
            $table->string('strength', 100)->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_controlled')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'name']);
        });

        Schema::create('product_codes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('product_id')->constrained()->cascadeOnDelete();
            $table->string('code_type', 30);
            $table->string('value', 190);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
            $table->unique(['code_type', 'value']);
        });

        Schema::create('kits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('kit_items', function (Blueprint $table) {
            $table->foreignUuid('kit_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->timestamps();
            $table->primary(['kit_id', 'product_id']);
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 100);
            $table->date('manufactured_on')->nullable();
            $table->date('expires_on')->index();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->char('currency', 3)->nullable();
            $table->string('origin', 190)->nullable();
            $table->string('status', 30)->default('available');
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'product_id', 'batch_number']);
        });

        Schema::create('standard_lists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('code', 60);
            $table->string('name', 190);
            $table->text('description')->nullable();
            $table->string('scope_type', 30);
            $table->uuid('scope_id');
            $table->boolean('allow_outside_list')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'code']);
        });

        Schema::create('standard_list_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('standard_list_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('status', 20)->default('draft');
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->text('change_notes')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->unique(['standard_list_id', 'version_number']);
        });

        Schema::create('standard_list_items', function (Blueprint $table) {
            $table->foreignUuid('standard_list_version_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->decimal('minimum_quantity', 18, 4)->nullable();
            $table->decimal('maximum_quantity', 18, 4)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->primary(['standard_list_version_id', 'product_id'], 'standard_list_item_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('standard_list_items');
        Schema::dropIfExists('standard_list_versions');
        Schema::dropIfExists('standard_lists');
        Schema::dropIfExists('batches');
        Schema::dropIfExists('kit_items');
        Schema::dropIfExists('kits');
        Schema::dropIfExists('product_codes');
        Schema::dropIfExists('products');
        Schema::dropIfExists('suppliers');
        Schema::dropIfExists('catalog_references');
    }
};

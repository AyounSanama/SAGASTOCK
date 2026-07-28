<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('theoretical_quantity', 18, 4)->default(0);
            $table->decimal('physical_quantity', 18, 4)->nullable();
            $table->decimal('reserved_quantity', 18, 4)->default(0);
            $table->timestamp('last_movement_at')->nullable();
            $table->timestamps();
            $table->unique(['site_id', 'batch_id']);
            $table->index(['organization_id', 'site_id', 'product_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->string('movement_type', 40);
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('reference_type', 50)->nullable();
            $table->uuid('reference_id')->nullable();
            $table->foreignUuid('compensates_movement_id')->nullable()->constrained('stock_movements')->restrictOnDelete();
            $table->text('reason')->nullable();
            $table->string('status', 20)->default('validated');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at');
            $table->timestamps();
            $table->index(['site_id', 'product_id', 'validated_at']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('transfers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 60);
            $table->foreignUuid('source_site_id')->constrained('sites')->restrictOnDelete();
            $table->foreignUuid('destination_site_id')->constrained('sites')->restrictOnDelete();
            $table->string('status', 30)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('dispatched_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
        });

        Schema::create('transfer_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('transfer_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_sent', 18, 4);
            $table->decimal('quantity_received', 18, 4)->nullable();
            $table->text('discrepancy_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_holds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->string('hold_type', 30);
            $table->decimal('quantity', 18, 4);
            $table->text('reason');
            $table->string('status', 20)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_holds');
        Schema::dropIfExists('transfer_items');
        Schema::dropIfExists('transfers');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_balances');
    }
};

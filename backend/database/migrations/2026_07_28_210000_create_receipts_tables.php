<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('site_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference', 60);
            $table->string('order_reference', 100)->nullable();
            $table->date('received_on');
            $table->string('status', 20)->default('draft');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'reference']);
        });
        Schema::create('receipt_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('product_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_ordered', 18, 4)->default(0);
            $table->decimal('quantity_received', 18, 4);
            $table->decimal('quantity_accepted', 18, 4);
            $table->decimal('quantity_rejected', 18, 4)->default(0);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->text('discrepancy_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('receipt_items');
        Schema::dropIfExists('receipts');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_effective_configurations', function (Blueprint $table): void {
            $table->string('synchronization_status', 20)->default('pending')->index();
            $table->timestamp('synchronization_required_at')->nullable();
        });

        Schema::create('organization_configuration_syncs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('organization_effective_configuration_id');
            $table->uuid('organization_id');
            $table->uuid('user_id');
            $table->uuid('device_id');
            $table->string('checksum', 64);
            $table->string('status', 20)->default('synced')->index();
            $table->timestamp('synced_at');
            $table->timestamps();
            $table->foreign('organization_effective_configuration_id', 'configuration_sync_effective_fk')->references('id')->on('organization_effective_configurations')->cascadeOnDelete();
            $table->foreign('organization_id')->references('id')->on('organizations')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
            $table->unique(['organization_effective_configuration_id', 'device_id'], 'configuration_sync_device_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_configuration_syncs');
        Schema::table('organization_effective_configurations', function (Blueprint $table): void {
            $table->dropColumn(['synchronization_status', 'synchronization_required_at']);
        });
    }
};

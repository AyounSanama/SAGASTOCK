<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('phone')->nullable()->after('email');
            $table->boolean('is_active')->default(true)->after('password');
            $table->timestamp('last_login_at')->nullable();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name');
            $table->boolean('is_system')->default(false); $table->timestamps();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id(); $table->string('code')->unique(); $table->string('name'); $table->timestamps();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });
        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope_type')->default('platform'); $table->uuid('scope_id')->nullable();
            $table->timestamps(); $table->unique(['role_id', 'user_id', 'scope_type', 'scope_id']);
        });
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary(); $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name'); $table->string('platform', 20); $table->string('fingerprint')->unique();
            $table->timestamp('last_seen_at')->nullable(); $table->timestamp('revoked_at')->nullable(); $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('devices'); Schema::dropIfExists('role_user');
        Schema::dropIfExists('permission_role'); Schema::dropIfExists('permissions'); Schema::dropIfExists('roles');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['uuid', 'phone', 'is_active', 'last_login_at']));
    }
};

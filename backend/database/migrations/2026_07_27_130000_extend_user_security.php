<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(true)->after('is_active');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event'); $table->string('auditable_type')->nullable();
            $table->string('auditable_id')->nullable(); $table->json('old_values')->nullable();
            $table->json('new_values')->nullable(); $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable(); $table->timestamps();
            $table->index(['auditable_type', 'auditable_id']);
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['must_change_password', 'password_changed_at']));
    }
};

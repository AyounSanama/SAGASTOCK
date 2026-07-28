<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->string('scope_type', 30)->default('platform')->after('is_system');
            $table->uuid('scope_id')->nullable()->after('scope_type');
            $table->index(['scope_type', 'scope_id']);
        });
    }

    public function down(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->dropIndex(['scope_type', 'scope_id']);
            $table->dropColumn(['scope_type', 'scope_id']);
        });
    }
};

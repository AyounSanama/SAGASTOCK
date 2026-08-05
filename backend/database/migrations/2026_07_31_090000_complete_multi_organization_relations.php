<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->text('description')->nullable()->after('manager_title');
        });
        Schema::table('users', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });
        Schema::table('sites', function (Blueprint $table) {
            $table->foreignUuid('organization_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        DB::table('sites')->orderBy('id')->each(function ($site): void {
            $organizationId = DB::table('health_facilities')
                ->where('id', $site->health_facility_id)
                ->value('organization_id');
            DB::table('sites')->where('id', $site->id)
                ->update(['organization_id' => $organizationId]);
        });
        DB::table('role_user')->where('scope_type', 'organization')
            ->whereNotNull('scope_id')->orderBy('user_id')->each(function ($role): void {
                DB::table('users')->where('id', $role->user_id)
                    ->whereNull('organization_id')
                    ->update(['organization_id' => $role->scope_id]);
            });
    }

    public function down(): void
    {
        Schema::table('sites', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('users', fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};

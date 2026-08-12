<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->string('geographic_access_type', 20)->default('single_country')->after('country_code');
        });
        Schema::create('country_organization', function (Blueprint $table): void {
            $table->foreignUuid('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('country_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->primary(['organization_id', 'country_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_organization');
        Schema::table('organizations', fn (Blueprint $table) => $table->dropColumn('geographic_access_type'));
    }
};

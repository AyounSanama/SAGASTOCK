<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('health_facilities', function (Blueprint $table): void {
            $table->string('region')->nullable()->after('address');
            $table->string('district')->nullable()->after('region');
            $table->string('locality')->nullable()->after('district');
            $table->decimal('latitude', 10, 7)->nullable()->after('locality');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }
    public function down(): void
    {
        Schema::table('health_facilities', fn (Blueprint $table) => $table->dropColumn(['region', 'district', 'locality', 'latitude', 'longitude']));
    }
};

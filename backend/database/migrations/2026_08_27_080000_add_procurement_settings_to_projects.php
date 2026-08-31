<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedSmallInteger('order_period_months')->nullable()->after('ends_on');
            $table->unsignedSmallInteger('delivery_lead_time_months')->nullable()->after('order_period_months');
            $table->unsignedSmallInteger('safety_stock_months')->nullable()->after('delivery_lead_time_months');
        });
    }

    public function down(): void
    {
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn([
            'order_period_months', 'delivery_lead_time_months', 'safety_stock_months',
        ]));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Niveau 7 (décision du 09/10) — article hors Liste Standard livré par un
 * tiers : entrée seulement avec l'origine « Autre », motif obligatoire,
 * ligne marquée « hors liste » et signalée à la Coordination.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('receipt_items', function (Blueprint $table): void {
            $table->boolean('outside_list')->default(false);
        });
        Schema::table('receipts', function (Blueprint $table): void {
            $table->string('outside_list_reason', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('receipt_items', fn (Blueprint $table) => $table->dropColumn('outside_list'));
        Schema::table('receipts', fn (Blueprint $table) => $table->dropColumn('outside_list_reason'));
    }
};

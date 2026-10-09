<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Supervision de la synchronisation : dernier état déclaré par chaque
 * téléphone (envois en attente, refusés, conflits). Aucune donnée patient :
 * seulement le module, la référence de l'opération et le motif du serveur.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_sync_states', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('device_id');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Un téléphone partagé garde l'état de chaque compte.
            $table->unique(['device_id', 'user_id']);
            $table->uuid('site_id')->nullable()->index();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('reported_at');
            $table->unsignedInteger('pending_total')->default(0);
            $table->json('pending_by_module');
            $table->json('issues');
            $table->timestamps();
            $table->foreign('device_id')->references('id')->on('devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_sync_states');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Compte en lecture seule (Admin Coordination créé par une Coordination) :
 * il voit le même périmètre mais ne peut rien créer, modifier ni supprimer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('read_only')->default(false)->after('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('read_only'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->uuid('client_reference')->nullable()->unique()->after('organization_id');
            $table->text('allergies')->nullable()->after('address');
            $table->text('clinical_notes')->nullable()->after('allergies');
        });
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->uuid('client_reference')->nullable()->unique()->after('organization_id');
            $table->string('service_origin', 190)->nullable()->after('prescriber_name');
            $table->string('attachment_path')->nullable()->after('service_origin');
            $table->text('clinical_validation_notes')->nullable()->after('notes');
            $table->text('rejection_reason')->nullable()->after('clinical_validation_notes');
            $table->boolean('protocol_confirmed')->default(false)->after('rejection_reason');
            $table->boolean('dosage_confirmed')->default(false)->after('protocol_confirmed');
            $table->boolean('contraindications_checked')->default(false)->after('dosage_confirmed');
        });
        Schema::table('prescription_items', fn (Blueprint $table) => $table->boolean('substitution_authorized')->default(false)->after('instructions'));
        Schema::table('dispensations', function (Blueprint $table) {
            $table->string('destination_type', 30)->default('patient')->after('site_id');
            $table->string('destination_name', 190)->nullable()->after('destination_type');
        });
        Schema::table('dispensation_items', function (Blueprint $table) {
            $table->decimal('quantity_requested', 18, 4)->nullable()->after('batch_id');
            $table->decimal('quantity_shortage', 18, 4)->default(0)->after('quantity');
            $table->decimal('quantity_returned', 18, 4)->default(0)->after('quantity_shortage');
        });
    }

    public function down(): void
    {
        Schema::table('dispensation_items', fn (Blueprint $table) => $table->dropColumn(['quantity_requested', 'quantity_shortage', 'quantity_returned']));
        Schema::table('dispensations', fn (Blueprint $table) => $table->dropColumn(['destination_type', 'destination_name']));
        Schema::table('prescription_items', fn (Blueprint $table) => $table->dropColumn('substitution_authorized'));
        Schema::table('prescriptions', fn (Blueprint $table) => $table->dropColumn(['client_reference', 'service_origin', 'attachment_path', 'clinical_validation_notes', 'rejection_reason', 'protocol_confirmed', 'dosage_confirmed', 'contraindications_checked']));
        Schema::table('patients', fn (Blueprint $table) => $table->dropColumn(['client_reference', 'allergies', 'clinical_notes']));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A received delivery no longer closes a shipment: it waits (status `awaiting_verification`) for the
 * office to verify it. Signed shipping tickets (PDF or image) are attached to the shipment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('verification_notes')->nullable();
        });

        Schema::create('shipment_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->default('signed_ticket');
            $table->string('original_name');
            $table->string('file_path');
            $table->string('file_mime', 100);
            $table->unsignedInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_documents');
        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['verified_at', 'verification_notes']);
        });
    }
};

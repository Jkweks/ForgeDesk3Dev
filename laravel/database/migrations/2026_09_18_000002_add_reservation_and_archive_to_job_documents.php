<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ties a JobDocument to the JobReservation it was checked/attached for (e.g.
 * the EZ Estimate a material check ran against, auto-attached when the
 * reservation is created — see BusinessJobController::createReservation()).
 * restrictOnDelete blocks a raw hard-delete of a still-referenced reservation
 * at the DB level; the app-level guard in JobDocumentController::destroy()
 * and the JobReservation model's cancel/soft-delete hooks (which flip
 * `archived` instead of deleting) handle the normal lifecycle. `archived`/
 * `archived_at` follow the same pair used on fd_work_orders.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_documents', function (Blueprint $table) {
            $table->foreignId('job_reservation_id')->nullable()->after('business_job_id')
                ->constrained('job_reservations')->restrictOnDelete();
            $table->boolean('archived')->default(false)->after('uploaded_by');
            $table->timestamp('archived_at')->nullable()->after('archived');
        });
    }

    public function down(): void
    {
        Schema::table('job_documents', function (Blueprint $table) {
            $table->dropConstrainedForeignId('job_reservation_id');
            $table->dropColumn(['archived', 'archived_at']);
        });
    }
};

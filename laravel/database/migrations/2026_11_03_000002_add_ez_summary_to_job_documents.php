<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Figures read from the Summary sheet of an uploaded EZ Estimate (see EzEstimateSummaryReader), cached on the
 * document so the SOF Savings report does not re-open an 8 MB workbook per view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('job_documents', function (Blueprint $table) {
            $table->decimal('ez_total_net', 12, 2)->nullable();     // Summary!F92  Total Net All Systems
            $table->decimal('ez_freight', 12, 2)->nullable();       // Summary!F95  freight
            $table->decimal('ez_grand_total', 12, 2)->nullable();   // Summary!F107 grand total
            $table->decimal('ez_list_price', 12, 2)->nullable();    // Summary!F111 total list price
            $table->timestamp('ez_summary_read_at')->nullable();
            $table->string('ez_summary_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('job_documents', function (Blueprint $table) {
            $table->dropColumn(['ez_total_net', 'ez_freight', 'ez_grand_total', 'ez_list_price', 'ez_summary_read_at', 'ez_summary_error']);
        });
    }
};

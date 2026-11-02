<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('business_job_shipment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipment_id')->constrained('shipments')->cascadeOnDelete();
            $table->foreignId('business_job_id')->constrained('business_jobs')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['shipment_id', 'business_job_id']);
            $table->index('business_job_id');
        });

        DB::table('shipments')->whereNotNull('business_job_id')->orderBy('id')->each(function ($s) {
            DB::table('business_job_shipment')->insert([
                'shipment_id' => $s->id, 'business_job_id' => $s->business_job_id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        });

        Schema::table('shipments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('business_job_id');
        });
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table) {
            $table->foreignId('business_job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
        });

        // Lossy: only the first job survives the way back.
        DB::table('business_job_shipment')->orderBy('id')->each(function ($r) {
            DB::table('shipments')->where('id', $r->shipment_id)->whereNull('business_job_id')
                ->update(['business_job_id' => $r->business_job_id]);
        });

        Schema::dropIfExists('business_job_shipment');
    }
};

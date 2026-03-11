<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\BusinessJob;
use App\Models\JobReservation;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates BusinessJob records from existing job_reservations that don't have a business_job_id.
     * Uses the first occurrence of each job_number to get the job_name.
     */
    public function up(): void
    {
        // Temporarily drop the unique constraint to allow updates
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->dropUnique('unique_job_reservation_id');
        });

        // Get all unique job_numbers from reservations without a business_job_id
        $jobNumbers = DB::table('job_reservations')
            ->whereNull('business_job_id')
            ->whereNotNull('job_number')
            ->select('job_number')
            ->distinct()
            ->pluck('job_number');

        foreach ($jobNumbers as $jobNumber) {
            DB::beginTransaction();

            try {
                // Check if a BusinessJob already exists with this job_number
                $existingJob = DB::table('business_jobs')
                    ->where('job_number', $jobNumber)
                    ->first();

                if ($existingJob) {
                    // Job already exists, just link the reservations to it
                    $jobId = $existingJob->id;
                    echo "Job '{$jobNumber}' already exists (ID: {$jobId}), linking reservations...\n";
                } else {
                    // Get the first reservation with this job_number to extract job_name
                    $firstReservation = DB::table('job_reservations')
                        ->where('job_number', $jobNumber)
                        ->whereNotNull('job_number')
                        ->orderBy('id', 'asc')
                        ->first();

                    if (!$firstReservation) {
                        echo "No reservation found for job_number: {$jobNumber}, skipping...\n";
                        DB::rollBack();
                        continue;
                    }

                    // Create the BusinessJob
                    $jobId = DB::table('business_jobs')->insertGetId([
                        'job_number' => $jobNumber,
                        'job_name' => $firstReservation->job_name ?? 'Imported Job',
                        'customer_name' => null,
                        'site_address' => null,
                        'contact_name' => null,
                        'contact_phone' => null,
                        'contact_email' => null,
                        'status' => 'active',
                        'start_date' => null,
                        'target_completion_date' => null,
                        'actual_completion_date' => null,
                        'notes' => 'Auto-created from existing reservations',
                        'created_by_id' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    echo "Created job '{$jobNumber}' (ID: {$jobId})\n";
                }

                // Get all reservation IDs for this job_number that need to be linked
                $reservationIds = DB::table('job_reservations')
                    ->where('job_number', $jobNumber)
                    ->whereNull('business_job_id')
                    ->orderBy('id', 'asc')
                    ->pluck('id');

                if ($reservationIds->count() > 0) {
                    // Update business_job_id for all these reservations
                    DB::table('job_reservations')
                        ->whereIn('id', $reservationIds)
                        ->update([
                            'business_job_id' => $jobId,
                            'updated_at' => now(),
                        ]);

                    // Renumber reservation_ids sequentially for this job
                    $driver = DB::connection()->getDriverName();

                    if ($driver === 'pgsql') {
                        // PostgreSQL: Use ROW_NUMBER to renumber
                        DB::statement("
                            WITH numbered AS (
                                SELECT id,
                                       ROW_NUMBER() OVER (ORDER BY id) as rn
                                FROM job_reservations
                                WHERE business_job_id = ?
                            )
                            UPDATE job_reservations
                            SET reservation_id = numbered.rn
                            FROM numbered
                            WHERE job_reservations.id = numbered.id
                        ", [$jobId]);
                    } elseif ($driver === 'sqlite') {
                        // SQLite: Update with subquery
                        DB::statement("
                            UPDATE job_reservations
                            SET reservation_id = (
                                SELECT COUNT(*) + 1
                                FROM job_reservations AS jr2
                                WHERE jr2.business_job_id = job_reservations.business_job_id
                                AND jr2.id < job_reservations.id
                            )
                            WHERE business_job_id = ?
                        ", [$jobId]);
                    } else {
                        // MySQL: Use variables
                        DB::statement("SET @rn := 0");
                        DB::statement("
                            UPDATE job_reservations
                            SET reservation_id = (@rn := @rn + 1)
                            WHERE business_job_id = ?
                            ORDER BY id
                        ", [$jobId]);
                    }

                    echo "Linked {$reservationIds->count()} reservation(s) to job '{$jobNumber}'\n";
                } else {
                    echo "No reservations to link for job '{$jobNumber}'\n";
                }

                DB::commit();
            } catch (\Exception $e) {
                DB::rollBack();
                echo "Error processing job_number '{$jobNumber}': " . $e->getMessage() . "\n";
                // Continue with next job_number instead of failing completely
            }
        }

        echo "\nJob creation from reservations completed!\n";

        // Recreate the unique constraint
        Schema::table('job_reservations', function (Blueprint $table) {
            $table->unique(['business_job_id', 'reservation_id'], 'unique_job_reservation_id');
        });

        echo "Unique constraint recreated successfully.\n";
    }

    /**
     * Reverse the migrations.
     *
     * WARNING: This will unlink reservations from jobs but won't delete the jobs,
     * as they may have been manually modified or have other relationships.
     */
    public function down(): void
    {
        // Get all jobs that were auto-created
        $autoCreatedJobs = DB::table('business_jobs')
            ->where('notes', 'Auto-created from existing reservations')
            ->pluck('id');

        if ($autoCreatedJobs->count() > 0) {
            // Unlink reservations from these jobs
            DB::table('job_reservations')
                ->whereIn('business_job_id', $autoCreatedJobs)
                ->update([
                    'business_job_id' => null,
                    'updated_at' => now(),
                ]);

            echo "Unlinked reservations from " . $autoCreatedJobs->count() . " auto-created jobs\n";
            echo "Note: Auto-created jobs were not deleted in case they have been modified.\n";
            echo "To delete them manually, run: DELETE FROM business_jobs WHERE notes = 'Auto-created from existing reservations';\n";
        }
    }
};

<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Change history (feature #11): who changed what on Project Management records, field by field. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_change_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('entity_type', 30);                 // job | po | submittal | product | vendor | manufacturer
            $table->unsignedBigInteger('entity_id');
            $table->string('entity_label')->nullable();        // e.g. "PO-2026-0007", "8001 Wege Pharmacy"
            $table->foreignId('business_job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
            $table->string('action', 10);                      // created | updated | deleted
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index('business_job_id');
            $table->index('created_at');
        });

        Schema::create('pm_change_log_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_change_log_id')->constrained('pm_change_logs')->cascadeOnDelete();
            $table->string('field', 60);
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
        });

        Permission::firstOrCreate(['name' => 'pm.history.view'], [
            'display_name' => 'PM: View Change History', 'description' => 'See who changed what on Project Management records', 'category' => 'pm',
        ]);
        foreach (['admin', 'manager', 'office_staff', 'project_manager'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('pm.history.view');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_change_log_fields');
        Schema::dropIfExists('pm_change_logs');
        Permission::where('name', 'pm.history.view')->delete();
    }
};

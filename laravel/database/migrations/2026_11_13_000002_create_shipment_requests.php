<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Shipping requests: someone asks for a shipment on a day (right-click in the shipping queue);
 * a manager or admin approves it (which creates the draft shipment) or rejects it.
 *  - shipments.request — submit a request (admin, manager, office_staff)
 *  - shipments.approve — approve / reject requests (admin, manager)
 */
return new class extends Migration
{
    private const PERMS = [
        'shipments.request' => ['Shipments: Request', 'Submit shipping requests from the shipping queue', ['admin', 'manager', 'office_staff']],
        'shipments.approve' => ['Shipments: Approve Requests', 'Approve or reject shipping requests', ['admin', 'manager']],
    ];

    public function up(): void
    {
        Schema::create('shipment_requests', function (Blueprint $t) {
            $t->id();
            $t->date('requested_date');
            $t->foreignId('job_id')->nullable()->constrained('business_jobs')->nullOnDelete();
            $t->string('custom_job', 120)->nullable();
            $t->string('destination')->nullable();
            $t->json('shipment_type_ids')->nullable();
            $t->text('notes')->nullable();
            $t->string('status', 20)->default('pending'); // pending | approved | rejected | withdrawn
            $t->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $t->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->text('decision_note')->nullable();
            $t->foreignId('shipment_id')->nullable()->constrained('shipments')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'requested_date']);
        });

        foreach (self::PERMS as $name => [$display, $desc, $roles]) {
            Permission::firstOrCreate(['name' => $name], ['display_name' => $display, 'description' => $desc, 'category' => 'shipments']);
            foreach ($roles as $role) {
                Role::where('name', $role)->first()?->assignPermission($name);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('shipment_requests');
        Permission::whereIn('name', array_keys(self::PERMS))->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'cutflow';

    /**
     * Named cut_jobs, not jobs — Laravel's own queue table already owns
     * that name.
     */
    public function up(): void
    {
        Schema::connection('cutflow')->create('cut_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // ForgeDesk's fd_work_orders.id — plain integer, no real FK
            // possible across databases. Nullable/unique: a CutJob started
            // by a manual CSV upload has no work order yet; once released
            // configurator parts land in it, this gets set and every later
            // release matches/updates the same job instead of duplicating.
            $table->unsignedBigInteger('work_order_id')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_jobs');
    }
};

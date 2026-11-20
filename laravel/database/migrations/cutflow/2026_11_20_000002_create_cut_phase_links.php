<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Manual links from a cut list's phase (the elevation/door tag on its lines) to work-order elevations, for
// uploaded lists whose tags don't match any elevation automatically. elevation_id points at the main DB.
return new class extends Migration
{
    protected $connection = 'cutflow';

    public function up(): void
    {
        Schema::connection('cutflow')->create('cut_phase_links', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cut_job_id');
            $table->string('phase', 255);   // normalised: upper-case, no spaces
            $table->unsignedBigInteger('elevation_id');
            $table->unsignedBigInteger('linked_by')->nullable();
            $table->timestamps();
            $table->unique(['cut_job_id', 'phase', 'elevation_id']);
            $table->index('cut_job_id');
        });
    }

    public function down(): void
    {
        Schema::connection('cutflow')->dropIfExists('cut_phase_links');
    }
};

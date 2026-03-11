<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_machine', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->onDelete('cascade');
            $table->foreignId('machine_id')->constrained('machines')->onDelete('cascade');
            $table->timestamps();

            $table->unique(['asset_id', 'machine_id']);
            $table->index('asset_id');
            $table->index('machine_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_machine');
    }
};

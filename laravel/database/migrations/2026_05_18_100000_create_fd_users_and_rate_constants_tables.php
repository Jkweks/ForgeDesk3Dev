<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('initials', 10)->nullable();
            $table->string('role')->default('worker'); // worker | manager | admin
            $table->string('email')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('fd_rate_constants', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->decimal('value', 8, 4);
            $table->string('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_rate_constants');
        Schema::dropIfExists('fd_users');
    }
};

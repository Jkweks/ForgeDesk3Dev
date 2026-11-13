<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cutlist_rules', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->text('description')->nullable();
            $t->string('kind', 20);                       // row | count | companion
            $t->string('severity', 10)->default('warning'); // error blocks the import, warning is reported
            $t->boolean('enabled')->default(true);
            $t->json('definition');
            $t->string('message', 500)->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $t->timestamps();
        });

        // Admin overrides of config/sill_quarter_points.php, one JSON value per config key.
        Schema::create('cutlist_rule_settings', function (Blueprint $t) {
            $t->string('key')->primary();
            $t->json('value');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cutlist_rule_settings');
        Schema::dropIfExists('cutlist_rules');
    }
};

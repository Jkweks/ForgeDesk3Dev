<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fd_elevation_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color', 7)->default('#6c757d'); // hex color for UI badges
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Seed defaults
        $defaults = [
            ['name' => 'SF',    'color' => '#3b82f6', 'sort_order' => 1],
            ['name' => 'CW',    'color' => '#f97316', 'sort_order' => 2],
            ['name' => 'Door',  'color' => '#8b5cf6', 'sort_order' => 3],
            ['name' => 'Frame', 'color' => '#10b981', 'sort_order' => 4],
            ['name' => 'Other', 'color' => '#6b7280', 'sort_order' => 5],
        ];

        foreach ($defaults as $row) {
            DB::table('fd_elevation_types')->insert(array_merge($row, [
                'active'     => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('fd_elevation_types');
    }
};

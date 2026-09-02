<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company locations: the buying entity's own addresses. The "primary" row is the
 * order-from / bill-to block printed on a purchase order; the others are pickable
 * as the ship-to address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_primary')->default(false);
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('zip')->nullable();
            $table->string('country')->default('USA');
            $table->string('phone')->nullable();
            $table->string('fax')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index('is_primary');
        });

        // Seed a single primary placeholder so a PO PDF is never blank.
        DB::table('company_locations')->insert([
            'name' => config('app.name') ?: 'My Company',
            'is_primary' => true,
            'country' => 'USA',
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('company_locations');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('committed_inventory', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_id')->constrained()->onDelete('cascade');
            $table->foreignId('order_item_id')->constrained()->onDelete('cascade');
            $table->integer('quantity_committed');
            $table->date('committed_date');
            $table->date('expected_release_date')->nullable();
            $table->timestamps();
            
            $table->index('product_id');
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('committed_inventory');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * PM Suite vendor parity (docs/plans/pm-suite-integration.md, section 4c).
 * Existing contact_* columns stay as the primary contact.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table) {
            $table->text('products_services')->nullable()->after('notes');
            $table->string('bidestimate_code')->nullable()->after('products_services');
            $table->index('bidestimate_code');
        });

        Schema::create('supplier_phones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('number', 50);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('label')->nullable();
            $table->string('address');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->boolean('is_preferred_rep')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_aliases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('alias');
            $table->string('alias_normalized')->unique();
            $table->string('source', 30)->default('manual'); // manual | bidestimate (learned)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_aliases');
        Schema::dropIfExists('supplier_emails');
        Schema::dropIfExists('supplier_phones');
        Schema::dropIfExists('supplier_contacts');
        Schema::table('suppliers', function (Blueprint $table) {
            $table->dropIndex(['bidestimate_code']);
            $table->dropColumn(['products_services', 'bidestimate_code']);
        });
    }
};

<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Product library for submittals (docs/plans/pm-suite-integration.md, feature #10): manufacturers,
 * categories and products with spec / color sheets. Separate from stock Products (pm_ prefix):
 * these are catalog entries a submittal is built from, not inventory.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pm_manufacturers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('website')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('pm_product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('pm_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_manufacturer_id')->constrained('pm_manufacturers')->restrictOnDelete();
            $table->foreignId('pm_product_category_id')->nullable()->constrained('pm_product_categories')->nullOnDelete();
            $table->string('name');
            $table->string('model')->nullable();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index('name');
        });

        Schema::create('pm_product_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pm_product_id')->constrained('pm_products')->cascadeOnDelete();
            $table->foreignId('pm_file_id')->constrained('pm_files')->cascadeOnDelete();
            $table->string('kind', 20)->default('spec');   // spec | color | cut | other
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Permission::firstOrCreate(['name' => 'pm.library.manage'], [
            'display_name' => 'PM: Manage Product Library',
            'description' => 'Add and edit manufacturers, categories, products and their spec sheets',
            'category' => 'pm',
        ]);
        foreach (['admin', 'manager'] as $role) {
            Role::where('name', $role)->first()?->assignPermission('pm.library.manage');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('pm_product_documents');
        Schema::dropIfExists('pm_products');
        Schema::dropIfExists('pm_product_categories');
        Schema::dropIfExists('pm_manufacturers');
        Permission::where('name', 'pm.library.manage')->delete();
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The standard checks ("No scratches", "Piece count matches packing list"). A category's checklist row
        // (shipment_check_items) points at one; label/type edits made here propagate to those rows.
        Schema::create('shipment_criteria', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->string('input_type', 20)->default('pass_fail');
            $table->string('unit', 20)->nullable();
            $table->json('options')->nullable();
            $table->boolean('required')->default(true);
            $table->boolean('photo_required')->default(false);
            $table->boolean('critical')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('shipment_check_items', function (Blueprint $table) {
            $table->foreignId('criterion_id')->nullable()->constrained('shipment_criteria')->nullOnDelete();
        });

        // Every existing checklist row becomes (or shares) a criterion; identical label/type/unit/options dedupe.
        $made = [];
        DB::table('shipment_check_items')->orderBy('id')->each(function ($row) use (&$made) {
            $key = mb_strtolower(trim($row->label)).'|'.$row->input_type.'|'.$row->unit.'|'.$row->options;
            if (! isset($made[$key])) {
                $made[$key] = DB::table('shipment_criteria')->insertGetId([
                    'label' => $row->label, 'input_type' => $row->input_type, 'unit' => $row->unit, 'options' => $row->options,
                    'required' => $row->required, 'photo_required' => $row->photo_required, 'critical' => $row->critical,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('shipment_check_items')->where('id', $row->id)->update(['criterion_id' => $made[$key]]);
        });
    }

    public function down(): void
    {
        Schema::table('shipment_check_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('criterion_id');
        });
        Schema::dropIfExists('shipment_criteria');
    }
};

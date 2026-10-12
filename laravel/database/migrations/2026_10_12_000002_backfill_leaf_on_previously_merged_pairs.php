<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Pairs merged before "Merge as Pair" kept a door config per leaf: they hold two door tags (e.g. 1403 + 1409) under
 * one pair opening, with a single door config that covers both. Without 'leaf' on the tags and door configs the
 * configurator treats them as an ordinary pair and shows one Door tab. Mark the first tag Door 1 (active) and the
 * second Door 2 (inactive), and give the inactive leaf its own copy of the door config so both can be edited.
 *
 * A pair imported under a single tag has one door tag and is left alone. Door parts are not touched — regenerate
 * the door parts on a converted opening to get them built per leaf.
 */
return new class extends Migration
{
    public function up(): void
    {
        $pairIds = DB::table('door_frame_opening_specs')
            ->where('opening_type', 'pair')
            ->pluck('configuration_id');

        foreach ($pairIds as $configId) {
            $doors = DB::table('door_frame_configuration_doors')
                ->where('configuration_id', $configId)
                ->orderBy('id')
                ->get();

            // Only an unconverted two-tag pair: a single-tag pair has nothing to split.
            if ($doors->count() !== 2 || $doors->whereNotNull('leaf')->isNotEmpty()) {
                continue;
            }

            DB::transaction(function () use ($configId, $doors) {
                DB::table('door_frame_configuration_doors')->where('id', $doors[0]->id)->update(['leaf' => 'active']);
                DB::table('door_frame_configuration_doors')->where('id', $doors[1]->id)->update(['leaf' => 'inactive']);

                $configs = DB::table('door_frame_door_configs')->where('configuration_id', $configId)->orderBy('id')->get();
                if ($configs->count() !== 1 || $configs[0]->leaf !== null) {
                    return;
                }

                // The existing row (and its parts) becomes Door 1; Door 2 starts as a copy of its settings.
                $copy = (array) $configs[0];
                unset($copy['id']);
                $copy['leaf'] = 'inactive';
                $copy['created_at'] = $copy['updated_at'] = now();

                DB::table('door_frame_door_configs')->where('id', $configs[0]->id)->update(['leaf' => 'active']);
                DB::table('door_frame_door_configs')->insert($copy);
            });
        }
    }

    public function down(): void
    {
        // Not reversible: the leaf markers are indistinguishable from ones set by Merge as Pair.
    }
};

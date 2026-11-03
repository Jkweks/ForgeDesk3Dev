<?php

namespace App\Services\Configurator;

use App\Models\ConfiguratorHwlibLink;
use App\Models\ConfiguratorHwlibLinkValue;
use App\Models\ConfiguratorHwlibSet;
use App\Models\ConfiguratorHwlibSetItem;
use App\Models\ConfiguratorHwlibSetItemValue;
use App\Models\DoorFrameConfiguration;
use Illuminate\Support\Facades\DB;

/**
 * Keeps openings in step with the hardware set they follow: materializes a set's items (with their
 * prep-value defaults and functions) as hardware links on an opening, re-syncs every opening after the
 * set changes, and writes prep-value edits back into a set item's defaults.
 */
class HwlibSetSync
{
    /**
     * Delete-then-reinsert the links this set previously materialized on the configuration, replaced
     * with the set's current items. Shared by first application and every re-sync.
     */
    public function materialize(ConfiguratorHwlibSet $set, DoorFrameConfiguration $configuration): void
    {
        ConfiguratorHwlibLink::where('configuration_id', $configuration->id)
            ->where('source_set_id', $set->id)
            ->delete();

        foreach ($set->setItems as $setItem) {
            $link = ConfiguratorHwlibLink::updateOrCreate(
                ['configuration_id' => $configuration->id, 'item_id' => $setItem->item_id, 'leaf' => $setItem->leaf],
                ['quantity' => $setItem->quantity, 'series' => $setItem->series, 'notes' => $setItem->notes, 'source_set_id' => $set->id]
            );

            ConfiguratorHwlibLinkValue::where('link_id', $link->id)->delete();
            foreach ($setItem->values as $value) {
                if (($value->value_text ?? '') === '') {
                    continue;
                }
                ConfiguratorHwlibLinkValue::create([
                    'link_id' => $link->id,
                    'variable_id' => $value->variable_id,
                    'value_text' => $value->value_text,
                ]);
            }

            $link->functions()->sync($setItem->functions->pluck('id'));
        }
    }

    /**
     * Re-materialize the set into every opening it's applied to, skipping any already released.
     *
     * @return array{reapplied: int[], skipped_released: int[]}
     */
    public function cascade(ConfiguratorHwlibSet $set): array
    {
        $set->load('setItems.values', 'setItems.functions', 'appliedConfigurations');

        $reapplied = [];
        $skipped = [];
        foreach ($set->appliedConfigurations as $configuration) {
            if (! $configuration->canEditHardware()) {
                $skipped[] = $configuration->id;

                continue;
            }
            DB::transaction(fn () => $this->materialize($set, $configuration));
            $reapplied[] = $configuration->id;
        }

        return ['reapplied' => $reapplied, 'skipped_released' => $skipped];
    }

    /** The set row a set-applied link came from (same set, item and leaf), if the set still has it. */
    public function setItemForLink(ConfiguratorHwlibLink $link): ?ConfiguratorHwlibSetItem
    {
        if (! $link->source_set_id) {
            return null;
        }

        return ConfiguratorHwlibSetItem::where('set_id', $link->source_set_id)
            ->where('item_id', $link->item_id)->where('leaf', $link->leaf)->first();
    }

    /** How many openings follow this set (editable or not). */
    public function openingCount(int $setId): int
    {
        return DB::table('door_frame_configuration_hwlib_sets')->where('set_id', $setId)->count();
    }

    /**
     * Write prep-value defaults onto a set item. `$operations` is a list of [ConfiguratorHwlibVariable, ?text]:
     * null clears the default. A degree-matrix default stores one value per opening angle, so the edit sets
     * the cell at `$angle` and leaves the other angles alone.
     *
     * @param  array<int, array{0: \App\Models\ConfiguratorHwlibVariable, 1: ?string}>  $operations
     */
    public function writeSetItemValues(ConfiguratorHwlibSetItem $setItem, array $operations, ?string $angle = null): void
    {
        DB::transaction(function () use ($setItem, $operations, $angle) {
            foreach ($operations as [$variable, $text]) {
                $existing = ConfiguratorHwlibSetItemValue::where('set_item_id', $setItem->id)->where('variable_id', $variable->id)->first();

                if ($text === null) {
                    $existing?->delete();

                    continue;
                }

                if ($variable->var_type === 'degree_matrix' && $angle !== null) {
                    $matrix = json_decode($existing?->value_text ?? '{}', true);
                    $matrix = is_array($matrix) ? $matrix : [];
                    $matrix[$angle] = $text;
                    $text = json_encode($matrix);
                }

                ConfiguratorHwlibSetItemValue::updateOrCreate(
                    ['set_item_id' => $setItem->id, 'variable_id' => $variable->id],
                    ['value_text' => $text]
                );
            }
        });
    }
}

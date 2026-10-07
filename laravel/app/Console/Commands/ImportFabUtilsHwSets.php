<?php

namespace App\Console\Commands;

use App\Models\BusinessJob;
use App\Models\ConfiguratorHwlibItem;
use App\Models\ConfiguratorHwlibSet;
use App\Models\ConfiguratorHwlibSetItem;
use App\Models\ConfiguratorHwlibSetItemValue;
use App\Models\ConfiguratorHwlibVariable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Imports one fab_utils job's hardware sets (hwlib_sets / hwlib_set_items / hwlib_set_item_values) into a
 * ForgeDesk job. The JSON is a per-job export with items and variables referenced by name/code, so no ids
 * have to line up between the two databases. Sets are matched by (job, name): an existing set is skipped
 * unless --replace is given, so the command is safe to re-run. Nothing is applied to openings.
 */
class ImportFabUtilsHwSets extends Command
{
    protected $signature = 'configurator:import-fab-utils-hwsets
        {file : JSON export of the fab_utils job\'s sets (path, or relative to storage/app/fab_utils_import)}
        {--job= : ForgeDesk business_jobs.id (or job number) to import into}
        {--replace : Replace sets that already exist on the job instead of skipping them}
        {--dry-run : Report what would happen and write nothing}';

    protected $description = 'Import a fab_utils job\'s hardware sets into a ForgeDesk job';

    public function handle(): int
    {
        $path = is_file($this->argument('file')) ? $this->argument('file') : storage_path('app/fab_utils_import/'.$this->argument('file'));
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $job = BusinessJob::where('id', $this->option('job'))->orWhere('job_number', $this->option('job'))->first();
        if (! $job) {
            $this->error('Job not found — pass --job=<id or job number>.');

            return self::FAILURE;
        }

        $sets = json_decode(file_get_contents($path), true) ?: [];
        $items = ConfiguratorHwlibItem::pluck('id', 'name');
        $variables = ConfiguratorHwlibVariable::pluck('id', 'code');

        // Resolve everything up front so a missing catalog entry aborts before anything is written.
        $missing = [];
        foreach ($sets as $set) {
            foreach ($set['items'] as $row) {
                if (! isset($items[$row['item']])) {
                    $missing[] = "item: {$row['item']}";
                }
                foreach ($row['values'] as $v) {
                    if (! isset($variables[$v['variable']])) {
                        $missing[] = "variable: {$v['variable']}";
                    }
                }
            }
        }
        if ($missing) {
            $this->error('Not in the ForgeDesk hardware library: '.implode(', ', array_unique($missing)));

            return self::FAILURE;
        }

        $dry = $this->option('dry-run');
        $created = $replaced = $skipped = 0;

        DB::transaction(function () use ($sets, $job, $items, $variables, $dry, &$created, &$replaced, &$skipped) {
            foreach ($sets as $data) {
                $existing = ConfiguratorHwlibSet::where('business_job_id', $job->id)->where('name', $data['name'])->first();
                if ($existing && ! $this->option('replace')) {
                    $this->line("skip     {$data['name']} (already on job)");
                    $skipped++;

                    continue;
                }
                $this->line(($existing ? 'replace  ' : 'create   ')."{$data['name']} — ".count($data['items']).' items'.($data['is_pair'] ? ' (pair)' : ''));
                $existing ? $replaced++ : $created++;
                if ($dry) {
                    continue;
                }

                // Replacing keeps the set id so openings it is applied to stay linked; they are not re-synced here.
                $set = $existing ?: new ConfiguratorHwlibSet(['business_job_id' => $job->id]);
                $set->fill(['name' => $data['name'], 'notes' => $data['notes'], 'is_pair' => (bool) $data['is_pair']])->save();
                $set->setItems()->delete();

                foreach ($data['items'] as $row) {
                    $setItem = ConfiguratorHwlibSetItem::create([
                        'set_id' => $set->id,
                        'item_id' => $items[$row['item']],
                        'quantity' => $row['quantity'],
                        'notes' => $row['notes'],
                        'series' => $row['series'],
                        'leaf' => $row['leaf'],
                    ]);
                    foreach ($row['values'] as $v) {
                        ConfiguratorHwlibSetItemValue::create([
                            'set_item_id' => $setItem->id,
                            'variable_id' => $variables[$v['variable']],
                            'value_text' => $v['value_text'],
                        ]);
                    }
                }
            }
        });

        $this->info(($dry ? '[dry run] ' : '')."Job {$job->job_number} {$job->job_name}: {$created} created, {$replaced} replaced, {$skipped} skipped.");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\ConfiguratorPdfTemplate;
use App\Services\Configurator\PackageReportService;
use Illuminate\Console\Command;

/**
 * Imports fab_utils' saved door/frame report layouts (pdf_templates: block order / span / enabled /
 * per-column toggles) from storage/app/fab_utils_import/pdf_templates.json, so the fabricators'
 * sheets keep their current format. Blocks ForgeDesk doesn't know are dropped. Re-runnable
 * (replaces the saved layout per report type).
 */
class ImportFabUtilsPdfTemplates extends Command
{
    protected $signature = 'configurator:import-fab-utils-pdf-templates {--path=fab_utils_import : Directory under storage/app}';

    protected $description = 'Import the fab_utils door/frame report layout templates';

    public function handle(): int
    {
        $file = storage_path('app/'.$this->option('path').'/pdf_templates.json');
        if (! is_file($file)) {
            $this->error("Not found: {$file}");

            return self::FAILURE;
        }

        foreach (json_decode(file_get_contents($file), true) ?: [] as $row) {
            $type = $row['report_type'] ?? null;
            if (! isset(PackageReportService::BLOCKS[$type])) {
                continue;
            }
            $layout = is_string($row['layout']) ? json_decode($row['layout'], true) : $row['layout'];
            $known = PackageReportService::BLOCKS[$type];

            $clean = [];
            foreach ($layout as $item) {
                if (! isset($known[$item['key'] ?? ''])) {
                    continue;
                }
                $span = $item['span'] ?? (($item['width'] ?? '') === 'half' ? 6 : 12);
                $clean[] = array_filter([
                    'key' => $item['key'], 'span' => max(1, min(12, (int) $span)),
                    'enabled' => $item['enabled'] ?? true, 'cols' => $item['cols'] ?? null,
                ], fn ($v) => $v !== null);
            }

            // fab_utils slots hwlib_backers in after the variables when a saved layout predates it.
            if (! collect($clean)->contains('key', 'hwlib_backers')) {
                $i = collect($clean)->search(fn ($x) => $x['key'] === 'hwlib_variables');
                array_splice($clean, $i === false ? count($clean) : $i, 0, [['key' => 'hwlib_backers', 'span' => 12, 'enabled' => true]]);
            }

            ConfiguratorPdfTemplate::updateOrCreate(['report_type' => $type], ['layout' => $clean]);
            $this->info("Imported {$type} layout: ".count($clean).' blocks.');
        }

        return self::SUCCESS;
    }
}

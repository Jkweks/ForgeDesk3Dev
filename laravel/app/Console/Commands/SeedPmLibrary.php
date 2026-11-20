<?php

namespace App\Console\Commands;

use App\Models\PmFile;
use App\Models\PmManufacturer;
use App\Models\PmProduct;
use App\Models\PmProductCategory;
use App\Models\PmProductDocument;
use App\Services\Pm\PmFileIngestor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads the PM Suite's product library export (manufacturers, categories, products and the spec / cut / color sheets
 * listed for each) into the submittal product library. The export carries each document's label, type and FILE NAME,
 * not the PDF, so a document is attached only when a PDF of that name can be found: in --files (searched recursively,
 * the newest wins, as the PM Suite's own index does) or already registered in ForgeDesk (e.g. read from F:).
 * Everything else is listed as waiting. Safe to run again: products are matched by manufacturer + name and documents
 * by file name, so a second run after the PDFs turn up attaches them and creates nothing twice.
 */
class SeedPmLibrary extends Command
{
    protected $signature = 'pm:seed-library {export : The PM Suite library export (library_export.json)}
        {--files= : Folder holding the PDFs, searched recursively by file name}
        {--missing= : Write the file names still waiting for a PDF to this CSV}
        {--dry-run : Report what would happen and change nothing}';

    protected $description = 'Seed the PM product library from a PM Suite export, attaching documents where the PDFs can be found';

    /** The PM Suite's document types, in the library's four kinds (the PM label is kept as the document's label). */
    private const KIND = ['Cut Sheet' => 'cut', 'Color Chart' => 'color', 'Product Data' => 'spec'];

    public function handle(PmFileIngestor $ingestor): int
    {
        $path = (string) $this->argument('export');
        $rows = is_file($path) ? json_decode((string) file_get_contents($path), true) : null;
        if (! is_array($rows) || ! array_is_list($rows)) {
            $this->error("Could not read a product list from {$path}.");

            return self::FAILURE;
        }
        $dir = $this->option('files');
        if ($dir && ! is_dir($dir)) {
            $this->error("The folder {$dir} does not exist.");

            return self::FAILURE;
        }
        $pdfs = $dir ? $this->indexPdfs($dir) : [];
        $dry = (bool) $this->option('dry-run');
        $n = ['manufacturers' => 0, 'categories' => 0, 'products' => 0, 'existing' => 0, 'attached' => 0, 'already' => 0, 'waiting' => 0, 'skipped' => 0];
        $waiting = [];
        $sort = 0;
        $makers = [];      // name => manufacturer / category, so a dry run (nothing saved) still counts each only once
        $cats = [];

        // seeding is not a person's edit: keep it out of the change history
        PmProduct::withoutEvents(function () use ($rows, $pdfs, $dry, $ingestor, &$n, &$waiting, &$sort, &$makers, &$cats) {
            DB::transaction(function () use ($rows, $pdfs, $dry, $ingestor, &$n, &$waiting, &$sort, &$makers, &$cats) {
            foreach ($rows as $row) {
                $maker = trim((string) ($row['manufacturer'] ?? ''));
                $name = trim((string) ($row['product'] ?? ''));
                if ($maker === '' || $name === '') {
                    $n['skipped']++;

                    continue;
                }
                $mf = $makers[$maker] ?? PmManufacturer::where('name', $maker)->first();
                if (! $mf) {
                    $n['manufacturers']++;
                    $mf = $dry ? new PmManufacturer(['name' => $maker]) : PmManufacturer::create(['name' => $maker]);
                }
                $makers[$maker] = $mf;
                $cat = null;
                if (($c = trim((string) ($row['category'] ?? ''))) !== '') {
                    $cat = $cats[$c] ?? PmProductCategory::where('name', $c)->first();
                    if (! $cat) {
                        $n['categories']++;
                        $cat = $dry ? new PmProductCategory(['name' => $c]) : PmProductCategory::create(['name' => $c, 'sort_order' => ++$sort]);
                    }
                    $cats[$c] = $cat;
                }
                $product = $mf->exists ? PmProduct::where('pm_manufacturer_id', $mf->id)->where('name', $name)->first() : null;
                if ($product) {
                    $n['existing']++;
                } else {
                    $n['products']++;
                    $tags = array_filter(array_map('strval', (array) ($row['tags'] ?? [])));
                    $attrs = ['pm_manufacturer_id' => $mf->id, 'pm_product_category_id' => $cat?->id, 'name' => $name,
                        'description' => $tags ? 'Tags: '.implode(', ', $tags) : null, 'is_active' => true];
                    $product = $dry ? new PmProduct($attrs) : PmProduct::create($attrs);
                }

                foreach ((array) ($row['files'] ?? []) as $doc) {
                    $fn = trim((string) ($doc['filename'] ?? ''));
                    if ($fn === '') {
                        continue;
                    }
                    if ($product->exists && $product->documents()->whereHas('file', fn ($q) => $q->where('original_name', $fn))->exists()) {
                        $n['already']++;

                        continue;
                    }
                    $file = PmFile::where('original_name', $fn)->orderByDesc('id')->first();
                    if (! $file && isset($pdfs[strtolower($fn)])) {
                        $file = $dry ? new PmFile(['original_name' => $fn]) : $ingestor->registerBytes((string) file_get_contents($pdfs[strtolower($fn)]), $fn, 'product_document', null, null);
                    }
                    if (! $file) {
                        $n['waiting']++;
                        $waiting[] = [$maker, $name, (string) ($doc['label'] ?? ''), (string) ($doc['type'] ?? ''), $fn];

                        continue;
                    }
                    $n['attached']++;
                    if (! $dry) {
                        PmProductDocument::create(['pm_product_id' => $product->id, 'pm_file_id' => $file->id,
                            'kind' => self::KIND[$doc['type'] ?? ''] ?? 'other', 'label' => ($doc['label'] ?? null) ?: null]);
                    }
                }
            }
            });
        });

        $this->table(['', 'Count'], [
            ['Manufacturers '.($dry ? 'to create' : 'created'), $n['manufacturers']], ['Categories '.($dry ? 'to create' : 'created'), $n['categories']],
            ['Products '.($dry ? 'to create' : 'created'), $n['products']], ['Products already there', $n['existing']], ['Rows skipped (no manufacturer or name)', $n['skipped']],
            ['Documents '.($dry ? 'to attach' : 'attached'), $n['attached']], ['Documents already attached', $n['already']],
            ['Documents waiting for a PDF', $n['waiting']],
        ]);
        if ($waiting) {
            $names = array_unique(array_column($waiting, 4));
            $this->warn(count($names).' distinct PDF file names have no file yet. Point --files at the folder that holds them (the PM Suite keeps them under "Submittal - General") and run this again.');
            if ($m = $this->option('missing')) {
                $fh = fopen($m, 'w');
                fputcsv($fh, ['manufacturer', 'product', 'label', 'type', 'filename'], ',', '"', '\\');
                foreach ($waiting as $w) {
                    fputcsv($fh, $w, ',', '"', '\\');
                }
                fclose($fh);
                $this->line("Wrote the list to {$m}.");
            }
        }

        return self::SUCCESS;
    }

    /** {lower-case file name => full path}; when a name appears more than once the newest file wins. */
    private function indexPdfs(string $dir): array
    {
        $found = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)) as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'pdf' && $f->getSize() > 0) {
                $k = strtolower($f->getFilename());
                if (! isset($found[$k]) || $f->getMTime() > filemtime($found[$k])) {
                    $found[$k] = $f->getPathname();
                }
            }
        }

        return $found;
    }
}

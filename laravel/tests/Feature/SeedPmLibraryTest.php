<?php

namespace Tests\Feature;

use App\Models\PmFile;
use App\Models\PmManufacturer;
use App\Models\PmProduct;
use App\Models\PmProductCategory;
use App\Models\PmProductDocument;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** pm:seed-library: the PM Suite's library export into the submittal product library. */
class SeedPmLibraryTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->dir = sys_get_temp_dir().'/seedlib_'.uniqid();
        mkdir($this->dir.'/pdfs/Tubelite/Storefront', 0777, true);
    }

    protected function tearDown(): void
    {
        foreach ([$this->dir.'/pdfs/Tubelite/Storefront', $this->dir.'/pdfs/Tubelite', $this->dir.'/pdfs', $this->dir] as $d) {
            foreach (glob($d.'/*') ?: [] as $f) {
                is_file($f) && unlink($f);
            }
            is_dir($d) && rmdir($d);
        }
        parent::tearDown();
    }

    private function export(): string
    {
        $rows = [
            ['id' => 'tl-1', 'manufacturer' => 'Tubelite', 'product' => 'TU14000', 'category' => 'Storefront', 'colorScheme' => 'tubelite', 'tags' => ['aluminum', 'storefront'],
                'files' => [['label' => 'Cut sheet', 'type' => 'Cut Sheet', 'filename' => 'Tubelite_TU14000_Cut.pdf'], ['label' => 'Colors', 'type' => 'Color Chart', 'filename' => 'Tubelite_Colors.pdf'],
                    ['label' => 'AAMA test', 'type' => 'Test Report', 'filename' => 'Tubelite_TU14000_AAMA.pdf']]],
            ['id' => 'tl-2', 'manufacturer' => 'Tubelite', 'product' => 'TU24000', 'category' => 'Storefront', 'tags' => [], 'files' => [['label' => 'Colors', 'type' => 'Color Chart', 'filename' => 'Tubelite_Colors.pdf']]],
            ['id' => 'g-1', 'manufacturer' => 'Guardian', 'product' => 'SN68', 'category' => 'Glazing', 'files' => []],
            ['id' => 'bad', 'manufacturer' => '', 'product' => 'No maker'],
        ];
        file_put_contents($this->dir.'/export.json', json_encode($rows));

        return $this->dir.'/export.json';
    }

    public function test_products_are_created_and_documents_wait_for_their_pdfs(): void
    {
        $this->artisan('pm:seed-library', ['export' => $this->export(), '--missing' => $this->dir.'/missing.csv'])->assertSuccessful();

        $this->assertSame(['Guardian', 'Tubelite'], PmManufacturer::orderBy('name')->pluck('name')->all());
        $this->assertSame(['Glazing', 'Storefront'], PmProductCategory::orderBy('name')->pluck('name')->all());
        $this->assertSame(3, PmProduct::count());                                      // the row with no manufacturer is skipped
        $p = PmProduct::where('name', 'TU14000')->firstOrFail();
        $this->assertSame('Tags: aluminum, storefront', $p->description);
        $this->assertSame(0, PmProductDocument::count());                              // no PDFs were found: nothing invented
        $csv = file_get_contents($this->dir.'/missing.csv');
        $this->assertStringContainsString('Tubelite_TU14000_Cut.pdf', $csv);
        $this->assertSame(0, \App\Models\PmChangeLog::count());                        // seeding is not a person's edit
    }

    public function test_documents_are_attached_where_the_pdf_exists_and_a_second_run_finishes_the_job(): void
    {
        file_put_contents($this->dir.'/pdfs/Tubelite/Storefront/Tubelite_TU14000_Cut.pdf', '%PDF-1 cut');
        file_put_contents($this->dir.'/pdfs/Tubelite/Tubelite_Colors.pdf', '%PDF-1 colors');       // used by both Tubelite products
        $this->artisan('pm:seed-library', ['export' => $this->export(), '--files' => $this->dir.'/pdfs'])->assertSuccessful();

        $docs = PmProductDocument::with('product', 'file')->get();
        $this->assertCount(3, $docs);                                                  // cut + colors on TU14000, colors on TU24000
        $cut = $docs->first(fn ($d) => $d->file->original_name === 'Tubelite_TU14000_Cut.pdf');
        $this->assertSame('cut', $cut->kind);
        $this->assertSame('Cut sheet', $cut->label);
        $this->assertSame('color', $docs->first(fn ($d) => $d->file->original_name === 'Tubelite_Colors.pdf')->kind);
        $this->assertSame(1, PmFile::where('original_name', 'Tubelite_Colors.pdf')->count());    // one stored file, shared

        // the missing test report turns up later; running again attaches it and duplicates nothing
        file_put_contents($this->dir.'/pdfs/Tubelite_TU14000_AAMA.pdf', '%PDF-1 aama');
        $this->artisan('pm:seed-library', ['export' => $this->export(), '--files' => $this->dir.'/pdfs'])->assertSuccessful();
        $this->assertSame(3, PmProduct::count());
        $this->assertCount(4, PmProductDocument::all());
        $aama = PmProductDocument::whereHas('file', fn ($q) => $q->where('original_name', 'Tubelite_TU14000_AAMA.pdf'))->firstOrFail();
        $this->assertSame('other', $aama->kind);                                       // a test report is not one of the library's kinds
        $this->assertSame('AAMA test', $aama->label);                                  // the PM Suite's label is kept
    }

    public function test_dry_run_changes_nothing_and_a_bad_file_is_refused(): void
    {
        $this->artisan('pm:seed-library', ['export' => $this->export(), '--dry-run' => true])->assertSuccessful();
        $this->assertSame(0, PmProduct::count());
        $this->assertSame(0, PmManufacturer::count());

        file_put_contents($this->dir.'/nope.json', '{"not": "a list"}');
        $this->artisan('pm:seed-library', ['export' => $this->dir.'/nope.json'])->assertFailed();
        $this->artisan('pm:seed-library', ['export' => $this->export(), '--files' => $this->dir.'/missing-dir'])->assertFailed();
    }
}

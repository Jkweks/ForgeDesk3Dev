<?php

namespace Tests\Feature;

use App\Services\CutFlow\SillQuarterPointService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SillQuarterPointUploadTest extends TestCase
{
    private function csv(array $rows): string
    {
        return collect($rows)->map(fn ($r) => implode(',', $r))->implode("\n")."\n";
    }

    public function test_upload_with_sills_is_stored_and_report_can_be_rebuilt(): void
    {
        Storage::fake('local');
        $svc = new SillQuarterPointService(null, fn () => null);
        $content = $this->csv([
            ['T15356', 'C1', '100', '1', 'Harbor', 'WO3', '4-E3', '1', '1', 'LEFT JAMB', '0', '0', ''],
            ['T15310', 'C1', '36', '1', 'Harbor', 'WO3', '4-E3', '1', '1', 'SILL', '0', '0', ''],
            ['T15356', 'C1', '100', '1', 'Harbor', 'WO3', '4-E3', '1', '1', 'RIGHT JAMB', '0', '0', ''],
        ]);

        $summary = $svc->storeUpload(UploadedFile::fake()->createWithContent('cut.csv', $content), 42);

        $this->assertSame(['elevations' => 1, 'needs_review' => 0], $summary);
        $this->assertTrue($svc->hasStoredUpload(42));
        $this->assertSame('2.1250', $svc->buildForCutJob(42)['elevations'][0]['runs'][0]['sills'][0]['start']);
    }

    public function test_other_csv_layouts_store_nothing(): void
    {
        Storage::fake('local');
        $svc = new SillQuarterPointService(null, fn () => null);
        $content = "part_id,finish,dimension_in,qty\nT15310,C1,36,2\n";

        $this->assertNull($svc->storeUpload(UploadedFile::fake()->createWithContent('cut.csv', $content), 7));
        $this->assertFalse($svc->hasStoredUpload(7));
    }
}

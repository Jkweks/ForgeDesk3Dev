<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Tests\TestCase;

/**
 * parse-excel division resolution:
 *  1. first non-empty cell to the right of the "Division" label
 *  2. failing that, the first digit of the job-number cell
 */
class WorkOrderExcelDivisionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    /** Build a WO workbook from a grid of rows and return it as an upload. */
    private function workbook(array $rows): UploadedFile
    {
        $ss = new Spreadsheet;
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('WO');
        $sheet->fromArray($rows, null, 'A1');

        $path = tempnam(sys_get_temp_dir(), 'wo').'.xlsx';
        (new XlsxWriter($ss))->save($path);

        return new UploadedFile($path, 'wo.xlsx', null, null, true);
    }

    private function parse(UploadedFile $file): array
    {
        return $this->post('/api/v1/work-orders/parse-excel', ['file' => $file])
            ->assertOk()
            ->json();
    }

    public function test_division_reads_first_value_right_of_the_label(): void
    {
        $data = $this->parse($this->workbook([
            ['Division', null, '7'],          // gap cell is skipped
            ['Job Number', '24-1099'],
            [],
            ['Type', 'Elevation', 'Qty'],
            ['SF', 'A1', 2],
            ['CW', 'B2', 1],
        ]));

        $this->assertSame('7', $data['division']);
        $this->assertCount(2, $data['elevations']);
    }

    public function test_division_falls_back_to_first_digit_of_job_number_cell(): void
    {
        $data = $this->parse($this->workbook([
            ['Division', null, null],         // label present, nothing to the right
            ['Job Number:', 'J24-1099'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));

        $this->assertSame('2', $data['division']);
        $this->assertSame('J24-1099', $data['job_number']);
    }

    public function test_division_uses_job_number_when_no_division_label(): void
    {
        $data = $this->parse($this->workbook([
            ['Job No', '3-4471'],
            [],
            ['Type', 'Elevation'],
            ['CW', 'C1'],
        ]));

        $this->assertSame('3', $data['division']);
    }

    public function test_division_is_null_when_nothing_resolves(): void
    {
        $data = $this->parse($this->workbook([
            ['Project', 'Somewhere'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));

        $this->assertNull($data['division']);
    }

    public function test_job_name_is_read_from_a_job_name_or_project_label(): void
    {
        $byJobName = $this->parse($this->workbook([
            ['Job Number', '24-1099'],
            ['Job Name', 'Acme HQ Tower'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));
        $this->assertSame('Acme HQ Tower', $byJobName['job_name']);

        $byProject = $this->parse($this->workbook([
            ['Project:', 'Riverside Medical'],
            [],
            ['Type', 'Elevation'],
            ['CW', 'B1'],
        ]));
        $this->assertSame('Riverside Medical', $byProject['job_name']);

        $none = $this->parse($this->workbook([
            ['Job Number', '24-1099'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));
        $this->assertNull($none['job_name']);
    }

    public function test_project_manager_and_superintendent_are_read_from_their_labels(): void
    {
        $data = $this->parse($this->workbook([
            ['Job Number', '24-1099'],
            ['Project Manager', 'Dana Lee'],
            ['Superintendent:', 'Sam Park'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));

        $this->assertSame('Dana Lee', $data['project_manager']);
        $this->assertSame('Sam Park', $data['superintendent']);

        $none = $this->parse($this->workbook([
            ['Job Number', '24-1099'],
            [],
            ['Type', 'Elevation'],
            ['SF', 'A1'],
        ]));
        $this->assertNull($none['project_manager']);
        $this->assertNull($none['superintendent']);
    }
}

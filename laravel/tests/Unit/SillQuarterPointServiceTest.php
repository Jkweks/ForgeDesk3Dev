<?php

namespace Tests\Unit;

use App\Services\CutFlow\SillQuarterPointService;
use PHPUnit\Framework\TestCase;

class SillQuarterPointServiceTest extends TestCase
{
    private array $config = [
        'left_gap' => 0.125, 'right_gap' => 0.125, 'vertical_width' => 2, 'default_jamb_width' => 2,
        'comp_jamb_width' => 0, 'receptor_gap' => 0.375, 'fraction_denominator' => 64,
        'flag_tolerance' => 0.0625, 'knee_wall_tolerance' => 0.01,
        'part_widths' => ['T15361' => 4.5, 'T15362' => 4.5, 'T15356' => 2],
    ];

    private function row(string $pn, float $len, string $elev, ?int $bay, string $desc): array
    {
        return [$pn, 'C1 CLEAR : ANODIZED', (string) $len, '1', 'Harbor', 'WO3', $elev, $bay === null ? '' : ' '.$bay, '1', $desc, '0', '0', ''];
    }

    private function report(array $rows, ?\Closure $productWidth = null): array
    {
        $path = tempnam(sys_get_temp_dir(), 'sqp');
        $h = fopen($path, 'w');
        foreach ($rows as $r) {
            fputcsv($h, $r, escape: '');
        }
        fclose($h);
        $out = (new SillQuarterPointService($this->config, $productWidth ?? fn () => null))->build($path);
        unlink($path);

        return $out;
    }

    public function test_simple_elevation_positions_and_receptor_match(): void
    {
        $e = '4-E3';
        $r = $this->report([
            $this->row('T15362', 100, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 50, $e, 1, 'SILL'),
            $this->row('T15356', 100, $e, 1, 'FEMALE VERTICAL'),
            $this->row('T15356', 100, $e, 1, 'MALE VERTICAL'),
            $this->row('T15310', 40, $e, 2, 'SILL'),
            $this->row('T15361', 100, $e, 2, 'RIGHT JAMB'),
            $this->row('T15310', 49.0625, $e, 2, 'OUTSIDE STOP SILL'),
            $this->row('T15380', 101.25, $e, 1, 'SILL RECEPTORS CHANNEL'),
        ]);

        $this->assertSame([], $r['needs_review']);
        $run = $r['elevations'][0]['runs'][0];
        $this->assertCount(2, $run['sills']);
        $this->assertSame(['4.6250', '54.6250', '17.1250', '42.1250'], [$run['sills'][0]['start'], $run['sills'][0]['end'], $run['sills'][0]['q1'], $run['sills'][0]['q3']]);
        $this->assertSame(['56.6250', '96.6250', '66.6250', '86.6250'], [$run['sills'][1]['start'], $run['sills'][1]['end'], $run['sills'][1]['q1'], $run['sills'][1]['q3']]);
        $this->assertSame('101.2500', $run['layout_dec']);
        $this->assertSame('0.0000', $run['receptor']['diff_dec']);
        $this->assertFalse($run['receptor']['flagged']);
        $this->assertSame([], $r['assumptions']);
    }

    public function test_door_and_knee_wall_runs_start_at_zero(): void
    {
        $e = '12-E10';
        $r = $this->report([
            $this->row('T15356', 142.4375, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 35.125, $e, 1, 'SILL'),
            $this->row('T15310', 36, $e, 2, 'THRESHOLD'),
            $this->row('T15361', 142.625, $e, 2, 'LEFT JAMB {SIDELITE JAMB}'),
            $this->row('T15310', 35.125, $e, 3, 'SILL'),
            $this->row('T15356', 142.4375, $e, 3, 'FEMALE VERTICAL'),
            $this->row('T15310', 40.3125, $e, 4, 'SILL'),
            $this->row('T15356', 130.625, $e, 4, 'FEMALE VERTICAL'),
            $this->row('T15310', 40.3125, $e, 5, 'SILL'),
            $this->row('T15361', 130.625, $e, 5, 'RIGHT JAMB'),
            $this->row('T15380', 37.25, $e, 1, 'SILL RECEPTORS CHANNEL'),
            $this->row('T15380', 87.25, $e, 5, 'SILL RECEPTORS CHANNEL'),
            $this->row('T15380', 37.25, $e, 3, 'SILL RECEPTORS CHANNEL'),
        ]);

        $this->assertSame([], $r['needs_review']);
        $runs = $r['elevations'][0]['runs'];
        $this->assertCount(3, $runs);

        $this->assertSame('2.1250', $runs[0]['sills'][0]['start']);
        $this->assertSame('37.2500', $runs[0]['sills'][0]['end']);

        $b3 = $runs[1]['sills'][0];
        $this->assertSame(['0.0000', '35.1250', '8.7813', '26.3438'], [$b3['start'], $b3['end'], $b3['q1'], $b3['q3']]);
        $this->assertSame('8 25/32"', $b3['q1_frac']);
        $this->assertSame('37.2500', $runs[1]['layout_dec']); // terminating vertical 2 + right gap .125

        $b5 = $runs[2]['sills'][1];
        $this->assertSame('42.3125', $b5['start']);
        $this->assertSame('82.6250', $b5['end']);
        $this->assertSame('52.3906', $b5['q1']);
        $this->assertSame('52 25/64"', $b5['q1_frac']);
        $this->assertSame('87.2500', $runs[2]['layout_dec']);

        // Receptor-summary positions are rounded to 1/16" (8.78125 -> 8 13/16, 26.34375 -> 26 3/8)
        $marks = $runs[1]['on_receptor']['pieces'][0]['marks'];
        $this->assertSame(['8.8125', '26.3750'], array_column($marks, 'dec'));
        $this->assertSame(['8 13/16"', '26 3/8"'], array_column($marks, 'frac'));

        foreach ($runs as $run) {
            $this->assertSame('0.0000', $run['receptor']['diff_dec']);
        }
        $this->assertCount(2, $r['elevations'][0]['breaks']);
    }

    public function test_receptor_difference_over_sixteenth_is_flagged_and_split_pieces_use_gap(): void
    {
        $e = '1-E1';
        $rows = [
            $this->row('T15356', 100, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 297, $e, 1, 'SILL'),
            $this->row('T15356', 100, $e, 1, 'RIGHT JAMB'),
            $this->row('T15380', 288, $e, 1, 'SILL RECEPTORS CHANNEL'),
            $this->row('T15380', 11, $e, 1, 'SILL RECEPTORS CHANNEL'),
        ];
        // layout = 2.125 + 297 + 2 + .125 = 301.25... run end 299.125 + 2.125 = 301.25
        $r = $this->report($rows);
        $run = $r['elevations'][0]['runs'][0];
        $this->assertSame('301.2500', $run['layout_dec']);
        $this->assertSame('299.3750', $run['receptor']['receptor_dec']); // 288 + .375 + 11
        $this->assertSame('-1.8750', $run['receptor']['diff_dec']);
        $this->assertTrue($run['receptor']['flagged']);
        $this->assertCount(1, $r['receptor_flags']);
    }

    public function test_door_at_far_left_is_skipped_for_review(): void
    {
        $e = '9-E9';
        $r = $this->report([
            $this->row('T15356', 100, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 36, $e, 1, 'THRESHOLD'),
            $this->row('T15310', 40, $e, 2, 'SILL'),
            $this->row('T15356', 100, $e, 2, 'RIGHT JAMB'),
        ]);

        $this->assertSame([], $r['elevations']);
        $this->assertCount(1, $r['needs_review']);
        $this->assertStringContainsString('far left', $r['needs_review'][0]['reason']);
    }

    public function test_unknown_jamb_uses_default_and_is_listed_as_assumption_and_product_width_wins(): void
    {
        $e = '11-E9 UPPER';
        $rows = [
            $this->row('T15301', 100, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 36, $e, 1, 'SILL'),
            $this->row('T15301', 100, $e, 1, 'RIGHT JAMB'),
        ];

        $r = $this->report($rows);
        $this->assertSame('2.1250', $r['elevations'][0]['runs'][0]['sills'][0]['start']);
        $this->assertCount(2, array_filter($r['assumptions'], fn ($a) => str_contains($a, 'T15301')));

        $r = $this->report($rows, fn ($pn) => $pn === 'T15301' ? 3.0 : null);
        $this->assertSame('3.1250', $r['elevations'][0]['runs'][0]['sills'][0]['start']);
        $this->assertSame([], array_filter($r['assumptions'], fn ($a) => str_contains($a, 'T15301')));
    }

    public function test_upper_and_lower_are_separate_elevations(): void
    {
        $rows = [];
        foreach (['11-E9 UPPER', '11-E9 LOWER'] as $e) {
            $rows[] = $this->row('T15356', 100, $e, 1, 'LEFT JAMB');
            $rows[] = $this->row('T15310', 36, $e, 1, 'SILL');
            $rows[] = $this->row('T15356', 100, $e, 1, 'RIGHT JAMB');
        }

        $this->assertCount(2, $this->report($rows)['elevations']);
    }

    public function test_marks_are_placed_on_the_receptor_pieces(): void
    {
        $e = '1-E1';
        $r = $this->report([
            $this->row('T15356', 100, $e, 1, 'LEFT JAMB'),
            $this->row('T15310', 100, $e, 1, 'SILL'),
            $this->row('T15356', 100, $e, 1, 'RIGHT JAMB'),
            $this->row('T15380', 60, $e, 1, 'SILL RECEPTORS CHANNEL'),
            $this->row('T15380', 44, $e, 1, 'SILL RECEPTORS CHANNEL'),
        ]);
        // sill 2.125..102.125, q1 27.125, q3 77.125; pieces 60 | gap .375 | 44 (starts 60.375)
        $on = $r['elevations'][0]['runs'][0]['on_receptor'];
        $this->assertCount(2, $on['pieces']);
        $marks = fn ($i) => array_column($on['pieces'][$i]['marks'], 'dec', 'mark');
        $this->assertSame(['1/4' => '27.1250'], $marks(0));
        $this->assertSame(['3/4' => '16.7500'], $marks(1));
        $this->assertSame([], $on['off']);
    }

    public function test_elevations_are_sorted_naturally(): void
    {
        $rows = [];
        foreach (['11-E9', '4-E3', '5-E4'] as $e) {
            $rows[] = $this->row('T15356', 100, $e, 1, 'LEFT JAMB');
            $rows[] = $this->row('T15310', 36, $e, 1, 'SILL');
            $rows[] = $this->row('T15356', 100, $e, 1, 'RIGHT JAMB');
        }

        $this->assertSame(['4-E3', '5-E4', '11-E9'], array_column($this->report($rows)['elevations'], 'elevation'));
    }
}

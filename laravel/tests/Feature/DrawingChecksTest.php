<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DrawingElevation;
use App\Models\DrawingSet;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/** Automatic checks, confidence and the review workflow for imported drawings (ported from the PM Suite). */
class DrawingChecksTest extends TestCase
{
    use RefreshDatabase;

    private BusinessJob $job;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pm_worker.token' => 'tok', 'services.pm_worker.url' => 'http://worker.test', 'services.pm_worker.poll_ms' => 1]);
        Storage::fake('local');
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
        $this->job = BusinessJob::withoutGlobalScopes()->create(['job_number' => '1801', 'job_name' => 'Checks', 'status' => 'active']);
    }

    private function dim(float $v, array $rect): array
    {
        return ['v' => $v, 'rect' => $rect];
    }

    /** Three elevations: W1 closes on both axes, W2 width does not close, W3 is a merged multi-sheet run. */
    private function parsed(): array
    {
        $base = fn ($label, $page) => ['page' => $page, 'label' => $label, 'archRef' => 'A-1', 'system' => 'TUBELITE', 'finish' => 'CLEAR', 'thusReqd' => 1, 'oppReqd' => 0, 'totalReqd' => 3,
            'roW' => 100.0, 'fsW' => 98.0, 'roH' => 96.0, 'fsH' => 94.0, 'dloWidths' => [30, 30, 30], 'dloHeights' => [90], 'glassTags' => [], 'pageSize' => [1224, 792], 'titleRect' => null];
        $w1 = array_merge($base('W1', 1), ['balance' => ['w' => ['ok' => true, 'member' => 2.0, 'used' => 3, 'dropped' => 0, 'chains' => [['ok' => true]]], 'h' => ['ok' => true, 'member' => 2.0, 'used' => 1, 'dropped' => 0, 'chains' => [['ok' => true]]]],
            'dims' => ['fsW' => $this->dim(98, [100, 50, 140, 62]), 'roW' => $this->dim(100, [100, 30, 140, 42]), 'fsH' => $this->dim(94, [900, 100, 912, 140]), 'roH' => $this->dim(96, [940, 100, 952, 140]),
                'doW' => null, 'doH' => null, 'dloWidths' => [$this->dim(30, [100, 70, 130, 82]), $this->dim(30, [140, 70, 170, 82]), $this->dim(30, [180, 70, 210, 82])], 'dloHeights' => [$this->dim(90, [900, 200, 912, 240])]]]);
        $w2 = array_merge($base('W2', 2), ['roW' => null, 'fsW' => 99.3, 'balance' => ['w' => ['ok' => false, 'member' => null, 'used' => 0, 'dropped' => 0, 'chains' => [['ok' => false]]], 'h' => null],
            'dims' => ['fsW' => $this->dim(99.3, [100, 50, 140, 62]), 'roW' => null, 'fsH' => null, 'roH' => null, 'doW' => null, 'doH' => null,
                'dloWidths' => [$this->dim(30, [100, 70, 130, 82]), $this->dim(30, [140, 70, 170, 82]), $this->dim(30.0, [180, 70, 210, 82])], 'dloHeights' => []]]);
        $w3 = array_merge($base('W3', 3), ['multiSheet' => true, 'balance' => ['w' => null, 'h' => null], 'sheets' => [3, 4],
            'manualTakeoff' => ['reason' => 'mirror', 'tags' => ['IG-3']], 'dims' => ['dloWidths' => [$this->dim(33.37, [10, 20, 50, 33])]],
            'sheetDims' => [['page' => 3, 'pageSize' => [1224, 792], 'dims' => ['dloWidths' => [$this->dim(33.37, [10, 20, 50, 33])]], 'titleRect' => null]]]);

        return ['ok' => true, 'pageCount' => 6, 'skippedPages' => 3, 'warnings' => [], 'setFinish' => null, 'skipSummary' => '3 pages were not elevations: plans 3', 'glazingSchedule' => [['tag' => 'IG-1', 'makeup' => ["1/4\" clear", "1/2\" air"]], ['tag' => 'IG-1T', 'makeup' => []]], 'elevations' => [$w1, $w2, $w3]];
    }

    private function fakeRead(?array $result = null, ?callable $balance = null): void
    {
        $result ??= $this->parsed();
        Http::fake([
            'worker.test/drawing/jobs' => Http::response(['id' => 'job-1'], 202),
            'worker.test/drawing/jobs/*' => fn ($request) => Http::response(['state' => 'done', 'done' => 6, 'count' => 6, 'note' => 'W3', 'busy' => 0, 'error' => null]
                + (str_contains($request->url(), 'result=1') ? ['result' => $result] : [])),
            'worker.test/drawing/balance' => fn ($request) => Http::response(['balance' => $balance ? $balance($request->data()) : ['ok' => true, 'member' => 2.0, 'used' => 3, 'dropped' => 0, 'chains' => [['ok' => true]]]]),
        ]);
    }

    private function import(string $content = '%PDF-1 set'): DrawingSet
    {
        $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets", ['file' => UploadedFile::fake()->createWithContent('shop.pdf', $content)], ['Accept' => 'application/json'])->assertStatus(202);

        return DrawingSet::orderByDesc('id')->firstOrFail();
    }

    private function elev(DrawingSet $set, string $label): array
    {
        return collect($this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}")->assertOk()->json('elevations'))->firstWhere('label', $label);
    }

    private function url(DrawingSet $set, string $suffix = ''): string
    {
        return "/api/v1/pm/jobs/{$this->job->id}/drawing-sets/{$set->id}{$suffix}";
    }

    public function test_import_stores_the_frame_checks_and_a_merged_run_is_never_judged(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $this->assertSame(6, $set->fresh()->progress_count);
        $this->assertSame(6, $set->fresh()->progress_done);

        $state = fn ($e) => collect($e['axis_checks'])->pluck('state', 'axis')->all();
        $this->assertEquals(['w' => 'closes', 'h' => 'closes'], $state($this->elev($set, 'W1')));
        $this->assertEquals(['w' => 'flagged', 'h' => 'unverifiable'], $state($this->elev($set, 'W2')));
        $this->assertEquals(['w' => 'unverifiable', 'h' => 'unverifiable'], $state($this->elev($set, 'W3')));
        $this->assertTrue($this->elev($set, 'W3')['multi_sheet']);
        $this->assertSame('mirror: IG-3', $this->elev($set, 'W3')['manual_takeoff']);
        $this->assertSame(['w'], $this->elev($set, 'W2')['unresolved_axes']);
    }

    public function test_the_sets_glazing_schedule_and_skip_summary_are_kept(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $show = $this->getJson($this->url($set))->assertOk();
        $this->assertSame('3 pages were not elevations: plans 3', $show->json('skip_summary'));
        $this->assertSame(['IG-1', 'IG-1T'], array_column($show->json('glazing_tags'), 'tag'));
        $this->assertSame("1/4\" clear\n1/2\" air", $show->json('glazing_tags.0.makeup'));
        $this->assertSame(2, count($this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-sets")->json('0.glazing_tags')));

        $this->postJson($this->url($set, '/reimport'))->assertStatus(202);      // a re-read replaces them rather than duplicating
        $this->assertSame(2, \App\Models\DrawingGlazingTag::count());
    }

    public function test_confidence_reflects_the_checks_and_explains_itself(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $w1 = $this->elev($set, 'W1');
        $w2 = $this->elev($set, 'W2');
        $w3 = $this->elev($set, 'W3');

        $this->assertSame('high', $w1['confidence']['level']);
        $this->assertSame('low', $w2['confidence']['level']);
        $this->assertContains('Width: the frame does not close', $w2['confidence']['reasons']);
        $this->assertContains('R.O. width not read', $w2['confidence']['reasons']);
        $this->assertLessThan($w1['confidence']['score'], $w3['confidence']['score']);
        $this->assertContains('Manual takeoff: mirror: IG-3', $w3['confidence']['reasons']);

        // an odd reading (not on a 1/16" grid) is called out on the value and lowers the elevation
        $odd = collect($w3['marks'])->firstWhere('kind', 'dlo_w');
        $this->assertSame('low', $odd['confidence']['level']);   // nothing to check it against, and not on the grid
        $this->assertStringContainsString('1/16', implode(' ', $odd['confidence']['reasons']));

        // a person confirming the values makes them certain and lifts the elevation
        $ids = collect($w2['marks'])->pluck('id')->all();
        $this->postJson($this->url($set, '/marks'), ['ids' => $ids, 'action' => 'confirm'])->assertOk();
        $after = $this->elev($set, 'W2');
        $this->assertGreaterThan($w2['confidence']['score'], $after['confidence']['score']);
        $this->assertSame(['confirmed'], array_unique(array_column($after['marks'], 'status')));
        $this->assertSame(100, $after['marks'][0]['confidence']['score']);
    }

    public function test_a_frame_that_does_not_close_blocks_approval_until_it_is_dealt_with(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $w2 = $this->elev($set, 'W2');
        $review = fn ($id, $action = 'approve') => $this->postJson($this->url($set, "/elevations/{$id}/review"), ['action' => $action]);

        $review($w2['id'])->assertStatus(422)->assertJsonPath('unresolved_axes', ['w']);
        $this->assertSame('pending', $this->elev($set, 'W2')['review_status']);

        // accepting needs a reason; keeping it unverified does not
        $resolve = fn ($mode, $reason = null) => $this->postJson($this->url($set, "/elevations/{$w2['id']}/axes/w/resolve"), ['mode' => $mode] + ($reason ? ['reason' => $reason] : []));
        $resolve('accepted')->assertStatus(422);
        $this->assertSame('accepted', collect($resolve('accepted', 'Matches the architect markup')->assertOk()->json('axis_checks'))->firstWhere('axis', 'w')['state']);
        $review($w2['id'])->assertOk()->assertJsonPath('review_status', 'approved');

        // reopening and clearing the decision flags it again
        $review($w2['id'], 'reopen')->assertOk()->assertJsonPath('review_status', 'pending');
        $this->deleteJson($this->url($set, "/elevations/{$w2['id']}/axes/w/resolve"))->assertOk()->assertJsonPath('unresolved_axes', ['w']);
        $resolve('unverified')->assertOk();
        $review($w2['id'])->assertOk();

        // a frame that closes has nothing to resolve
        $w1 = $this->elev($set, 'W1');
        $this->postJson($this->url($set, "/elevations/{$w1['id']}/axes/w/resolve"), ['mode' => 'unverified'])->assertStatus(422);
        $review($w1['id'], 'exclude')->assertOk()->assertJsonPath('review_status', 'excluded');
    }

    public function test_correcting_a_value_rechecks_the_frame_and_drops_a_stale_decision(): void
    {
        // the worker says the frame closes only when the third opening is 31.3 (the person's correction)
        $this->fakeRead(null, function (array $p) {
            $third = $p['dlos'][2]['v'] ?? 0;

            return ['ok' => abs($third - 31.3) < 0.001, 'member' => abs($third - 31.3) < 0.001 ? 2.0 : null, 'used' => 3, 'dropped' => 0, 'chains' => [['ok' => true]]];
        });
        $set = $this->import();
        $w2 = $this->elev($set, 'W2');
        $this->postJson($this->url($set, "/elevations/{$w2['id']}/axes/w/resolve"), ['mode' => 'unverified'])->assertOk();

        $third = collect($w2['marks'])->where('kind', 'dlo_w')->sortBy('idx')->values()[2];
        $this->postJson($this->url($set, '/marks'), ['ids' => [$third['id']], 'action' => 'correct', 'value' => 31.3])->assertOk();

        $e = $this->elev($set, 'W2');
        $w = collect($e['axis_checks'])->firstWhere('axis', 'w');
        $this->assertSame('corrected', $w['state']);                  // closes now, because a person fixed a reading
        $this->assertNull($w['resolution']);                          // the earlier "unverified" no longer applies
        $this->assertEquals(2.0, $w['member']);
        $this->assertSame([], $e['unresolved_axes']);

        // breaking it again flags it again
        $this->postJson($this->url($set, '/marks'), ['ids' => [$third['id']], 'action' => 'correct', 'value' => 30.5])->assertOk();
        $this->assertSame('flagged', collect($this->elev($set, 'W2')['axis_checks'])->firstWhere('axis', 'w')['state']);
    }

    public function test_a_worker_outage_does_not_lose_a_confirmation(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $w1 = $this->elev($set, 'W1');
        Http::fake(['worker.test/drawing/balance' => Http::response('down', 503)]);

        $this->postJson($this->url($set, '/marks'), ['ids' => [$w1['marks'][0]['id']], 'action' => 'confirm'])->assertOk();
        $this->assertSame('confirmed', collect($this->elev($set, 'W1')['marks'])->firstWhere('id', $w1['marks'][0]['id'])['status']);
    }

    public function test_job_review_list_puts_attention_first_and_bulk_approve_skips_the_risky_ones(): void
    {
        $this->fakeRead();
        $this->import();

        $res = $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations")->assertOk();
        $this->assertSame(['W2', 'W3', 'W1'], array_column($res->json('elevations'), 'label'));   // does-not-close first, then lowest confidence
        $this->assertSame(['total' => 3, 'pending' => 3, 'approved' => 0, 'excluded' => 0, 'needs_attention' => 1], array_intersect_key($res->json('summary'), array_flip(['total', 'pending', 'approved', 'excluded', 'needs_attention'])));
        $this->assertSame(1, $res->json('summary.high'));

        $r = $this->postJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations/bulk-review", ['min_level' => 'high'])->assertOk();
        $this->assertSame(1, $r->json('approved'));
        $this->assertEqualsCanonicalizing(['W2', 'W3'], array_column($r->json('skipped'), 'label'));
        $this->assertStringContainsString('does not close', collect($r->json('skipped'))->firstWhere('label', 'W2')['why']);

        $after = $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations")->json('summary');
        $this->assertSame([1, 2], [$after['approved'], $after['pending']]);
    }

    public function test_a_re_read_keeps_what_people_already_decided(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $w1 = $this->elev($set, 'W1');
        $w2 = $this->elev($set, 'W2');
        $this->postJson($this->url($set, '/marks'), ['ids' => [$w1['marks'][0]['id']], 'action' => 'confirm'])->assertOk();
        $this->postJson($this->url($set, "/elevations/{$w1['id']}/review"), ['action' => 'approve'])->assertOk();
        $this->postJson($this->url($set, "/elevations/{$w2['id']}/axes/w/resolve"), ['mode' => 'accepted', 'reason' => 'Per RFI 12'])->assertOk();
        $kind = $w1['marks'][0]['kind'];

        $this->postJson($this->url($set, '/reimport'))->assertStatus(202);

        $this->assertSame('approved', $this->elev($set, 'W1')['review_status']);
        $this->assertSame('confirmed', collect($this->elev($set, 'W1')['marks'])->firstWhere('kind', $kind)['status']);
        $w2b = $this->elev($set, 'W2');
        $this->assertSame('accepted', collect($w2b['axis_checks'])->firstWhere('axis', 'w')['state']);
        $this->assertSame('Per RFI 12', collect($w2b['axis_checks'])->firstWhere('axis', 'w')['resolution_reason']);

        // but a decision about a reading the parser no longer makes is dropped
        $changed = $this->parsed();
        $changed['elevations'][0]['dims']['fsW']['v'] = 97.5;
        $this->fakeRead($changed);
        $this->postJson($this->url($set, '/reimport'))->assertStatus(202);
        $fsW = collect($this->elev($set, 'W1')['marks'])->firstWhere('kind', 'fs_w');
        $this->assertSame('suggested', $fsW['status']);
    }

    public function test_a_full_package_can_be_uploaded_at_once(): void
    {
        $this->fakeRead();
        $files = [UploadedFile::fake()->createWithContent('a.pdf', '%PDF-a'), UploadedFile::fake()->createWithContent('b.pdf', '%PDF-b'), UploadedFile::fake()->createWithContent('a-copy.pdf', '%PDF-a')];

        $res = $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/bulk", ['files' => $files], ['Accept' => 'application/json'])->assertStatus(202);
        $this->assertCount(3, $res->json());
        $this->assertSame([false, false, true], array_column($res->json(), 'already_uploaded'));   // identical content is not read twice
        $this->assertSame(2, DrawingSet::count());
        $this->assertSame(['done', 'done'], DrawingSet::orderBy('id')->pluck('status')->all());

        // one review list across every set
        $this->assertSame(6, $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations")->json('summary.total'));

        $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/bulk", ['files' => [UploadedFile::fake()->createWithContent('x.docx', 'x')]], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post("/api/v1/pm/jobs/{$this->job->id}/drawing-sets/bulk", [], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_scope_and_permissions(): void
    {
        $this->fakeRead();
        $set = $this->import();
        $w2 = $this->elev($set, 'W2');

        Sanctum::actingAs(User::factory()->create(['role' => 'project_manager', 'is_active' => true]), ['*']);   // not this job's PM
        $this->getJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations")->assertNotFound();
        $this->postJson($this->url($set, "/elevations/{$w2['id']}/review"), ['action' => 'exclude'])->assertNotFound();
        $this->postJson("/api/v1/pm/jobs/{$this->job->id}/drawing-elevations/bulk-review", ['min_level' => 'high'])->assertNotFound();
    }
}

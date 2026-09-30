<?php

namespace App\Livewire\CutFlow;

use App\Models\CutFlow\CutFlowSetting;
use App\Models\CutFlow\CutJob;
use App\Models\CutFlow\CutLogEntry;
use App\Models\CutFlow\Part;
use App\Models\CutFlow\StickItem;
use App\Models\CutFlow\StickSession;
use App\Models\FdUser;
use App\Services\CutFlow\CutPlanner;
use App\Services\CutFlow\TigerBridgeClient;
use App\Support\Dimension;
use App\Support\IpAllowlist;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.cutflow.layout')]
class Dashboard extends Component
{
    // null | 'manual' | 'stick' | 'login' | 'jobs'
    public ?string $modal = null;

    public string $keypadValue = '';

    public ?int $activeStickId = null;

    // which part_id+finish list the sidebar/stick-entry flow is scoped to
    public ?string $activeProfileName = null;

    public ?string $activeProfileFinish = null;

    // ids of the cut_jobs currently selected — the pool CutPlanner optimizes
    // across is the union of all parts belonging to these jobs
    public array $activeJobIds = [];

    // filters the job list inside the job-picker modal — shops running
    // 20-40 active jobs at once need to search rather than scan a wall of
    // checkboxes
    public string $jobSearch = '';

    // hides "Done" rows from the active profile's cut-list table — a
    // finished job's list is mostly completed pieces, which buries the
    // handful still pending under noise.
    public bool $showCompleted = true;

    // idle | positioning | printing — just tracks whether a move/print
    // request is in flight, to disable buttons and block concurrent
    // actions. The red/orange/yellow/green cut state and the "awaiting cut
    // sensor" state are both computed (see currentItemState()/
    // awaitingSensor()), not stored here.
    public string $tigerStatus = 'idle';

    // Last known TigerStop connection/position, from tiger-bridge's GET
    // /status — see refreshTigerBridgeStatus(). The amp has no "read
    // current position" query, so lastPosition is the last inches value
    // tiger-bridge successfully moved it to (see TigerBridgeClient).
    public bool $tigerConnected = false;

    public ?float $tigerPosition = null;

    public ?string $tigerPositionAt = null;

    // Unix timestamp of the last manual move/cut action (see
    // markBridgeActivity()) — drives the fast-vs-slow status poll interval
    // in bridgePollIntervalMs() below.
    public ?int $lastBridgeActivityAt = null;

    public string $lastError = '';

    public string $loginError = '';

    // --- operator identity ------------------------------------------------
    // Multiple operators can be signed in at once (second-person help on
    // high-volume runs). $crew is the roster of everyone currently signed in
    // on this tablet; each entry is
    // ['key' => <session-local uuid>, 'operator_id' => ?int, 'name' => string].
    // operator_id is a ForgeDesk FdUser id (fab_pin-verified) — there is no
    // "Unknown"/bypass entry anymore (see openManual()): with an empty crew
    // the dashboard restricts to manual entry only, so there's nothing left
    // for a bypass to unlock.
    // $activeCrewKey picks who gets credited on the *next* action — tapping
    // a crew chip switches it without re-entering a PIN, so two people can
    // trade off the "next cut" button without signing each other out.

    public array $crew = [];

    public ?string $activeCrewKey = null;

    public function mount(Request $request, CutPlanner $planner, TigerBridgeClient $bridge): void
    {
        $this->refreshTigerBridgeStatus($bridge);

        $this->crew = session('cutflow_crew', []);
        $this->activeCrewKey = session('cutflow_active_crew_key');

        if (! empty($this->crew) && ! $this->activeOperator()) {
            $this->activeCrewKey = $this->crew[0]['key'];
            $this->persistCrew();
        }

        $this->activeJobIds = session('cutflow_active_job_ids', []);
        $this->showCompleted = session('cutflow_show_completed', true);

        if ($request->filled('job')) {
            $jobId = (int) $request->query('job');

            if (CutJob::whereKey($jobId)->exists() && ! in_array($jobId, $this->activeJobIds, true)) {
                $this->activeJobIds[] = $jobId;
            }
        }

        // drop any job ids that no longer exist
        $this->activeJobIds = CutJob::whereIn('id', $this->activeJobIds)->pluck('id')->all();

        if (empty($this->activeJobIds)) {
            $first = CutJob::orderBy('id')->first();
            $this->activeJobIds = $first ? [$first->id] : [];
        }

        $this->persistJobSelection();

        $active = StickSession::where('status', 'active')->whereNull('cancelled_at')->latest()->first();
        $this->activeStickId = $active?->id;

        if ($active) {
            $this->activeProfileName = $active->part_name;
            $this->activeProfileFinish = $active->finish;

            return;
        }

        $firstProfile = $planner->activeProfiles($this->activeJobIds)->first();
        $this->activeProfileName = $firstProfile?->name;
        $this->activeProfileFinish = $firstProfile?->finish;
    }

    protected function signedIn(): bool
    {
        return ! empty($this->crew);
    }

    protected function persistJobSelection(): void
    {
        session(['cutflow_active_job_ids' => $this->activeJobIds]);
    }

    public function toggleJob(int $jobId): void
    {
        if (! $this->signedIn()) {
            return;
        }

        if (in_array($jobId, $this->activeJobIds, true)) {
            $this->activeJobIds = array_values(array_diff($this->activeJobIds, [$jobId]));
        } else {
            $this->activeJobIds[] = $jobId;
        }

        $this->persistJobSelection();
    }

    /**
     * The job picker: a checkbox wall doesn't scale past a handful of jobs,
     * so this is a searchable modal instead of the old always-visible pill
     * bar. Query is filtered in filteredJobs() below.
     */
    public function openJobsModal(): void
    {
        if (! $this->signedIn()) {
            return;
        }

        $this->modal = 'jobs';
        $this->jobSearch = '';
    }

    protected function filteredJobs()
    {
        return CutJob::when($this->jobSearch !== '', function ($query) {
            $query->whereRaw('LOWER(name) LIKE ?', ['%'.strtolower($this->jobSearch).'%']);
        })->orderBy('name')->get();
    }

    /**
     * Adds every job currently matching the search box to the selection
     * (union, not replace) — searching "kitchen" then "Select visible"
     * twice with different search terms should keep stacking jobs, not
     * wipe out an earlier pick.
     */
    public function selectVisibleJobs(): void
    {
        $ids = $this->filteredJobs()->pluck('id')->all();
        $this->activeJobIds = array_values(array_unique([...$this->activeJobIds, ...$ids]));
        $this->persistJobSelection();
    }

    public function clearJobSelection(): void
    {
        $this->activeJobIds = [];
        $this->persistJobSelection();
    }

    public function toggleShowCompleted(): void
    {
        $this->showCompleted = ! $this->showCompleted;
        session(['cutflow_show_completed' => $this->showCompleted]);
    }

    public function switchProfile(string $name, ?string $finish): void
    {
        $finish = $finish ?: null;

        if ($this->activeProfileName === $name && $this->activeProfileFinish === $finish) {
            $this->activeProfileName = null;
            $this->activeProfileFinish = null;

            return;
        }

        $this->activeProfileName = $name;
        $this->activeProfileFinish = $finish;
    }

    // --- operator crew (multiple signed-in operators) ----------------------

    protected function persistCrew(): void
    {
        session([
            'cutflow_crew' => $this->crew,
            'cutflow_active_crew_key' => $this->activeCrewKey,
        ]);
    }

    /**
     * The crew entry currently credited for the next action, or null if
     * nobody's signed in.
     */
    protected function activeOperator(): ?array
    {
        foreach ($this->crew as $entry) {
            if ($entry['key'] === $this->activeCrewKey) {
                return $entry;
            }
        }

        return null;
    }

    protected function activeOperatorId(): ?int
    {
        return $this->activeOperator()['operator_id'] ?? null;
    }

    protected function activeOperatorName(): string
    {
        return $this->activeOperator()['name'] ?? 'Unknown';
    }

    /**
     * PIN lookup against ForgeDesk's own fabrication users — mirrors
     * ShopFloorController::pinLogin() exactly (same hash-check-every-active-
     * user approach), so operator identity here is the same identity used
     * everywhere else on the shop floor rather than a separate local table.
     */
    protected function findUserByPin(string $pin): ?FdUser
    {
        return FdUser::where('active', true)
            ->whereNotNull('fab_pin')
            ->get()
            ->first(fn (FdUser $user) => Hash::check($pin, $user->fab_pin));
    }

    /**
     * Opens the login modal to add another person to the crew — doesn't
     * touch who's currently signed in, so this is also how a second person
     * joins mid-run without kicking the first one off.
     */
    public function openLogin(): void
    {
        $this->modal = 'login';
        $this->keypadValue = '';
        $this->loginError = '';
    }

    /**
     * Switch who the next action is credited to. Both people stay signed
     * in — this is the "trade off the Next Cut button" path.
     */
    public function setActiveOperator(string $key): void
    {
        if (collect($this->crew)->contains('key', $key)) {
            $this->activeCrewKey = $key;
            $this->persistCrew();
        }
    }

    /**
     * Removes one person from the crew (their shift/help is done). If
     * they were the active operator, hand off to whoever's left.
     */
    public function signOutOperator(string $key): void
    {
        $this->crew = array_values(array_filter($this->crew, fn ($e) => $e['key'] !== $key));

        if ($this->activeCrewKey === $key) {
            $this->activeCrewKey = $this->crew[0]['key'] ?? null;
        }

        $this->persistCrew();
    }

    // --- modal open/close -------------------------------------------------

    public function openManual(): void
    {
        $this->modal = 'manual';
        $this->keypadValue = '';
    }

    public function openStick(): void
    {
        if (! $this->signedIn()) {
            return;
        }

        $this->modal = 'stick';
        $this->keypadValue = '';
    }

    public function closeModal(): void
    {
        $this->modal = null;
        $this->keypadValue = '';
    }

    // --- on-screen keypad (tablet, no physical keyboard) -------------------

    // Only these denominators are real fractions on a tape measure/rule —
    // anything else (1/17, 1/23, ...) is almost certainly a typo, so it's
    // rejected at the keystroke rather than let through and misread later.
    protected const ALLOWED_DENOMINATORS = ['2', '4', '8', '16', '32'];

    public function tapDigit(string $digit): void
    {
        if ($this->modal === 'login') {
            if (strlen($this->keypadValue) < 8) {
                $this->keypadValue .= $digit;
            }

            return;
        }

        $token = $this->currentToken();

        if (str_contains($token, '/')) {
            $denominator = substr($token, strpos($token, '/') + 1).$digit;

            if (! $this->isValidDenominatorPrefix($denominator)) {
                return;
            }
        }

        $this->keypadValue .= $digit;
    }

    public function tapDecimal(): void
    {
        $token = $this->currentToken();

        if (! str_contains($token, '.') && ! str_contains($token, '/')) {
            $this->keypadValue .= '.';
        }
    }

    public function tapSlash(): void
    {
        $token = $this->currentToken();

        if ($token === '' || str_contains($token, '/') || str_contains($token, '.')) {
            return;
        }

        $this->keypadValue .= '/';
    }

    protected function isValidDenominatorPrefix(string $partial): bool
    {
        foreach (self::ALLOWED_DENOMINATORS as $denominator) {
            if (str_starts_with($denominator, $partial)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Lets a physical/laptop keyboard drive the modal the same way the
     * on-screen keypad does, for testing off the shop-floor tablet.
     * Enter, Escape, Backspace and Space are bound directly in the view
     * (so their existing action methods keep their normal DI); this just
     * covers digits and the punctuation keys.
     */
    public function handleKeydown(string $key): void
    {
        if (! $this->modal) {
            return;
        }

        if (ctype_digit($key) && strlen($key) === 1) {
            $this->tapDigit($key);

            return;
        }

        match ($key) {
            '.' => $this->tapDecimal(),
            '/' => $this->tapSlash(),
            default => null,
        };
    }

    protected function currentToken(): string
    {
        $tokens = preg_split('/\s+/', trim($this->keypadValue));

        return end($tokens) ?: '';
    }

    public function tapSpace(): void
    {
        if ($this->keypadValue !== '' && ! str_ends_with($this->keypadValue, ' ')) {
            $this->keypadValue .= ' ';
        }
    }

    public function tapFraction(string $fraction): void
    {
        $sep = ($this->keypadValue !== '' && ! str_ends_with($this->keypadValue, ' ')) ? ' ' : '';
        $this->keypadValue .= $sep.$fraction;
    }

    /**
     * Quick-length chips on the New Stick modal (standard stock lengths) —
     * unlike tapFraction() these replace the whole entry rather than append
     * to it, since a stick length is one number, not a whole+fraction pair
     * being built up token by token.
     */
    public function setKeypadValue(string $value): void
    {
        $this->keypadValue = $value;
    }

    public function backspace(): void
    {
        $this->keypadValue = substr($this->keypadValue, 0, -1);
    }

    public function clearDisplay(): void
    {
        $this->keypadValue = '';
    }

    // --- confirm: login pin, manual move, or new stick plan -----------------

    /**
     * /cut-station itself stays unauthenticated on purpose (kiosk route —
     * anyone on the network can view it and sign in with a crew PIN, per
     * signedIn()/findUserByPin()). This is a separate, narrower gate: only
     * the shop-floor tablet's IP (config('cutflow.tablet_allowed_ips')) may
     * trigger the handful of actions below that actually move the saw or
     * fire the printer, so a laptop elsewhere on the LAN can't drive
     * hardware even if someone signs in there. Left unconfigured, this is a
     * no-op (every device can command the saw) — logged so it's visible in
     * ops that the allowlist isn't set yet.
     */
    protected function authorizedTabletRequest(Request $request): bool
    {
        $allowed = IpAllowlist::allows($request->ip(), CutFlowSetting::current()->tabletAllowedIps());

        if (! $allowed) {
            Log::warning('[cutflow] rejected saw/printer command from disallowed IP', [
                'ip' => $request->ip(),
            ]);
        }

        return $allowed;
    }

    public function confirmModal(CutPlanner $planner, TigerBridgeClient $bridge, Request $request): void
    {
        $raw = trim($this->keypadValue);

        if ($raw === '') {
            return;
        }

        if ($this->modal === 'login') {
            $user = $this->findUserByPin($raw);

            if (! $user) {
                $this->loginError = 'PIN not recognized.';
                $this->keypadValue = '';

                return;
            }

            $existing = collect($this->crew)->firstWhere('operator_id', $user->id);

            if ($existing) {
                $this->activeCrewKey = $existing['key'];
            } else {
                $entry = ['key' => (string) Str::uuid(), 'operator_id' => $user->id, 'name' => $user->name];
                $this->crew[] = $entry;
                $this->activeCrewKey = $entry['key'];
            }

            $this->persistCrew();
            $this->loginError = '';
            $this->modal = null;
            $this->keypadValue = '';

            return;
        }

        if ($this->modal === 'manual') {
            if (! $this->authorizedTabletRequest($request)) {
                $this->lastError = 'This action is only available from the cut station tablet.';
                $this->modal = null;
                $this->keypadValue = '';

                return;
            }

            $inches = Dimension::parse($raw);

            $entry = CutLogEntry::create([
                'uuid' => (string) Str::uuid(),
                'part_id' => null,
                'part_name' => 'Manual entry',
                'operator_id' => $this->activeOperatorId(),
                'operator_name' => $this->activeOperatorName(),
                'dimension_inches' => $inches,
                'stick_length_label' => null,
                'type' => 'manual',
            ]);

            $result = $bridge->move($inches);
            $this->lastError = $result['ok'] ? '' : ($result['error'] ?? 'Move failed');
            $this->markBridgeActivity();

            $qrUrl = route('cutflow.cuts.show', $entry->uuid);

            $this->announceCutStarted([
                'size' => Dimension::toFraction($inches),
                'qrSvg' => $this->renderCutQrSvg($qrUrl),
            ]);

            // manual stickers carry only size + cut record data, no job/part fields
            $bridge->printLabel([
                'size' => Dimension::toFraction($inches),
                'operator' => $entry->operator_name,
                'timestamp' => $entry->cut_at_local?->format('n/j/y g:i A'),
                'uuid' => $entry->uuid,
                'qrUrl' => $qrUrl,
            ]);

            $this->modal = null;
            $this->keypadValue = '';

            return;
        }

        if ($this->modal === 'stick') {
            if (! $this->signedIn() || ! $this->activeProfileName) {
                return;
            }

            $session = $planner->buildStick($raw, $this->activeProfileName, $this->activeProfileFinish, $this->activeJobIds);
            $this->activeStickId = $session->id;
            $this->modal = null;
            $this->keypadValue = '';
        }
    }

    // --- active stick actions ----------------------------------------------

    protected function guardTabletAction(Request $request): bool
    {
        if (! $this->signedIn()) {
            return false;
        }

        if (! $this->authorizedTabletRequest($request)) {
            $this->lastError = 'This action is only available from the cut station tablet.';

            return false;
        }

        return true;
    }

    /**
     * Whether tiger-bridge's last confirmed position matches this item's
     * dimension — the saw-position half of the red/orange/yellow/green
     * state (see currentItemState()). A small epsilon absorbs float
     * round-tripping through JSON, not real-world slop.
     */
    protected function positionMatches(StickItem $item): bool
    {
        return $this->tigerPosition !== null
            && abs($this->tigerPosition - (float) $item->dimension_inches) < 0.0005;
    }

    /**
     * red: not in position, label not printed — needs both.
     * orange: label already printed but saw isn't in position yet —
     *   shouldn't happen with the tablet driving move+print together for a
     *   fresh piece, but reachable if move and print are triggered out of
     *   order (or a move fails after a print succeeded), so it gets its own
     *   state rather than being silently folded into "red".
     * yellow: in position, not yet printed — e.g. the same dimension cut
     *   twice in a row, so the saw's already sitting where this piece needs
     *   it before its label exists.
     * green: both done — ready for the operator to actually make the cut.
     */
    protected function currentItemState(StickItem $item): string
    {
        $positioned = $this->positionMatches($item);
        $printed = $item->isLabelPrinted();

        return match (true) {
            ! $positioned && ! $printed => 'red',
            ! $positioned && $printed => 'orange',
            $positioned && ! $printed => 'yellow',
            default => 'green',
        };
    }

    protected function doMove(StickItem $item, TigerBridgeClient $bridge): bool
    {
        $this->tigerStatus = 'positioning';
        $this->markBridgeActivity();

        $result = $bridge->move((float) $item->dimension_inches);

        if (! $result['ok']) {
            $this->tigerStatus = 'idle';
            $this->lastError = $result['error'] ?? 'Move failed';
            $this->refreshTigerBridgeStatus($bridge);

            return false;
        }

        // Prefer the position tiger-bridge actually confirmed over our own
        // guess — it's what GET /status will keep reporting on every
        // wire:poll refresh, so using anything else here just means the
        // badge flips back within 10s when the poll disagrees with us.
        $data = $result['data'] ?? [];
        $this->tigerPosition = isset($data['lastPosition']) ? (float) $data['lastPosition'] : (float) $item->dimension_inches;
        $this->tigerPositionAt = $data['lastPositionAt'] ?? now()->toIso8601String();
        $this->tigerConnected = true;
        $this->tigerStatus = 'idle';
        $this->lastError = '';

        return true;
    }

    /**
     * Prints straight off the planned StickItem/Part — no CutLogEntry yet,
     * since a label can now be printed before the cut is confirmed (yellow
     * state) or even before the saw is in position (red's combined action).
     * The QR carries a uuid reserved via reservePendingUuid(), which
     * finalizeCutAndAdvance() reuses as the eventual CutLogEntry's uuid, so
     * an early-printed sticker's QR resolves once the cut is confirmed.
     */
    protected function doPrint(StickItem $item, TigerBridgeClient $bridge): bool
    {
        $this->tigerStatus = 'printing';
        $this->markBridgeActivity();

        $part = $item->part;
        $uuid = $item->reservePendingUuid();
        $qrUrl = route('cutflow.cuts.show', $uuid);

        $this->announceCutStarted([
            'job' => $part->cutJob?->name,
            'part' => $part->profile_label,
            'partUse' => $part->description,
            'elevation' => $part->phase,
            'size' => Dimension::toFraction((float) $item->dimension_inches),
            'qrSvg' => $this->renderCutQrSvg($qrUrl),
        ]);

        $result = $bridge->printLabel([
            'job' => $part->cutJob?->name,
            'part' => $part->finish ? "{$part->name} · {$part->finish}" : $part->name,
            'partUse' => $part->description,
            'elevation' => $part->phase,
            'size' => Dimension::toFraction((float) $item->dimension_inches),
            'uuid' => $uuid,
            'qrUrl' => $qrUrl,
        ]);

        $this->tigerStatus = 'idle';

        if (! $result['ok']) {
            $this->lastError = $result['error'] ?? 'Print failed';

            return false;
        }

        $item->update(['label_printed_at' => now()]);
        $this->lastError = '';

        return true;
    }

    protected function moveAndPrintItem(StickItem $item, TigerBridgeClient $bridge): void
    {
        if ($this->doMove($item, $bridge)) {
            $this->doPrint($item, $bridge);
        }
    }

    /** Red state's combined action. */
    public function moveAndPrintCurrent(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->guardTabletAction($request) || $this->tigerStatus !== 'idle') {
            return;
        }

        if ($item = $this->currentItem()) {
            $this->moveAndPrintItem($item, $bridge);
        }
    }

    /** Orange state's action — label's already printed, just needs the move. */
    public function moveCurrentToPosition(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->guardTabletAction($request) || $this->tigerStatus !== 'idle') {
            return;
        }

        if ($item = $this->currentItem()) {
            $this->doMove($item, $bridge);
        }
    }

    /** Yellow state's action — saw's already in position, just needs the label. */
    public function printCurrentLabel(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->guardTabletAction($request) || $this->tigerStatus !== 'idle') {
            return;
        }

        if ($item = $this->currentItem()) {
            $this->doPrint($item, $bridge);
        }
    }

    /**
     * Green state's button when the cut sensor is off — the operator is
     * telling us the cut actually happened. When the sensor is on, this
     * state is status-only instead (see the view) and checkSensor() fires
     * finalizeCutAndAdvance() on its own once the sensor confirms.
     */
    public function confirmCutAndAdvance(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->guardTabletAction($request) || $this->tigerStatus !== 'idle') {
            return;
        }

        $item = $this->currentItem();

        if (! $item || $this->currentItemState($item) !== 'green') {
            return;
        }

        if (CutFlowSetting::current()->cut_sensor_active) {
            return;
        }

        $this->finalizeCutAndAdvance($bridge);
    }

    /**
     * Records the cut (CutLogEntry, qty decrement, item marked done) and
     * advances: if the stick has more pending pieces, immediately fires
     * move+print for the next one rather than waiting on another manual
     * action; if the stick is now complete and this profile still has
     * unassigned pieces, prompts for the next stick's length automatically
     * instead of leaving the operator to notice and click "Start Next
     * Stick" themselves. Shared by confirmCutAndAdvance() (button, no
     * sensor) and checkSensor() (sensor confirms the physical cut) — the
     * bookkeeping is identical either way, only what triggers it differs.
     */
    protected function finalizeCutAndAdvance(TigerBridgeClient $bridge): void
    {
        $item = $this->currentItem();

        if (! $item) {
            return;
        }

        $item->update(['status' => 'done']);
        $item->part()->decrement('qty_remaining');

        $part = $item->part;
        $stickSession = $item->stickSession;

        CutLogEntry::create([
            'uuid' => $item->pending_uuid ?? (string) Str::uuid(),
            'part_id' => $item->part_id,
            'part_name' => $part->name,
            'finish' => $part->finish,
            'operator_id' => $this->activeOperatorId(),
            'operator_name' => $this->activeOperatorName(),
            'cut_job_id' => $part->cut_job_id,
            'job_name' => $part->cutJob?->name,
            'work_order' => $part->work_order,
            'phase' => $part->phase,
            'description' => $part->description,
            'dimension_inches' => $item->dimension_inches,
            'stick_length_label' => $stickSession->length_label,
            'stick_session_id' => $item->stick_session_id,
            'type' => 'planned',
        ]);

        $this->tigerStatus = 'idle';

        if ($stickSession->items()->where('status', 'pending')->exists()) {
            // Same stick, more pieces — drive the next one automatically.
            if ($next = $this->currentItem()) {
                $this->moveAndPrintItem($next, $bridge);
            }

            return;
        }

        $stickSession->update(['status' => 'complete']);
        $this->activeStickId = null;

        $pool = app(CutPlanner::class)->unassignedPieces($this->activeProfileName, $this->activeProfileFinish, $this->activeJobIds);

        if ($pool->isNotEmpty()) {
            $this->modal = 'stick';
            $this->keypadValue = '';
        }
    }

    /**
     * Refreshes the TigerStop connection badge + last-known position shown
     * in the topbar. Polled continuously (low frequency — this is a
     * convenience display, not something a cut waits on) plus called
     * directly after every move for an instant update rather than waiting
     * on the next poll tick.
     */
    public function refreshTigerBridgeStatus(TigerBridgeClient $bridge): void
    {
        $status = $bridge->status();
        $data = $status['data'] ?? [];

        $this->tigerConnected = $status['ok'] && ($data['serialConnected'] ?? false);
        $this->tigerPosition = isset($data['lastPosition']) ? (float) $data['lastPosition'] : null;
        $this->tigerPositionAt = $data['lastPositionAt'] ?? null;
    }

    protected function markBridgeActivity(): void
    {
        $this->lastBridgeActivityAt = now()->timestamp;
    }

    /**
     * True once the current item is green (in position, label printed) and
     * the shop has the physical cut sensor enabled — the state where
     * there's nothing left for the operator to click, just a wait for the
     * sensor to confirm the cut (see checkSensor(), polled by the view).
     */
    protected function awaitingSensor(): bool
    {
        if (! CutFlowSetting::current()->cut_sensor_active) {
            return false;
        }

        $item = $this->currentItem();

        return $item && $this->currentItemState($item) === 'green';
    }

    /**
     * Poll interval (ms) for the TigerStop status badge's wire:poll (see
     * dashboard.blade.php) — 3s while an operator is signed in, a move/cut
     * is actually in flight, or within 30s of the last manual action;
     * 20s otherwise. tiger-bridge only ever has one tablet talking to it,
     * so this is just trimming idle-hours polling chatter, not a
     * correctness concern — a push/websocket layer would be solving a
     * problem this app doesn't have.
     */
    protected function bridgePollIntervalMs(): int
    {
        $active = $this->signedIn()
            || in_array($this->tigerStatus, ['positioning', 'printing'], true)
            || $this->awaitingSensor()
            || ($this->lastBridgeActivityAt && (now()->timestamp - $this->lastBridgeActivityAt) <= 30);

        return $active ? 3000 : 20000;
    }

    /**
     * Polled from the view (wire:poll) only while awaiting the physical cut
     * sensor (green state + cut_sensor_active — see awaitingSensor() /
     * the view's $awaitingSensor), so this stays a no-op the rest of the
     * time.
     */
    public function checkSensor(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->awaitingSensor()) {
            return;
        }

        if (! $this->authorizedTabletRequest($request)) {
            return;
        }

        $status = $bridge->sensorStatus();

        if (($status['data']['status'] ?? null) === 'complete') {
            $this->finalizeCutAndAdvance($bridge);
        }
    }

    /**
     * Abandons the active stick mid-run. Pieces already cut keep their
     * CutLogEntry history; whatever's still pending on the stick is just
     * dropped, which is all it takes to put those parts back in the cut
     * list — unassignedPieces() only excludes parts with a *pending*
     * StickItem, so once there isn't one they're unassigned again.
     * Blocked while the stop is mid-motion (see the view's disabled state
     * on this button) since there's no bridge call to abort a move in
     * flight.
     */
    public function stopStick(): void
    {
        if (! $this->activeStickId || in_array($this->tigerStatus, ['positioning', 'printing'], true) || $this->awaitingSensor()) {
            return;
        }

        $session = StickSession::find($this->activeStickId);

        $session?->items()->where('status', 'pending')->delete();
        $session?->update(['status' => 'complete', 'cancelled_at' => now()]);

        $this->activeStickId = null;
        $this->tigerStatus = 'idle';
    }

    protected function printLabelForEntry(TigerBridgeClient $bridge, CutLogEntry $entry): void
    {
        $result = $bridge->printLabel([
            'job' => $entry->job_name,
            'part' => $entry->finish ? "{$entry->part_name} · {$entry->finish}" : $entry->part_name,
            'partUse' => $entry->description,
            'elevation' => $entry->phase,
            'size' => Dimension::toFraction((float) $entry->dimension_inches),
            'uuid' => $entry->uuid,
            'qrUrl' => route('cutflow.cuts.show', $entry->uuid),
        ]);

        if (! $result['ok']) {
            $this->lastError = $result['error'] ?? 'Print failed';
        }
    }

    /**
     * Fires a browser event carrying a preview of the label that will
     * print for this cut (see the "cut-started" listener in
     * dashboard.blade.php, which renders it as a bottom-right toast).
     * Settings-gated since some shops find a popup on every cut distracting.
     *
     * For planned cuts this fires from doPrint() (the item's reserved
     * pending_uuid gives it a real qrUrl before the CutLogEntry exists),
     * not from finalizeCutAndAdvance() — the toast previews the label at
     * print time, whenever that happens to fall relative to the move.
     */
    protected function announceCutStarted(array $label): void
    {
        if (! CutFlowSetting::current()->show_cut_toast) {
            return;
        }

        $this->dispatch('cut-started', label: $label);
    }

    /**
     * Renders the same QR a cut's printed label carries — a real scannable
     * code, not the CSS placeholder the toast used before this pointed at
     * a live qrUrl. SVG keeps it dependency-free (no GD) and cheap enough
     * to inline straight into the Livewire payload.
     */
    protected function renderCutQrSvg(string $url): string
    {
        $svg = (new Builder(writer: new SvgWriter))
            ->build(
                data: $url,
                errorCorrectionLevel: ErrorCorrectionLevel::Low,
                size: 56,
                margin: 0,
            )
            ->getString();

        // strip the XML prolog — harmless if left in (browsers treat it as
        // a bogus comment when set via innerHTML) but pointless to ship.
        return preg_replace('/^<\?xml.*?\?>\s*/', '', $svg);
    }

    // --- recut / reprint -----------------------------------------------

    /**
     * The piece came out bad (or was lost) after already being marked cut —
     * queue a fresh pending item for the same part at the front of the
     * active stick's remaining work, and bump qty back up since the
     * original decrement no longer reflects one usable piece.
     */
    public function recutEntry(int $cutLogEntryId): void
    {
        $entry = CutLogEntry::findOrFail($cutLogEntryId);

        if (! $entry->part_id) {
            return; // manual entries have nothing to re-queue
        }

        $part = $entry->part;
        $part?->increment('qty_remaining');

        if ($this->activeStickId) {
            $maxSequence = StickItem::where('stick_session_id', $this->activeStickId)->max('sequence') ?? 0;

            StickItem::create([
                'stick_session_id' => $this->activeStickId,
                'part_id' => $entry->part_id,
                'dimension_inches' => $entry->dimension_inches,
                'sequence' => $maxSequence + 1,
                'status' => 'pending',
            ]);
        }

        CutLogEntry::create([
            'uuid' => (string) Str::uuid(),
            'part_id' => $entry->part_id,
            'part_name' => $entry->part_name,
            'finish' => $entry->finish,
            'operator_id' => $this->activeOperatorId(),
            'operator_name' => $this->activeOperatorName(),
            'cut_job_id' => $entry->cut_job_id,
            'job_name' => $entry->job_name,
            'work_order' => $entry->work_order,
            'phase' => $entry->phase,
            'description' => $entry->description,
            'dimension_inches' => $entry->dimension_inches,
            'stick_length_label' => $entry->stick_length_label,
            'type' => $entry->type,
            'is_recut' => true,
        ]);
    }

    /**
     * Reprints a past cut's label unchanged and logs a linked entry (same
     * uuid family via the original entry's data) with is_reprint set, so
     * there's a record a second label went out for this cut.
     */
    public function reprintEntry(int $cutLogEntryId, TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->authorizedTabletRequest($request)) {
            $this->lastError = 'This action is only available from the cut station tablet.';

            return;
        }

        $entry = CutLogEntry::findOrFail($cutLogEntryId);

        $reprint = CutLogEntry::create([
            'uuid' => (string) Str::uuid(),
            'part_id' => $entry->part_id,
            'part_name' => $entry->part_name,
            'finish' => $entry->finish,
            'operator_id' => $this->activeOperatorId(),
            'operator_name' => $this->activeOperatorName(),
            'cut_job_id' => $entry->cut_job_id,
            'job_name' => $entry->job_name,
            'work_order' => $entry->work_order,
            'phase' => $entry->phase,
            'description' => $entry->description,
            'dimension_inches' => $entry->dimension_inches,
            'stick_length_label' => $entry->stick_length_label,
            'type' => $entry->type,
            'is_reprint' => true,
        ]);

        if ($entry->part_id) {
            $this->printLabelForEntry($bridge, $reprint);
        } else {
            $bridge->printLabel([
                'size' => Dimension::toFraction((float) $reprint->dimension_inches),
                'operator' => $reprint->operator_name,
                'timestamp' => $reprint->cut_at_local?->format('n/j/y g:i A'),
                'uuid' => $reprint->uuid,
                'qrUrl' => route('cutflow.cuts.show', $reprint->uuid),
            ]);
        }
    }

    protected function currentItem(): ?StickItem
    {
        if (! $this->activeStickId) {
            return null;
        }

        return StickItem::where('stick_session_id', $this->activeStickId)
            ->where('status', 'pending')
            ->orderBy('sequence')
            ->with('part')
            ->first();
    }

    public function render(CutPlanner $planner)
    {
        $signedIn = $this->signedIn();

        $parts = ($signedIn && $this->activeProfileName)
            ? Part::where('name', $this->activeProfileName)
                ->where('finish', $this->activeProfileFinish)
                ->whereIn('cut_job_id', $this->activeJobIds)
                ->orderByDesc('dimension_inches')
                ->get()
            : collect();

        $completedCount = $parts->where('status_label', 'Done')->count();

        if (! $this->showCompleted) {
            $parts = $parts->reject(fn ($part) => $part->status_label === 'Done')->values();
        }

        $projection = ($signedIn && $this->activeProfileName)
            ? $planner->projectRemainingStandardSticks($this->activeProfileName, $this->activeProfileFinish, $this->activeJobIds)
            : null;

        $currentItem = $signedIn ? $this->currentItem() : null;

        return view('cutflow.livewire.dashboard', [
            'signedIn' => $signedIn,
            'tigerPollIntervalMs' => $this->bridgePollIntervalMs(),
            'jobs' => $this->modal === 'jobs' ? $this->filteredJobs() : collect(),
            'selectedJobs' => $signedIn ? CutJob::whereIn('id', $this->activeJobIds)->orderBy('name')->get() : collect(),
            'totalJobCount' => CutJob::count(),
            'profiles' => $signedIn ? $planner->activeProfiles($this->activeJobIds) : collect(),
            'parts' => $parts,
            'completedCount' => $completedCount,
            'projection' => $projection,
            'stick' => ($signedIn && $this->activeStickId) ? StickSession::with('items.part')->find($this->activeStickId) : null,
            'currentItem' => $currentItem,
            'currentItemState' => $currentItem ? $this->currentItemState($currentItem) : null,
            'awaitingSensor' => $this->awaitingSensor(),
            'log' => CutLogEntry::latest()->limit(6)->get(),
        ]);
    }
}

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

    // idle | positioning | ready | waiting_for_sensor
    public string $tigerStatus = 'idle';

    // Last known TigerStop connection/position, from tiger-bridge's GET
    // /status — see refreshTigerBridgeStatus(). The amp has no "read
    // current position" query, so lastPosition is the last inches value
    // tiger-bridge successfully moved it to (see TigerBridgeClient).
    public bool $tigerConnected = false;

    public ?float $tigerPosition = null;

    public ?string $tigerPositionAt = null;

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
        $allowed = IpAllowlist::allows($request->ip(), config('cutflow.tablet_allowed_ips', []));

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

            $this->announceCutStarted([
                'size' => Dimension::toFraction($inches),
            ]);

            // manual stickers carry only size + cut record data, no job/part fields
            $bridge->printLabel([
                'size' => Dimension::toFraction($inches),
                'operator' => $entry->operator_name,
                'timestamp' => $entry->created_at?->format('n/j/y g:i A'),
                'uuid' => $entry->uuid,
                'qrUrl' => route('cutflow.cuts.show', $entry->uuid),
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

    /**
     * Collapses the old two-step "Send to TigerStop" + "Record Cut" flow
     * into one button. Without the cut sensor enabled, "complete" is just
     * "the operator pressed the button" (today's behavior). With the sensor
     * enabled, this only moves the stop and waits — recordCut() fires once
     * the sensor confirms (see checkSensor(), polled from the view).
     */
    public function nextCut(TigerBridgeClient $bridge, Request $request): void
    {
        if (! $this->signedIn()) {
            return;
        }

        if (! $this->authorizedTabletRequest($request)) {
            $this->lastError = 'This action is only available from the cut station tablet.';

            return;
        }

        $item = $this->currentItem();

        if (! $item || $this->tigerStatus === 'positioning' || $this->tigerStatus === 'waiting_for_sensor') {
            return;
        }

        $this->tigerStatus = 'positioning';

        $result = $bridge->move((float) $item->dimension_inches);

        if (! $result['ok']) {
            $this->tigerStatus = 'idle';
            $this->lastError = $result['error'] ?? 'Move failed';
            $this->refreshTigerBridgeStatus($bridge);

            return;
        }

        $this->tigerPosition = (float) $item->dimension_inches;
        $this->tigerPositionAt = now()->toIso8601String();
        $this->tigerConnected = true;

        $part = $item->part;

        $this->announceCutStarted([
            'job' => $part->cutJob?->name,
            'part' => $part->profile_label,
            'partUse' => $part->description,
            'elevation' => $part->phase,
            'size' => Dimension::toFraction((float) $item->dimension_inches),
        ]);

        if (CutFlowSetting::current()->cut_sensor_active) {
            $this->tigerStatus = 'waiting_for_sensor';

            return;
        }

        $this->tigerStatus = 'ready';
        $this->recordCut($bridge);
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

    /**
     * Polled from the view (wire:poll) only while a sensor-gated cut is in
     * flight, so this stays a no-op the rest of the time.
     */
    public function checkSensor(TigerBridgeClient $bridge, Request $request): void
    {
        if ($this->tigerStatus !== 'waiting_for_sensor') {
            return;
        }

        if (! $this->authorizedTabletRequest($request)) {
            return;
        }

        $status = $bridge->sensorStatus();

        if (($status['data']['status'] ?? null) === 'complete') {
            $this->tigerStatus = 'ready';
            $this->recordCut($bridge);
        }
    }

    public function recordCut(TigerBridgeClient $bridge): void
    {
        $item = $this->currentItem();

        if (! $item) {
            return;
        }

        $item->update(['status' => 'done']);
        $item->part()->decrement('qty_remaining');

        $part = $item->part;

        $entry = CutLogEntry::create([
            'uuid' => (string) Str::uuid(),
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
            'stick_length_label' => $item->stickSession->length_label,
            'stick_session_id' => $item->stick_session_id,
            'type' => 'planned',
        ]);

        $this->printLabelForEntry($bridge, $entry);

        $this->tigerStatus = 'idle';

        if (! $item->stickSession->items()->where('status', 'pending')->exists()) {
            $item->stickSession->update(['status' => 'complete']);
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
        if (! $this->activeStickId || in_array($this->tigerStatus, ['positioning', 'waiting_for_sensor'], true)) {
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
     * print at the end of this cut (see the "cut-started" listener in
     * dashboard.blade.php, which renders it as a bottom-right toast).
     * Settings-gated since some shops find a popup on every cut distracting.
     */
    protected function announceCutStarted(array $label): void
    {
        if (! CutFlowSetting::current()->show_cut_toast) {
            return;
        }

        $this->dispatch('cut-started', label: $label);
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
                'timestamp' => $reprint->created_at?->format('n/j/y g:i A'),
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

        return view('cutflow.livewire.dashboard', [
            'signedIn' => $signedIn,
            'jobs' => $this->modal === 'jobs' ? $this->filteredJobs() : collect(),
            'selectedJobs' => $signedIn ? CutJob::whereIn('id', $this->activeJobIds)->orderBy('name')->get() : collect(),
            'totalJobCount' => CutJob::count(),
            'profiles' => $signedIn ? $planner->activeProfiles($this->activeJobIds) : collect(),
            'parts' => $parts,
            'completedCount' => $completedCount,
            'projection' => $projection,
            'stick' => ($signedIn && $this->activeStickId) ? StickSession::with('items.part')->find($this->activeStickId) : null,
            'currentItem' => $signedIn ? $this->currentItem() : null,
            'log' => CutLogEntry::latest()->limit(6)->get(),
        ]);
    }
}

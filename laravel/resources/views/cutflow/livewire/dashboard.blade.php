<div>
    <div class="topbar">
        <div style="display:flex;align-items:baseline;gap:9px;">
            <span class="display" style="font-weight:700;font-size:18px;">CutFlow</span>
            <span class="mono" style="font-size:10.5px;letter-spacing:.12em;color:var(--muted);">SHOP FLOOR &middot; TABLET</span>
        </div>

        {{-- Signed-in crew — tap a chip to make them the credited operator for
             the next action, × signs that person out. "+" adds another. A
             fab_pin sign-in is required for job/cut-list access; with nobody
             signed in, only Manual Override is available (see below). --}}
        <div style="display:flex;align-items:center;gap:8px;flex-grow:1;justify-content:center;">
            @foreach ($crew as $person)
                <div wire:click="setActiveOperator('{{ $person['key'] }}')"
                     style="cursor:pointer;display:flex;align-items:center;gap:6px;padding:6px 6px 6px 12px;border-radius:999px;
                            background: {{ $person['key'] === $activeCrewKey ? 'var(--accent)' : '#2E3339' }};">
                    <span style="font-size:12.5px;font-weight:600;color:#fff;">{{ $person['name'] }}</span>
                    <span wire:click.stop="signOutOperator('{{ $person['key'] }}')"
                          style="width:18px;height:18px;border-radius:50%;background:rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;font-size:10px;color:#fff;">&#10005;</span>
                </div>
            @endforeach
            <div wire:click="openLogin" style="cursor:pointer;display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:999px;background:#2E3339;color:#fff;font-size:12.5px;font-weight:600;">
                @if (empty($crew))
                    Sign In
                @else
                    <span style="width:18px;height:18px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;line-height:1;">+</span>
                @endif
            </div>
        </div>

        <div style="display:flex;align-items:center;gap:14px;">
            {{-- TigerStop connection + last-known position. The amp has no
                 "read current position" query (see tiger-bridge's README),
                 so this is the last inches value we successfully commanded
                 it to — refreshed on load, after every move, and on a slow
                 poll so a dropped connection shows up without a move
                 happening first. --}}
            <div wire:poll.10s="refreshTigerBridgeStatus"
                 style="display:flex;align-items:center;gap:7px;padding:5px 10px;border-radius:999px;background:var(--surface);border:1px solid var(--border);">
                <span style="width:8px;height:8px;border-radius:50%;background: {{ $tigerConnected ? 'var(--success)' : 'var(--danger)' }};"></span>
                <span style="font-size:11px;color:var(--muted);">TigerStop</span>
                <span class="mono" style="font-size:12.5px;font-weight:700;">
                    {{ $tigerPosition !== null ? number_format($tigerPosition, 3).'"' : '—' }}
                </span>
            </div>
            <a href="{{ route('cutflow.import.show') }}">Import List</a>
            <a href="{{ route('cutflow.settings') }}">Settings</a>
        </div>
    </div>

    @if (! $signedIn)
        <div style="padding:12px 24px;background:var(--accent-bg);border-bottom:1px solid var(--accent-border);color:var(--accent);font-size:12.5px;font-weight:600;">
            Sign in with your PIN to see job cut lists and plan sticks — Manual Override below still works without signing in.
        </div>
    @else
        {{-- Job selection summary — opens a searchable picker modal rather than
             listing every job inline, since shops can have 20-40 active jobs
             at once and a checkbox wall doesn't scale to that. --}}
        <div style="padding:10px 24px;background:var(--surface);border-bottom:1px solid var(--border);display:flex;align-items:center;gap:10px;">
            <span style="font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:600;">JOBS</span>

            <div wire:click="openJobsModal"
                 style="cursor:pointer;display:flex;align-items:center;gap:8px;padding:6px 14px;border-radius:999px;font-size:12.5px;font-weight:600;
                        background:var(--panel);color:var(--muted-2);border:1.5px solid var(--border);">
                @if ($totalJobCount === 0)
                    No jobs yet
                @elseif ($selectedJobs->isEmpty())
                    Select jobs&hellip;
                @elseif ($selectedJobs->count() <= 3)
                    {{ $selectedJobs->pluck('name')->join(', ') }}
                @else
                    {{ $selectedJobs->count() }} of {{ $totalJobCount }} jobs selected
                @endif
                <span style="color:var(--faint);">&#9662;</span>
            </div>

            @if ($totalJobCount === 0)
                <span style="font-size:12.5px;color:var(--muted);">— <a href="{{ route('cutflow.import.show') }}" style="color:var(--accent);">import a cut list</a> to create one.</span>
            @endif
        </div>
    @endif

    <div class="layout">

        @if ($signedIn)
            {{-- Left: the selected profile's cut list --}}
            <div class="sidebar">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <h2 style="margin:0;">Cut List</h2>
                    <div wire:click="toggleShowCompleted"
                         style="cursor:pointer;display:flex;align-items:center;gap:6px;padding:5px 10px;border-radius:999px;font-size:11.5px;font-weight:600;
                                background: {{ $showCompleted ? 'var(--surface)' : 'var(--accent-bg)' }};
                                color: {{ $showCompleted ? 'var(--muted-2)' : 'var(--accent)' }};">
                        <span style="width:8px;height:8px;border-radius:50%;background: {{ $showCompleted ? 'var(--muted)' : 'var(--accent)' }};"></span>
                        {{ $showCompleted ? 'Showing completed' : 'Completed hidden' }}
                    </div>
                </div>
                <div class="hint">One profile at a time &mdash; a stick is one piece of raw material, it can't mix profiles</div>

                @if ($profiles->isEmpty())
                    <p style="font-size:12.5px;color:var(--muted);">No parts in the selected job(s) yet. <a href="{{ route('cutflow.import.show') }}">Import a cut list</a> to get started.</p>
                @else
                    @foreach ($profiles as $p)
                        @php
                            $isActive = $p->name === $activeProfileName && $p->finish === $activeProfileFinish;
                        @endphp
                        <div class="part-row profile-row @if($isActive) active @endif" style="flex-direction:column;align-items:stretch;padding:0;overflow:hidden;">
                            <div wire:click="switchProfile('{{ $p->name }}', '{{ $p->finish }}')"
                                 style="display:flex;align-items:center;justify-content:space-between;padding:14px;cursor:pointer;">
                                <div>
                                    <div class="name">{{ $p->finish ? "{$p->name} · {$p->finish}" : $p->name }}</div>
                                    <div class="dim mono">{{ $p->length_count }} length{{ $p->length_count === 1 ? '' : 's' }}</div>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <span class="mono" style="font-size:12px;">{{ $p->qty_remaining_total }} pcs</span>
                                    <span style="font-size:13px;color:var(--faint);">{!! $isActive ? '&#9662;' : '&#9656;' !!}</span>
                                </div>
                            </div>

                            @if ($isActive)
                                <div style="padding:0 14px 14px;">
                                    @if ($projection)
                                        <div style="padding:10px 12px;border-radius:9px;background:var(--surface);margin-bottom:10px;display:flex;flex-direction:column;gap:5px;">
                                            <div style="font-size:11.5px;color:var(--text-2);">
                                                <strong class="mono">{{ $projection['piecesOnStandardStock'] }}</strong> unassigned need
                                                <strong class="mono">{{ $projection['stickCount'] }}</strong> more {{ number_format($projection['standardLength'], 0) }}" sticks
                                                @if ($projection['stickCount'] > 0)
                                                    (~{{ number_format($projection['totalWasteInches'], 1) }}" waste)
                                                @endif
                                            </div>
                                            @if ($projection['overLength']->isNotEmpty())
                                                <div style="font-size:11.5px;color:var(--danger);font-weight:600;">
                                                    &#9888; {{ $projection['overLength']->sum('count') }} pc(s) exceed {{ number_format($projection['standardLength'], 0) }}" stock &mdash; order longer material:
                                                    @foreach ($projection['overLength'] as $ol)
                                                        {{ $ol['count'] }}&times;{{ number_format($ol['dimension_inches'], 3) }}"@if (!$loop->last), @endif
                                                    @endforeach
                                                </div>
                                            @endif
                                        </div>
                                    @endif

                                    @if ($parts->isEmpty() && $completedCount > 0)
                                        <div style="padding:14px 4px;font-size:12.5px;color:var(--muted);">
                                            All {{ $completedCount }} piece{{ $completedCount === 1 ? '' : 's' }} in this profile are done &mdash;
                                            <span wire:click="toggleShowCompleted" style="cursor:pointer;color:var(--accent);font-weight:600;">show completed</span> to see them.
                                        </div>
                                    @else
                                        <table class="dim-table">
                                            <thead>
                                                <tr><th>Length</th><th>Qty</th><th>Elevation</th><th>Use</th><th></th></tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($parts as $part)
                                                    @php
                                                        $isCurrent = $currentItem && $currentItem->part_id === $part->id;
                                                        $statusClass = match ($part->status_label) {
                                                            'Done' => 'done',
                                                            'In progress' => 'progress',
                                                            default => 'pending',
                                                        };
                                                    @endphp
                                                    <tr class="@if($isCurrent) current @endif">
                                                        <td class="mono">{{ number_format($part->dimension_inches, 3) }}"</td>
                                                        <td class="mono">{{ $part->qty_remaining }}/{{ $part->qty_original }}</td>
                                                        <td class="mono" style="color:var(--muted);">{{ $part->phase }}</td>
                                                        <td style="color:var(--muted-2);">{{ $part->description }}</td>
                                                        <td><span class="badge {{ $statusClass }}">{{ $part->status_label }}</span></td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                        @if (! $showCompleted && $completedCount > 0)
                                            <div style="padding:8px 4px 0;font-size:11.5px;color:var(--muted);">
                                                {{ $completedCount }} completed piece{{ $completedCount === 1 ? '' : 's' }} hidden &mdash;
                                                <span wire:click="toggleShowCompleted" style="cursor:pointer;color:var(--accent);font-weight:600;">show</span>
                                            </div>
                                        @endif
                                    @endif
                                </div>
                            @endif
                        </div>
                    @endforeach
                @endif
            </div>
        @endif

        {{-- Right: manual override, stick flow, cut log --}}
        <div class="main" @if (! $signedIn) style="flex-grow:1;" @endif>

            <div class="override-bar" wire:click="openManual">
                <span style="width:28px;height:28px;border-radius:8px;background:var(--surface);display:flex;align-items:center;justify-content:center;">&#9000;</span>
                <span class="title">Manual Override</span>
                <span class="sub">&mdash; bypass the plan, send one dimension</span>
                <div style="flex-grow:1;"></div>
                <span style="font-size:16px;color:var(--faint);">&#8250;</span>
            </div>

            @if (! $signedIn)
                <div class="empty-state">
                    <span style="width:52px;height:52px;border-radius:14px;background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:24px;">&#128274;</span>
                    <div>
                        <div class="title">Sign In For Job Cut Lists</div>
                        <p>Manual Override above works without signing in. To browse an imported job's cut list and plan sticks, sign in with your PIN.</p>
                    </div>
                    <button class="btn btn-accent" wire:click="openLogin" style="margin-top:4px;">Sign In</button>
                </div>
            @elseif (! $stick)
                <div class="empty-state">
                    <span style="width:52px;height:52px;border-radius:14px;background:var(--surface);display:flex;align-items:center;justify-content:center;font-size:24px;">&#128207;</span>
                    @if ($activeProfileName)
                        <div>
                            <div class="title">No Active Stick</div>
                            <p>
                                Enter the length of the {{ $activeProfileFinish ? "{$activeProfileName} · {$activeProfileFinish}" : $activeProfileName }}
                                stick or drop you're loading. CutFlow will pull the best combination of that profile's remaining parts out of it.
                            </p>
                        </div>
                        <button class="btn btn-accent" wire:click="openStick" style="margin-top:4px;">Enter Stick Length</button>
                    @else
                        <div>
                            <div class="title">No Profile Selected</div>
                            <p>Select at least one job above and pick a profile from the left to start entering sticks.</p>
                        </div>
                    @endif
                </div>
            @else
                <div class="card" style="display:flex;flex-direction:column;gap:16px;">

                    <div class="stick-header">
                        <div>
                            <div class="label">ACTIVE STICK</div>
                            <div class="length mono">{{ $stick->length_label }}"</div>
                        </div>
                        <div style="display:flex;align-items:center;gap:16px;">
                            <div style="text-align:right;">
                                <div style="font-size:11px;color:var(--muted);">pieces cut</div>
                                <div class="mono" style="font-size:15px;font-weight:700;">
                                    {{ $stick->items->where('status', 'done')->count() }}/{{ $stick->items->count() }}
                                </div>
                            </div>
                            <div style="text-align:right;">
                                <div style="font-size:11px;color:var(--muted);">projected waste</div>
                                <div class="mono" style="font-size:15px;font-weight:700;color:var(--accent);">
                                    {{ number_format($stick->waste_inches, 3) }}"
                                </div>
                            </div>
                            <button class="btn btn-outline" style="padding:9px 14px;font-size:12.5px;color:var(--danger);border-color:var(--danger);"
                                    wire:click="stopStick"
                                    wire:confirm="Stop this stick? The {{ $stick->items->where('status', 'pending')->count() }} uncut piece(s) on it will go back to the cut list."
                                    @if (in_array($tigerStatus, ['positioning', 'waiting_for_sensor'])) disabled @endif>
                                Stop Stick
                            </button>
                        </div>
                    </div>

                    @if ($currentItem)
                        <div class="now-cutting"
                             @if ($tigerStatus === 'waiting_for_sensor') wire:poll.1s="checkSensor" @endif>
                            <div style="display:flex;align-items:center;justify-content:space-between;">
                                <div style="display:flex;align-items:center;gap:14px;">
                                    @if ($currentItem->part->photo_url)
                                        <img src="{{ $currentItem->part->photo_url }}" alt=""
                                             onclick="openPartPhoto()"
                                             style="width:64px;height:64px;border-radius:10px;object-fit:cover;background:var(--surface);border:1.5px solid var(--accent-border);flex-shrink:0;cursor:zoom-in;">
                                    @else
                                        <span style="width:64px;height:64px;border-radius:10px;background:var(--surface);border:1.5px solid var(--accent-border);flex-shrink:0;display:flex;align-items:center;justify-content:center;font-size:22px;color:var(--faint);">&#128247;</span>
                                    @endif
                                    <div>
                                        <div class="label" style="color:var(--accent);">NOW CUTTING</div>
                                        <div class="display" style="font-size:20px;font-weight:700;">{{ $currentItem->part->name }}</div>
                                    </div>
                                </div>
                                @php
                                    $statusMap = [
                                        'idle' => ['text' => 'Idle', 'dot' => 'var(--muted)', 'bg' => 'var(--surface)', 'color' => 'var(--muted-2)'],
                                        'positioning' => ['text' => 'Positioning…', 'dot' => 'var(--accent)', 'bg' => 'var(--accent-bg)', 'color' => 'var(--accent)'],
                                        'ready' => ['text' => 'Ready — at target', 'dot' => 'var(--success)', 'bg' => 'var(--success-bg)', 'color' => 'var(--success)'],
                                        'waiting_for_sensor' => ['text' => 'Waiting for cut sensor…', 'dot' => 'var(--accent)', 'bg' => 'var(--accent-bg)', 'color' => 'var(--accent)'],
                                    ];
                                    $s = $statusMap[$tigerStatus] ?? $statusMap['idle'];
                                @endphp
                                <span class="status-pill" style="background: {{ $s['bg'] }}; color: {{ $s['color'] }};">
                                    <span class="status-dot" style="background: {{ $s['dot'] }};"></span>
                                    {{ $s['text'] }}
                                </span>
                            </div>
                            <div class="dim">{{ number_format($currentItem->dimension_inches, 3) }}"</div>
                            <button class="btn btn-accent" style="width:100%;" wire:click="nextCut"
                                    @if (in_array($tigerStatus, ['positioning', 'waiting_for_sensor'])) disabled @endif>
                                Next Cut &mdash; Move &amp; Print Label
                            </button>
                        </div>
                    @else
                        <div class="stick-complete">
                            <div>
                                <div style="font-size:14.5px;font-weight:700;color:var(--success);">Stick complete</div>
                                <div style="font-size:12.5px;color:var(--success);">
                                    {{ number_format($stick->waste_inches, 3) }}" left over &middot; load the next stick to keep going
                                </div>
                            </div>
                            <button class="btn btn-success" wire:click="openStick">Start Next Stick</button>
                        </div>
                    @endif

                    <div>
                        @foreach ($stick->items as $item)
                            @php
                                $done = $item->status === 'done';
                                $isCurrent = $currentItem && $currentItem->id === $item->id;
                                $dotBg = $done ? 'var(--success)' : ($isCurrent ? 'var(--accent)' : 'var(--surface)');
                            @endphp
                            <div class="item-row">
                                <div style="display:flex;align-items:center;gap:12px;">
                                    <span class="dot" style="background: {{ $dotBg }};">{{ $done ? '✓' : '' }}</span>
                                    <span style="font-size:13px;font-weight: {{ $isCurrent ? 700 : 500 }}; color: {{ $done ? 'var(--muted)' : 'var(--ink)' }};">
                                        {{ $item->part->name }}
                                    </span>
                                </div>
                                <span class="mono" style="font-size:12.5px;color:var(--muted-2);">{{ number_format($item->dimension_inches, 3) }}"</span>
                            </div>
                        @endforeach
                    </div>

                    @if ($currentItem && $currentItem->part->photo_url)
                        <div class="photo-lightbox" id="photo-lightbox" onclick="closePartPhoto()">
                            <img src="{{ $currentItem->part->photo_url }}" alt="">
                        </div>
                    @endif

                </div>
            @endif

            @if ($lastError)
                <div style="padding:12px 16px;border-radius:10px;background:var(--danger-bg);color:var(--danger);font-size:13px;font-weight:600;">
                    {{ $lastError }}
                </div>
            @endif

            <div class="card" style="flex-grow:1;min-height:0;display:flex;flex-direction:column;gap:10px;overflow-y:auto;">
                <div class="display" style="font-size:14px;font-weight:700;">Cut Log</div>
                @forelse ($log as $entry)
                    <div class="cutlog-row">
                        <div style="display:flex;align-items:center;gap:12px;">
                            <span class="mono" style="font-size:11px;color:var(--muted);width:80px;">{{ $entry->created_at->format('g:i:s A') }}</span>
                            <span style="font-size:12.5px;font-weight:600;">{{ $entry->part_name }}</span>
                            <span style="font-size:11px;color:var(--muted);">{{ $entry->operator_name }}</span>
                            @if ($entry->is_recut)
                                <span class="badge" style="background:var(--danger-bg);color:var(--danger);">RECUT</span>
                            @endif
                            @if ($entry->is_reprint)
                                <span class="badge" style="background:var(--surface);color:var(--muted-2);">REPRINT</span>
                            @endif
                        </div>
                        <div style="display:flex;align-items:center;gap:10px;">
                            <span class="mono" style="font-size:12px;color:var(--text-2);">
                                {{ number_format($entry->dimension_inches, 3) }}"
                                @if ($entry->stick_length_label) on {{ $entry->stick_length_label }}" stick @endif
                            </span>
                            <span wire:click="reprintEntry({{ $entry->id }})" title="Reprint label" style="cursor:pointer;font-size:12px;color:var(--muted-2);">&#128424;</span>
                            @if ($entry->part_id)
                                <span wire:click="recutEntry({{ $entry->id }})" title="Flag for recut" style="cursor:pointer;font-size:12px;color:var(--accent);">&#8635;</span>
                            @endif
                        </div>
                    </div>
                @empty
                    <div style="font-size:12.5px;color:var(--muted);">No cuts recorded yet this session.</div>
                @endforelse
            </div>

        </div>
    </div>

    {{-- Job picker: searchable so 20-40 active jobs stays usable. Separate
         from the keypad modal below since it's a scrollable checklist, not
         a numeric entry flow. --}}
    @if ($modal === 'jobs')
        <div class="modal-overlay" wire:keydown.window.escape="closeModal">
            <div class="modal" style="width:480px;">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div class="display" style="font-size:18px;font-weight:700;">Select Jobs</div>
                    <div wire:click="closeModal" style="cursor:pointer;width:40px;height:40px;border-radius:10px;background:var(--surface);display:flex;align-items:center;justify-content:center;">&#10005;</div>
                </div>

                <input type="text" wire:model.live.debounce.200ms="jobSearch" placeholder="Search jobs&hellip;" autofocus
                       style="padding:12px 14px;border-radius:10px;border:1.5px solid var(--border);font-size:14px;">

                <div style="display:flex;gap:10px;">
                    <button class="btn btn-outline" style="flex:1;font-size:12.5px;padding:10px;" wire:click="selectVisibleJobs">Select all shown</button>
                    <button class="btn btn-outline" style="flex:1;font-size:12.5px;padding:10px;" wire:click="clearJobSelection">Clear selection</button>
                </div>

                <div style="max-height:340px;overflow-y:auto;display:flex;flex-direction:column;gap:6px;">
                    @forelse ($jobs as $job)
                        @php $isSelected = in_array($job->id, $activeJobIds); @endphp
                        <div wire:click="toggleJob({{ $job->id }})"
                             style="cursor:pointer;display:flex;align-items:center;gap:10px;padding:10px 12px;border-radius:9px;
                                    background: {{ $isSelected ? 'var(--accent-bg)' : 'var(--surface)' }};
                                    border: 1.5px solid {{ $isSelected ? 'var(--accent-border)' : 'transparent' }};">
                            <span style="width:20px;height:20px;border-radius:6px;flex-shrink:0;display:flex;align-items:center;justify-content:center;
                                         background: {{ $isSelected ? 'var(--accent)' : 'var(--panel)' }};
                                         border: 1.5px solid {{ $isSelected ? 'var(--accent)' : 'var(--border)' }};
                                         color:#fff;font-size:12px;">{{ $isSelected ? '✓' : '' }}</span>
                            <span style="font-size:13.5px;font-weight:600;">{{ $job->name }}</span>
                        </div>
                    @empty
                        <div style="font-size:12.5px;color:var(--muted);padding:10px 2px;">No jobs match "{{ $jobSearch }}".</div>
                    @endforelse
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <span style="font-size:12px;color:var(--muted);">{{ count($activeJobIds) }} selected</span>
                    <button class="btn btn-accent" wire:click="closeModal">Done</button>
                </div>
            </div>
        </div>
    @endif

    {{-- Shared modal: operator login, manual override entry, or new stick length entry --}}
    @if ($modal && $modal !== 'jobs')
        <div class="modal-overlay"
             wire:keydown.window.enter="confirmModal"
             wire:keydown.window.escape="closeModal"
             wire:keydown.window.backspace.prevent="backspace"
             wire:keydown.window.space.prevent="tapSpace"
             wire:keydown.window="handleKeydown($event.key)">
            <div class="modal">
                <div style="display:flex;align-items:center;justify-content:space-between;">
                    <div>
                        <div class="display" style="font-size:18px;font-weight:700;">
                            @if ($modal === 'login') Sign In
                            @elseif ($modal === 'stick') New Stick
                            @else Manual Override
                            @endif
                        </div>
                        <div style="font-size:12px;color:var(--muted);">
                            @if ($modal === 'login')
                                Enter your PIN &mdash; you'll be added alongside anyone already signed in
                            @elseif ($modal === 'stick')
                                Enter the stick or drop length you're loading &mdash; {{ $activeProfileFinish ? "{$activeProfileName} · {$activeProfileFinish}" : $activeProfileName }}
                            @else
                                Enter a dimension and send it directly to the stop
                            @endif
                        </div>
                    </div>
                    <div wire:click="closeModal" style="cursor:pointer;width:40px;height:40px;border-radius:10px;background:var(--surface);display:flex;align-items:center;justify-content:center;">&#10005;</div>
                </div>

                <div class="modal-display">
                    <span class="val" style="color: {{ $keypadValue === '' ? 'var(--faint)' : 'var(--ink)' }};">
                        @if ($modal === 'login')
                            {{ $keypadValue === '' ? '····' : str_repeat('•', strlen($keypadValue)) }}
                        @else
                            {{ $keypadValue === '' ? '0' : $keypadValue }}"
                        @endif
                    </span>
                    <div wire:click="clearDisplay" style="cursor:pointer;font-size:12.5px;font-weight:600;color:var(--accent);">Clear</div>
                </div>

                @if ($modal === 'login' && $loginError)
                    <div style="font-size:12.5px;font-weight:600;color:var(--danger);">{{ $loginError }}</div>
                @endif

                @if ($modal === 'stick')
                    <div>
                        <div style="font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:600;margin-bottom:8px;">QUICK LENGTHS</div>
                        <div class="chip-row">
                            @foreach (['288', '252', '144', '120'] as $len)
                                <div class="chip" wire:click="setKeypadValue('{{ $len }}')">{{ $len }}"</div>
                            @endforeach
                        </div>
                    </div>
                @elseif ($modal !== 'login')
                    <div>
                        <div style="font-size:10.5px;letter-spacing:.08em;color:var(--muted);font-weight:600;margin-bottom:8px;">QUICK FRACTIONS</div>
                        <div class="chip-row">
                            @foreach (['1/8', '1/4', '3/8', '1/2', '5/8', '3/4', '7/8'] as $frac)
                                <div class="chip" wire:click="tapFraction('{{ $frac }}')">{{ $frac }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <div class="keypad">
                    @foreach (['7','8','9','4','5','6','1','2','3'] as $d)
                        <div class="key" wire:click="tapDigit('{{ $d }}')">{{ $d }}</div>
                    @endforeach
                    @if ($modal === 'login')
                        <div class="key" style="visibility:hidden;">.</div>
                        <div class="key" wire:click="tapDigit('0')">0</div>
                        <div class="key" wire:click="backspace">&#9003;</div>
                    @else
                        <div class="key" wire:click="tapDecimal">.</div>
                        <div class="key" wire:click="tapDigit('0')">0</div>
                        <div class="key" wire:click="tapSlash">/</div>
                        <div class="key" wire:click="backspace" style="grid-column: span 3;">&#9003;</div>
                    @endif
                </div>

                @unless ($modal === 'login')
                    <div class="space-key" wire:click="tapSpace">SPACE</div>
                @endunless

                <div style="display:flex;gap:12px;">
                    @if ($modal === 'login')
                        <button class="btn btn-accent" style="flex:1;" wire:click="confirmModal">Sign In &rarr;</button>
                    @else
                        <button class="btn btn-outline" style="flex:1;" wire:click="closeModal">Cancel</button>
                        <button class="btn btn-accent" style="flex:1.4;" wire:click="confirmModal">
                            {{ $modal === 'stick' ? 'Build Cut Plan →' : 'Go →' }}
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif

    {{-- Cut-start toast: a preview of the label about to print, shown
         bottom-right (see Dashboard::announceCutStarted() /
         resources/css/cutflow.css .cut-toast*). Settings-gated. --}}
    <div class="cut-toast-stack" id="cut-toast-stack"></div>

    <script>
        function openPartPhoto() {
            document.getElementById('photo-lightbox')?.classList.add('open');
        }

        function closePartPhoto() {
            document.getElementById('photo-lightbox')?.classList.remove('open');
        }

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') closePartPhoto();
        });
    </script>

    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('cut-started', (event) => {
                const label = event.label || {};
                const stack = document.getElementById('cut-toast-stack');
                if (! stack) return;

                const toast = document.createElement('div');
                toast.className = 'cut-toast';

                const escapeHtml = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({
                    '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;',
                }[c]));

                const sizeLine = label.elevation
                    ? `<span class="elevation">${escapeHtml(label.elevation)}</span>${escapeHtml(label.size)}"`
                    : `${escapeHtml(label.size)}"`;

                toast.innerHTML = `
                    <div class="cut-toast-eyebrow"><span class="dot"></span> CUT STARTED &mdash; LABEL PREVIEW</div>
                    <div class="cut-toast-label">
                        <div class="text">
                            <div>
                                ${label.job ? `<div class="job">${escapeHtml(label.job)}</div>` : ''}
                                ${label.part ? `<div class="part">${escapeHtml(label.part)}</div>` : ''}
                                ${label.partUse ? `<div class="use">${escapeHtml(label.partUse)}</div>` : ''}
                            </div>
                            <div class="size-line">${sizeLine}</div>
                        </div>
                        <div class="cut-toast-qr"></div>
                    </div>
                `;

                stack.appendChild(toast);

                setTimeout(() => {
                    toast.classList.add('leaving');
                    setTimeout(() => toast.remove(), 200);
                }, 6000);
            });
        });
    </script>
</div>

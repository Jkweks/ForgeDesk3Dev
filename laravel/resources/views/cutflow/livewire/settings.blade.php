<div>
    <div class="topbar">
        <div style="display:flex;align-items:baseline;gap:9px;">
            <span class="display" style="font-weight:700;font-size:18px;">CutFlow</span>
            <span class="mono" style="font-size:10.5px;letter-spacing:.12em;color:var(--muted);">SETTINGS</span>
        </div>
        <a href="{{ route('cutflow.dashboard') }}">&larr; Back to Dashboard</a>
    </div>

    <div style="min-height:calc(100vh - 60px);display:flex;align-items:flex-start;justify-content:center;padding-top:60px;">
        <div class="card" style="width:480px;display:flex;flex-direction:column;gap:20px;">

            @if (! $unlocked)
                <div>
                    <h1 class="display" style="margin:0 0 8px;font-size:22px;">Admin PIN Required</h1>
                    <p style="margin:0 0 16px;font-size:13.5px;color:var(--muted-2);">Settings affect every operator on this tablet.</p>

                    @if ($pinError)
                        <div style="margin-bottom:12px;padding:10px 14px;border-radius:9px;background:var(--danger-bg);color:var(--danger);font-size:13px;font-weight:600;">{{ $pinError }}</div>
                    @endif

                    <div style="display:flex;gap:10px;">
                        <input type="password" wire:model="pin" wire:keydown.enter="unlock" inputmode="numeric" placeholder="PIN"
                               style="flex:1;padding:14px;border-radius:10px;border:1.5px solid var(--border);font-family:'IBM Plex Mono',monospace;font-size:16px;">
                        <button class="btn btn-accent" wire:click="unlock">Unlock</button>
                    </div>
                </div>
            @else
                <div>
                    <h1 class="display" style="margin:0 0 8px;font-size:22px;">Settings</h1>
                    @if (session('settings_status'))
                        <div style="margin-bottom:12px;padding:10px 14px;border-radius:9px;background:var(--success-bg);color:var(--success);font-size:13px;font-weight:600;">{{ session('settings_status') }}</div>
                    @endif
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--surface);border-radius:10px;">
                    <div>
                        <div style="font-size:14px;font-weight:600;">Cut Sensor Active</div>
                        <div style="font-size:12px;color:var(--muted);">Gate "Next Cut" on the physical cut-complete sensor instead of just the button press.</div>
                    </div>
                    <input type="checkbox" wire:model="cutSensorActive" style="width:22px;height:22px;">
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:var(--surface);border-radius:10px;">
                    <div>
                        <div style="font-size:14px;font-weight:600;">Show Cut-Start Toast</div>
                        <div style="font-size:12px;color:var(--muted);">Pop up a preview of the label in the bottom-right corner when a cut begins.</div>
                    </div>
                    <input type="checkbox" wire:model="showCutToast" style="width:22px;height:22px;">
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--muted-2);margin-bottom:6px;">Kerf (inches) &mdash; blank uses the config default</label>
                    <input type="number" step="0.001" wire:model="kerfInches" placeholder="0.125"
                           style="width:100%;padding:12px 14px;border-radius:9px;border:1.5px solid var(--border);font-family:'IBM Plex Mono',monospace;">
                </div>

                <div>
                    <label style="display:block;font-size:12px;font-weight:600;color:var(--muted-2);margin-bottom:6px;">Standard Stock Length (inches) &mdash; blank uses the config default</label>
                    <input type="number" step="0.001" wire:model="standardStockLength" placeholder="288"
                           style="width:100%;padding:12px 14px;border-radius:9px;border:1.5px solid var(--border);font-family:'IBM Plex Mono',monospace;">
                </div>

                <button class="btn btn-accent" wire:click="save">Save Settings</button>
            @endif
        </div>
    </div>
</div>

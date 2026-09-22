<x-cutflow.layout>
    <div style="min-height:100vh;display:flex;align-items:flex-start;justify-content:center;padding:40px 16px;">
        <div class="card" style="width:100%;max-width:420px;display:flex;flex-direction:column;gap:18px;">

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <span class="mono" style="font-size:10.5px;letter-spacing:.12em;color:var(--muted);font-weight:600;">CUT RECORD</span>
                <div style="display:flex;gap:6px;">
                    @if ($entry->is_recut)
                        <span class="badge" style="background:var(--danger-bg);color:var(--danger);">RECUT</span>
                    @endif
                    @if ($entry->is_reprint)
                        <span class="badge" style="background:var(--surface);color:var(--muted-2);">REPRINT</span>
                    @endif
                </div>
            </div>

            <div>
                @if ($entry->job_name)
                    <div class="mono" style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;">{{ $entry->job_name }}</div>
                @endif
                <div class="display" style="font-size:22px;font-weight:700;margin-top:2px;">
                    {{ $entry->finish ? "{$entry->part_name} · {$entry->finish}" : $entry->part_name }}
                </div>
                @if ($entry->description)
                    <div style="font-size:13.5px;color:var(--muted-2);margin-top:4px;">{{ $entry->description }}</div>
                @endif
            </div>

            <div style="padding:16px;border-radius:12px;background:var(--accent-bg);border:1.5px solid var(--accent-border);">
                <div class="mono" style="font-size:26px;font-weight:700;color:var(--accent);">
                    @if ($entry->phase)
                        <span style="color:var(--ink);font-weight:600;">{{ $entry->phase }}</span>
                    @endif
                    {{ number_format((float) $entry->dimension_inches, 3) }}"
                </div>
                @if ($entry->stick_length_label)
                    <div style="font-size:12px;color:var(--muted-2);margin-top:4px;">cut from a {{ $entry->stick_length_label }}" stick</div>
                @endif
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;">
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--muted);">Operator</span>
                    <span style="font-weight:600;">{{ $entry->operator_name ?? 'Unknown' }}</span>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--muted);">Cut at</span>
                    <span class="mono" style="font-weight:600;">{{ $entry->created_at?->format('n/j/y g:i:s A') }}</span>
                </div>
                @if ($entry->work_order)
                    <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                        <span style="color:var(--muted);">Work order</span>
                        <span class="mono" style="font-weight:600;">{{ $entry->work_order }}</span>
                    </div>
                @endif
                <div style="display:flex;align-items:center;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--muted);">Type</span>
                    <span style="font-weight:600;text-transform:capitalize;">{{ $entry->type }}</span>
                </div>
            </div>

            <div style="border-top:1px solid var(--border);padding-top:12px;">
                <div class="mono" style="font-size:10.5px;color:var(--muted);word-break:break-all;">{{ $entry->uuid }}</div>
            </div>

        </div>
    </div>
</x-cutflow.layout>

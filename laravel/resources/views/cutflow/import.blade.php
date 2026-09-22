<x-cutflow.layout>
    <div class="topbar">
        <div style="display:flex;align-items:baseline;gap:9px;">
            <span class="display" style="font-weight:700;font-size:18px;">CutFlow</span>
            <span class="mono" style="font-size:10.5px;letter-spacing:.12em;color:var(--muted);">SHOP FLOOR</span>
        </div>
        <a href="{{ route('cutflow.dashboard') }}">Skip to dashboard &rarr;</a>
    </div>

    <div style="min-height:calc(100vh - 60px);display:flex;align-items:center;justify-content:center;">
        <div class="card" style="width:560px;display:flex;flex-direction:column;gap:22px;">
            <div>
                <h1 class="display" style="margin:0 0 8px;font-size:26px;">Import Cut List</h1>
                <p style="margin:0;font-size:14px;line-height:1.5;color:var(--muted-2);">
                    Upload the optimized cut list exported from your job. CutFlow builds
                    today's part queue from it.
                </p>
            </div>

            @if (session('status'))
                <div style="padding:12px 16px;border-radius:10px;background:var(--success-bg);color:var(--success);font-size:13.5px;font-weight:600;">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('cutflow.import.store') }}" enctype="multipart/form-data" style="display:flex;flex-direction:column;gap:18px;">
                @csrf
                <input type="file" name="csv" accept=".csv,.txt" required
                       style="padding:14px;border:2px dashed var(--faint);border-radius:12px;background:var(--surface);">
                @error('csv')
                    <div style="color:var(--danger);font-size:12.5px;">{{ $message }}</div>
                @enderror

                <div style="display:flex;flex-direction:column;gap:6px;padding:14px 16px;background:var(--surface);border-radius:10px;">
                    <div style="font-size:11px;letter-spacing:.08em;color:var(--muted);font-weight:600;">EXPECTED COLUMNS</div>
                    <div class="mono" style="font-size:12.5px;color:var(--text-2);">part_id &middot; finish &middot; dimension_in &middot; qty</div>
                    <div class="mono" style="font-size:11px;color:var(--muted);">optional: job &middot; work_order &middot; phase &middot; description &middot; row &middot; column &middot; leftcutangle &middot; rightcutangle</div>
                    <div style="font-size:11.5px;color:var(--muted-2);margin-top:4px;">All rows in one file become one job (the CSV's <span class="mono">job</span> column, or the filename if that's blank). Import a second file to add another job &mdash; you can select multiple jobs at once on the dashboard.</div>
                </div>

                <button type="submit" class="btn btn-accent" style="width:100%;">Parse &amp; Import &rarr;</button>
            </form>
        </div>
    </div>
</x-cutflow.layout>

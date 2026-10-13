<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Sill Quarter Points</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 9px; margin: 20px; color: #333; }
        .header { border-bottom: 2px solid #333; padding-bottom: 8px; margin-bottom: 12px; }
        .header h1 { margin: 0 0 3px 0; font-size: 16px; }
        .header .sub { color: #666; font-size: 9px; }
        .elev { margin-bottom: 14px; page-break-inside: avoid; }
        .elev h2 { font-size: 12px; margin: 0 0 3px 0; background: #eee; padding: 4px 6px; }
        .meta { font-size: 8px; color: #555; margin: 0 0 4px 0; }
        .run-title { font-weight: bold; margin: 6px 0 2px 0; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #666; color: #fff; padding: 3px 4px; font-size: 7.5px; border: 1px solid #555; text-align: right; }
        td { padding: 3px 4px; border: 1px solid #dee2e6; text-align: right; font-size: 8px; }
        th.l, td.l { text-align: left; }
        td small { color: #777; display: block; font-size: 7px; }
        .receptor { font-size: 8px; margin: 3px 0 0 0; }
        .flag { color: #c0392b; font-weight: bold; }
        .ok { color: #2e7d32; }
        h3 { font-size: 11px; margin: 14px 0 4px 0; border-bottom: 1px solid #ccc; }
        ul { margin: 0 0 0 16px; padding: 0; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SILL QUARTER-POINT REPORT</h1>
        <div class="sub">{{ $title }} &middot; generated {{ now()->format('F d, Y H:i') }}</div>
        <div class="sub">Measured left to right from the left edge of the assembly. All values in inches.
            Left gap {{ $report['constants']['left_gap'] }}", right gap {{ $report['constants']['right_gap'] }}",
            receptor gap {{ $report['constants']['receptor_gap'] }}".</div>
    </div>

    @foreach ($report['elevations'] as $el)
        <div class="elev">
            <h2>{{ $el['project'] }} &middot; {{ $el['work_order'] }} &middot; Elevation {{ $el['elevation'] }}</h2>
            <p class="meta">
                Left jamb {{ $el['left_jamb']['pn'] }} ({{ $el['left_jamb']['width'] }}"{{ $el['left_jamb']['source'] === 'default' ? ', default' : '' }}) &middot;
                Right jamb {{ $el['right_jamb']['pn'] }} ({{ $el['right_jamb']['width'] }}"{{ $el['right_jamb']['source'] === 'default' ? ', default' : '' }}) &middot;
                Vertical {{ implode(', ', $el['vertical_pns']) ?: '—' }}
                @if ($el['breaks'])
                    <br>Breaks: {{ implode('; ', $el['breaks']) }}
                @endif
            </p>

            @foreach ($el['runs'] as $run)
                <div class="run-title">{{ $run['label'] }} <span style="font-weight: normal; color: #666;">(starts at {{ $run['start_kind'] }}, ends at {{ $run['end_kind'] }})</span></div>
                <table>
                    <thead>
                        <tr>
                            <th class="l">Bay</th><th class="l">Sill</th><th>Length</th><th>Start</th><th>End</th><th>1/4 point</th><th>3/4 point</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($run['sills'] as $s)
                            <tr>
                                <td class="l">{{ $s['bay'] }}</td>
                                <td class="l">{{ $s['pn'] }}</td>
                                <td>{{ $s['len'] }}<small>{{ $s['len_frac'] }}</small></td>
                                <td>{{ $s['start'] }}<small>{{ $s['start_frac'] }}</small></td>
                                <td>{{ $s['end'] }}<small>{{ $s['end_frac'] }}</small></td>
                                <td><strong>{{ $s['q1'] }}</strong><small>{{ $s['q1_frac'] }}</small></td>
                                <td><strong>{{ $s['q3'] }}</strong><small>{{ $s['q3_frac'] }}</small></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="receptor">
                    @if ($run['receptor'])
                        Receptor check: layout {{ $run['layout_dec'] }}" vs receptor {{ $run['receptor']['receptor_dec'] }}"
                        ({{ implode(' + ', $run['receptor']['pieces']) }})
                        : <span class="{{ $run['receptor']['flagged'] ? 'flag' : 'ok' }}">difference {{ $run['receptor']['diff_dec'] }}"{{ $run['receptor']['flagged'] ? ' — over 1/16"' : '' }}</span>
                    @else
                        Receptor check: layout {{ $run['layout_dec'] }}" — no receptor piece matched.
                    @endif
                </p>
            @endforeach
        </div>
    @endforeach

    <div style="page-break-before: always;"></div>
    <div class="header">
        <h1>SILL RECEPTOR SUMMARY</h1>
        <div class="sub">Each receptor piece with the sill 1/4 and 3/4 points that land on it, measured from that piece's left end and rounded to the nearest 1/16".
            Split runs are laid out largest piece first with the {{ $report['constants']['receptor_gap'] }}" receptor gap between pieces.</div>
    </div>
    @foreach ($report['elevations'] as $el)
        @php $runsWithReceptor = array_filter($el['runs'], fn ($r) => $r['on_receptor']); @endphp
        @if ($runsWithReceptor)
            <div class="elev">
                <h2>{{ $el['project'] }} &middot; {{ $el['work_order'] }} &middot; Elevation {{ $el['elevation'] }}</h2>
                @foreach ($runsWithReceptor as $run)
                    @foreach ($run['on_receptor']['pieces'] as $piece)
                        <div class="run-title">{{ $run['label'] }} &middot; receptor piece {{ $piece['no'] }} of {{ count($run['on_receptor']['pieces']) }}: {{ $piece['len'] }}" <span style="font-weight: normal; color: #666;">({{ $piece['len_frac'] }})</span></div>
                        <table>
                            <thead><tr><th class="l">Bay</th><th class="l">Mark</th><th>From left end of piece</th></tr></thead>
                            <tbody>
                                @forelse ($piece['marks'] as $m)
                                    <tr><td class="l">{{ $m['bay'] }}</td><td class="l">{{ $m['mark'] }}</td><td>{{ $m['dec'] }}<small>{{ $m['frac'] }}</small></td></tr>
                                @empty
                                    <tr><td class="l" colspan="3">No marks on this piece.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    @endforeach
                    @if ($run['on_receptor']['off'])
                        <p class="receptor flag">Not on any receptor piece (layout vs receptor mismatch):
                            @foreach ($run['on_receptor']['off'] as $o) bay {{ $o['bay'] }} {{ $o['mark'] }} @ {{ $o['pos'] }}"{{ $loop->last ? '' : ';' }} @endforeach
                        </p>
                    @endif
                @endforeach
            </div>
        @endif
    @endforeach

    <h3>Needs review</h3>
    @if ($report['needs_review'])
        <ul>@foreach ($report['needs_review'] as $r)<li><strong>{{ $r['label'] }}</strong> skipped: {{ $r['reason'] }}</li>@endforeach</ul>
    @else
        <p>None.</p>
    @endif

    <h3>Assumptions to confirm</h3>
    @if ($report['assumptions'] || $report['no_sills'])
        <ul>
            @foreach ($report['assumptions'] as $a)<li>{{ $a }}</li>@endforeach
            @foreach ($report['no_sills'] as $l)<li>{{ $l }}: no SILL rows (nothing to compute).</li>@endforeach
        </ul>
    @else
        <p>None.</p>
    @endif

    <h3>Receptor differences over 1/16"</h3>
    @if ($report['receptor_flags'])
        <ul>
            @foreach ($report['receptor_flags'] as $f)
                <li>{{ $f['elevation'] }} {{ $f['run'] }}: receptor {{ $f['receptor'] }}" vs layout {{ $f['layout'] }}" (difference {{ $f['diff'] }}")</li>
            @endforeach
        </ul>
    @else
        <p>None.</p>
    @endif
</body>
</html>

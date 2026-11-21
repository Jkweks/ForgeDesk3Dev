<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  /* 4in x 6in (288 x 432pt), one door leaf per page. Hand = fill vs outline so it reads on thermal. */
  @page { size: 288pt 432pt; margin: 0; }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #000; }
  .page { position: relative; width: 248pt; height: 394pt; padding: 18pt 20pt; page-break-after: always; overflow: hidden; }
  .page.last { page-break-after: auto; }
  .job { font-size: 11pt; color: #333; border-bottom: 1.5pt solid #000; padding-bottom: 6pt; height: 26pt; overflow: hidden; white-space: nowrap; }
  .tag { font-size: 46pt; font-weight: bold; line-height: 1; margin-top: 12pt; height: 54pt; overflow: hidden; }
  .hand { margin-top: 8pt; height: 40pt; }
  .badge { display: inline-block; border: 2.5pt solid #000; padding: 3pt 14pt; font-size: 26pt; font-weight: bold; }
  .badge.l { background: #000; color: #fff; }
  .badge.r { background: #fff; color: #000; }
  .inswing { display: inline-block; font-size: 14pt; font-weight: bold; margin-left: 10pt; }
  table.chk { width: 100%; margin-top: 16pt; border-collapse: collapse; }
  table.chk td { padding: 5pt 0; font-size: 15pt; vertical-align: middle; border-bottom: .5pt solid #999; }
  td.box { width: 26pt; }
  .sq { display: inline-block; width: 16pt; height: 16pt; border: 2pt solid #000; }
  .sign { position: absolute; left: 20pt; bottom: 14pt; font-size: 13pt; }
  .line { display: inline-block; width: 190pt; border-bottom: 1.5pt solid #000; height: 14pt; }
</style>
</head>
<body>
@foreach ($labels as $l)
  <div class="page {{ $loop->last ? 'last' : '' }}">
    <div class="job">{{ $l['job'] }}</div>
    @php
      // Bold caps run ~0.7em wide: shrink to fit one line, then fall back to two small lines.
      $len = max(1, mb_strlen($l['door']));
      $fit = 248 / ($len * 0.7);
      $tagSize = $fit >= 22 ? min(46, $fit) : 21;
    @endphp
    <div class="tag" style="font-size: {{ round($tagSize, 1) }}pt; {{ $fit >= 22 ? 'white-space: nowrap;' : 'line-height: 1.05;' }}">{{ $l['door'] }}</div>
    <div class="hand">
      @if ($l['hand'])
        <span class="badge {{ strtolower($l['side']) }}">{{ $l['hand'] }}{{ $l['pair'] ? '-PAIR' : '' }}</span>
      @endif
      @if ($l['inswing'])<span class="inswing">INSWING</span>@endif
    </div>
    <table class="chk">
      @foreach ($l['items'] as $item)
        <tr><td class="box"><span class="sq"></span></td><td>{{ $item }}</td></tr>
      @endforeach
    </table>
    <div class="sign">Initial <span class="line"></span></div>
  </div>
@endforeach
</body>
</html>

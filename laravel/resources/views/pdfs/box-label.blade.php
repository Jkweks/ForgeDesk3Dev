<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  /* 4in x 6in (288 x 432pt) box label. */
  @page { size: 288pt 432pt; margin: 0; }
  * { margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #000; }
  .page { position: relative; width: 248pt; height: 394pt; padding: 18pt 20pt; overflow: hidden; }
  .path { font-size: 9pt; color: #333; height: 14pt; overflow: hidden; white-space: nowrap; }
  .name { font-size: 40pt; font-weight: bold; line-height: 1; height: 46pt; overflow: hidden; border-bottom: 2pt solid #000; padding-bottom: 6pt; margin-top: 2pt; }
  table.parts { width: 100%; margin-top: 8pt; border-collapse: collapse; }
  table.parts th { font-size: 9pt; text-align: left; border-bottom: 1pt solid #000; padding-bottom: 2pt; }
  table.parts td { font-size: 15pt; padding: 4pt 0; border-bottom: .5pt solid #999; }
  .home { font-size: 8.5pt; font-weight: normal; color: #333; margin-top: 1pt; }
  .qty { text-align: right; font-weight: bold; }
  .more, .empty { font-size: 11pt; margin-top: 6pt; color: #333; }
  .qr { position: absolute; right: 20pt; bottom: 18pt; width: 96pt; height: 96pt; }
  .scan { position: absolute; left: 20pt; bottom: 18pt; font-size: 10pt; width: 120pt; }
</style>
</head>
<body>
<div class="page">
  <div class="path">{{ $path }}</div>
  @php
    $len = max(1, mb_strlen($name));
    $size = max(18, min(40, 248 / ($len * 0.7)));
  @endphp
  <div class="name" style="font-size: {{ round($size, 1) }}pt; white-space: nowrap;">{{ $name }}</div>

  @if (count($items))
    <table class="parts">
      <tr><th>PART NUMBER</th><th class="qty">QTY</th></tr>
      @foreach ($items as $it)
        <tr><td>{{ $it['sku'] }}@if ($it['home'])<div class="home">home: {{ $it['home'] }}</div>@endif</td><td class="qty">{{ $it['qty'] }}</td></tr>
      @endforeach
    </table>
    @if ($more > 0)<div class="more">+ {{ $more }} more — scan for full contents</div>@endif
  @else
    <div class="empty">No parts assigned to this box.</div>
  @endif

  <div class="scan">Scan for contents{{ $code ? ' · '.$code : '' }}</div>
  <img class="qr" src="{{ $qr }}">
</div>
</body>
</html>

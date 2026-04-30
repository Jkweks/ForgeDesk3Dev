@php
    $count = count($items);
    // Choose column count to maximise image area
    $cols = match(true) {
        $count <= 3 => max($count, 1),
        $count === 4 => 2,
        default     => 3,   // 5 or 6 items
    };
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  /*
   * Page: letter landscape = 792 × 612 pt
   * Label: 9 × 6 in = 648 × 432 pt
   * Centering margins: left/right = (792-648)/2 = 72pt (1in)
   *                   top/bottom  = (612-432)/2 = 90pt (1.25in)
   */
  body {
    width: 792pt;
    height: 612pt;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    background: #fff;
    margin: 0;
    padding: 90pt 72pt;
  }

  .label {
    width: 648pt;
    height: 432pt;
    border: 3pt solid #000;
    padding: 7pt;
  }

  /* Outer two-row table: items on top, shelf-id strip on bottom */
  .outer {
    width: 100%;
    height: 418pt;   /* 432 - 2×7 label padding */
    border-collapse: collapse;
  }

  .outer .items-row { height: 330pt; vertical-align: top; }
  .outer .gap-row   { height: 6pt; }
  .outer .id-row    { height: 82pt; vertical-align: middle; }

  /* Inner grid of item cells */
  .items-table {
    width: 100%;
    height: 100%;
    border-collapse: separate;
    border-spacing: 5pt;
  }

  .item {
    border: 2pt solid #000;
    text-align: center;
    vertical-align: top;
    padding: 5pt;
    width: {{ round(100 / $cols, 2) }}%;
  }

  .item img {
    width: 100%;
    max-height: 255pt;
    object-fit: contain;
    filter: grayscale(100%) contrast(130%);
  }

  .no-image {
    height: 240pt;
    color: #aaa;
    font-size: 10pt;
    line-height: 240pt;
    text-align: center;
  }

  .sku {
    border-top: 2pt solid #000;
    margin-top: 5pt;
    padding-top: 4pt;
    font-weight: bold;
    font-size: 16pt;
    word-break: break-all;
    text-align: center;
  }

  /* Shelf ID strip */
  .shelf-id {
    border: 2pt solid #000;
    text-align: right;
    font-weight: 900;
    font-size: 58pt;
    line-height: 1;
    padding: 8pt 12pt;
    vertical-align: middle;
    letter-spacing: 0.01em;
  }
</style>
</head>
<body>
<div class="label">
  <table class="outer">
    <tr class="items-row">
      <td>
        @if ($count === 0)
          <div style="text-align:center;color:#aaa;padding-top:100pt;font-size:12pt;">No products at this location</div>
        @else
          <table class="items-table">
            @foreach (array_chunk($items, $cols) as $row)
              <tr>
                @foreach ($row as $item)
                  <td class="item">
                    @if ($item['photo_data'])
                      <img src="{{ $item['photo_data'] }}" alt="{{ $item['sku'] }}">
                    @else
                      <div class="no-image">No Image</div>
                    @endif
                    <div class="sku">{{ $item['sku'] }}</div>
                  </td>
                @endforeach
                {{-- Pad incomplete last row so columns stay even width --}}
                @if (count($row) < $cols)
                  @for ($p = 0; $p < $cols - count($row); $p++)
                    <td class="item" style="border:2pt solid #ddd;"></td>
                  @endfor
                @endif
              </tr>
            @endforeach
          </table>
        @endif
      </td>
    </tr>
    <tr class="gap-row"><td></td></tr>
    <tr class="id-row">
      <td class="shelf-id">{{ $shelf_id }}</td>
    </tr>
  </table>
</div>
</body>
</html>

@php
    $count = count($items);
    $cols = match(true) {
        $count <= 3 => max($count, 1),
        $count === 4 => 2,
        default     => 3,
    };
    // Fixed heights in pt (letter landscape = 792×612pt)
    // @page margins centre the 9×6in (648×432pt) label
    // Interior = 648-14 = 634pt wide, 432-14 = 418pt tall
    $idRowHeight   = 82;
    $gapHeight     = 6;
    $itemsHeight   = 418 - $idRowHeight - $gapHeight;  // 330pt
    $imageHeight   = $itemsHeight - 35;                 // ~295pt, leaves room for SKU row
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page {
    size: 792pt 612pt;   /* letter landscape */
    margin: 90pt 72pt;   /* centres 648×432pt label on page */
  }

  * { margin: 0; padding: 0; box-sizing: border-box;
      -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  body {
    width: 648pt;
    font-family: Calibri, Arial, Helvetica, sans-serif;
    color: #000;
    background: #fff;
  }

  .label {
    width: 648pt;
    height: 432pt;
    border: 3pt solid #000;
    padding: 7pt;
    overflow: hidden;
    page-break-inside: avoid;
  }

  .outer {
    width: 634pt;   /* 648 - 2×7 */
    height: 418pt;  /* 432 - 2×7 */
    border-collapse: collapse;
    page-break-inside: avoid;
  }

  .items-row { height: {{ $itemsHeight }}pt; vertical-align: top; }
  .gap-row   { height: {{ $gapHeight }}pt; }
  .id-row    { height: {{ $idRowHeight }}pt; vertical-align: middle; }

  .items-table {
    width: 100%;
    height: {{ $itemsHeight }}pt;
    border-collapse: separate;
    border-spacing: 5pt;
    page-break-inside: avoid;
  }

  .items-table tr { page-break-inside: avoid; }

  .item {
    border: 2pt solid #000;
    text-align: center;
    vertical-align: top;
    padding: 5pt;
    width: {{ round(100 / $cols, 2) }}%;
    height: {{ $itemsHeight }}pt;
    overflow: hidden;
  }

  .item img {
    width: 100%;
    height: {{ $imageHeight }}pt;
    object-fit: contain;
    filter: grayscale(100%) contrast(130%);
    display: block;
  }

  .no-image {
    width: 100%;
    height: {{ $imageHeight }}pt;
    color: #aaa;
    font-size: 10pt;
    text-align: center;
    display: block;
  }

  .sku {
    border-top: 2pt solid #000;
    margin-top: 4pt;
    padding-top: 3pt;
    font-weight: bold;
    font-size: 16pt;
    line-height: 1.1;
    word-break: break-all;
    text-align: center;
  }

  .shelf-id {
    border: 2pt solid #000;
    text-align: right;
    font-weight: 900;
    font-size: 58pt;
    line-height: 1;
    padding: 8pt 12pt;
    vertical-align: middle;
    letter-spacing: 0.01em;
    height: {{ $idRowHeight }}pt;
    overflow: hidden;
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

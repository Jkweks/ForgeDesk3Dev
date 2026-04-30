@php
    $count = count($items);
    // Choose column count to maximise image area
    $cols = match(true) {
        $count <= 3 => max($count, 1),
        $count === 4 => 2,
        default     => 3,          // 5 or 6 items
    };
@endphp
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  body {
    width: 612pt;
    height: 396pt;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    background: #fff;
    overflow: hidden;
  }

  .label {
    width: 612pt;
    height: 396pt;
    border: 3pt solid #000;
    padding: 6pt;
  }

  /* Outer two-row table: items on top, shelf-id strip on bottom */
  .outer {
    width: 100%;
    height: 384pt;   /* 396 - 2×6 label padding */
    border-collapse: collapse;
  }

  .outer .items-row { height: 303pt; vertical-align: top; }
  .outer .gap-row   { height: 5pt; }
  .outer .id-row    { height: 76pt; vertical-align: middle; }

  /* Inner grid of item cells */
  .items-table {
    width: 100%;
    height: 100%;
    border-collapse: separate;
    border-spacing: 4pt;
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
    max-height: 220pt;
    object-fit: contain;
    filter: grayscale(100%) contrast(130%);
  }

  .no-image {
    height: 200pt;
    color: #aaa;
    font-size: 10pt;
    line-height: 200pt;
    text-align: center;
  }

  .sku {
    border-top: 2pt solid #000;
    margin-top: 5pt;
    padding-top: 4pt;
    font-weight: bold;
    font-size: 15pt;
    word-break: break-all;
    text-align: center;
  }

  /* Shelf ID strip */
  .shelf-id {
    border: 2pt solid #000;
    text-align: right;
    font-weight: 900;
    font-size: 52pt;
    line-height: 1;
    padding: 6pt 10pt;
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
          <div style="text-align:center;color:#aaa;padding-top:80pt;font-size:12pt;">No products at this location</div>
        @else
          <table class="items-table">
            @foreach (array_chunk($items, $cols) as $rowIndex => $row)
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

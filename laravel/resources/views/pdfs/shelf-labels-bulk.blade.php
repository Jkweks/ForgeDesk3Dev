<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  @page {
    size: 792pt 612pt;
    margin: 90pt 72pt;
  }

  * { margin: 0; padding: 0; box-sizing: border-box;
      -webkit-print-color-adjust: exact; print-color-adjust: exact; }

  body {
    font-family: Calibri, Arial, Helvetica, sans-serif;
    color: #000;
    background: #fff;
  }

  .label {
    width: 648pt;
    height: 432pt;
    border: 2pt dashed #aaa;
    padding: 7pt;
    overflow: hidden;
  }

  .page-break { page-break-after: always; }

  .outer {
    width: 634pt;
    height: 418pt;
    border-collapse: collapse;
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
    overflow: hidden;
  }

  .item {
    border: 2pt solid #000;
    text-align: center;
    vertical-align: top;
    padding: 5pt;
    overflow: hidden;
  }

  .item img {
    max-width: 100%;
    width: auto;
    height: auto;
    display: block;
    margin: 0 auto;
    filter: grayscale(100%) contrast(130%);
  }

  .no-image {
    width: 100%;
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

  .items-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 5pt;
  }
</style>
</head>
<body>
@foreach ($labels as $i => $label)
@php
    $count      = count($label['items']);
    $cols       = match(true) {
        $count <= 3 => max($count, 1),
        $count === 4 => 2,
        default      => 3,
    };
    $idRowHeight    = 82;
    $gapHeight      = 6;
    $itemsHeight    = 418 - $idRowHeight - $gapHeight;
    $rows           = max(1, (int) ceil($count / max($cols, 1)));
    $spacing        = 5;
    $itemCellHeight = ($itemsHeight - ($rows - 1) * $spacing) / $rows;
    $imageHeight    = $itemCellHeight - 35;
    $isLast         = $i === count($labels) - 1;
@endphp
<div class="label{{ $isLast ? '' : ' page-break' }}">
  <table class="outer">
    <tr style="height: {{ $itemsHeight }}pt; vertical-align: top;">
      <td>
        @if ($count === 0)
          <div style="text-align:center;color:#aaa;padding-top:100pt;font-size:12pt;">No products at this location</div>
        @else
          <table class="items-table" style="height: {{ $itemsHeight }}pt;">
            @foreach (array_chunk($label['items'], $cols) as $row)
              <tr>
                @foreach ($row as $item)
                  <td class="item" style="width: {{ round(100 / $cols, 2) }}%; height: {{ $itemCellHeight }}pt;">
                    @if ($item['photo_data'])
                      <img src="{{ $item['photo_data'] }}" alt="{{ $item['sku'] }}" style="max-height: {{ $imageHeight }}pt;">
                    @else
                      <div class="no-image" style="height: {{ $imageHeight }}pt;">No Image</div>
                    @endif
                    <div class="sku">{{ $item['sku'] }}</div>
                  </td>
                @endforeach
                @if (count($row) < $cols)
                  @for ($p = 0; $p < $cols - count($row); $p++)
                    <td class="item" style="width: {{ round(100 / $cols, 2) }}%; height: {{ $itemCellHeight }}pt; border: 2pt solid #ddd;"></td>
                  @endfor
                @endif
              </tr>
            @endforeach
          </table>
        @endif
      </td>
    </tr>
    <tr style="height: {{ $gapHeight }}pt;"><td></td></tr>
    <tr style="height: {{ $idRowHeight }}pt; vertical-align: middle;">
      <td class="shelf-id">{{ $label['shelf_id'] }}</td>
    </tr>
  </table>
</div>
@endforeach
</body>
</html>

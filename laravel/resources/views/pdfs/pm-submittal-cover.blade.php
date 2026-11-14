<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
  body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #1d2733; }
  h1 { font-size: 24px; margin: 0 0 4px; }
  h2 { font-size: 14px; margin: 24px 0 6px; border-bottom: 1px solid #999; padding-bottom: 3px; }
  table { width: 100%; border-collapse: collapse; }
  td, th { padding: 4px 6px; text-align: left; vertical-align: top; }
  th { width: 150px; color: #555; font-weight: normal; }
  .toc td { border-bottom: 1px solid #ddd; }
</style>
</head>
<body>
  <h1>Submittal {{ $submittal->number }}@if ($submittal->revision_number) — Revision {{ $submittal->revision_number }}@endif</h1>
  <div>{{ $submittal->name }}</div>

  <h2>Project</h2>
  <table>
    <tr><th>Job</th><td>{{ $job->job_number }} — {{ $job->job_name }}</td></tr>
    @if ($job->customer_name)<tr><th>Customer / GC</th><td>{{ $job->customer_name }}</td></tr>@endif
    @if ($job->site_address)<tr><th>Site</th><td>{{ $job->site_address }}</td></tr>@endif
    @if ($submittal->spec_section)<tr><th>Spec section</th><td>{{ $submittal->spec_section }}</td></tr>@endif
    <tr><th>Prepared by</th><td>{{ $preparedBy }}</td></tr>
    <tr><th>Date</th><td>{{ $date }}</td></tr>
  </table>

  <h2>Contents</h2>
  <table class="toc">
    @foreach ($items as $i => $item)
      <tr><td style="width:30px">{{ $i + 1 }}</td><td>{{ $item->label }}</td></tr>
    @endforeach
  </table>
</body>
</html>

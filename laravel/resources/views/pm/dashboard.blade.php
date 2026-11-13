@extends('layouts.app')

@section('title', 'Project Management - ForgeDesk')

@section('content')
<div class="page-wrapper">
  <div class="page-header d-print-none">
    <div class="container-xl">
      <div class="page-pretitle">Project Management</div>
      <h1 class="page-title">Dashboard</h1>
    </div>
  </div>
  <main id="content" class="page-body">
    <div class="container-xl">
      <div class="row row-deck row-cards mb-3" id="pmStageCards">
        @foreach (['bidding' => 'Bidding', 'won' => 'Won', 'active' => 'Active'] as $key => $label)
          <div class="col-sm-4">
            <a href="/pm/jobs?status={{ $key }}" class="card card-link">
              <div class="card-body">
                <div class="subheader">{{ $label }} jobs</div>
                <div class="h1 mb-0" id="pmCount-{{ $key }}">-</div>
              </div>
            </a>
          </div>
        @endforeach
      </div>
      <div class="card">
        <div class="card-body">
          <p class="mb-0 text-muted">Jobs are shared with Fabrication once they are activated. Submittals, purchasing and the rest of the Project Management tools will appear here as they are added.</p>
        </div>
      </div>
    </div>
  </main>
</div>
<script>
  document.addEventListener('DOMContentLoaded', async () => {
    try {
      const jobs = await authenticatedFetch('/pm/jobs');
      ['bidding', 'won', 'active'].forEach(k => {
        document.getElementById('pmCount-' + k).textContent = jobs.filter(j => j.status === k).length;
      });
    } catch (e) { console.error(e); }
  });
</script>
@endsection

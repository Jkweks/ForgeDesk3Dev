{{-- Shared stage-status vocabulary for the fabrication screens.
     One colour treatment per status, with an explicit dark-mode pair for each
     (Tabler's own dark handling only covers solid bg-* badges). Pairs with the
     FabStage.LABEL / FabStage.className helpers in public/js/fab-shared.js. --}}
<style>
  .fab-stage {
    display: inline-flex; align-items: center; gap: .25rem;
    padding: .2rem .5rem; border-radius: 6px;
    font-size: .75rem; font-weight: 600; line-height: 1.2;
    border: 1px solid transparent; white-space: nowrap;
  }

  .fab-stage.pending      { background: #e9ecef; color: #495057; }
  .fab-stage.in_progress  { background: #fff3cd; color: #664d03; }
  .fab-stage.complete     { background: #d1e7dd; color: #0a3622; }
  .fab-stage.blocked      { background: #f8d7da; color: #58151c; }
  .fab-stage.not_required { background: #dce7f9; color: #2c5fc3; }
  .fab-stage.on_hold      { background: #fde3c4; color: #7a3f00; border-style: dashed; border-color: #b96a00; }

  [data-bs-theme="dark"] .fab-stage.pending      { background: #343a40; color: #c5ccd3; }
  [data-bs-theme="dark"] .fab-stage.in_progress  { background: #3d2e00; color: #ffc107; }
  [data-bs-theme="dark"] .fab-stage.complete     { background: #12281c; color: #8fd0ad; }
  [data-bs-theme="dark"] .fab-stage.blocked      { background: #3a1417; color: #ef9aa1; }
  [data-bs-theme="dark"] .fab-stage.not_required { background: #0d1f3c; color: #7aa7e9; }
  [data-bs-theme="dark"] .fab-stage.on_hold      { background: #3d2503; color: #f5a623; border-color: #f5a623; }
</style>

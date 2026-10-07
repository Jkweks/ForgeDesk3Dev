{{-- Shared stage-status vocabulary for the fabrication screens.
     One colour treatment per status, built from Tabler's theme tokens so it follows
     light/dark mode and the user's theme choices without per-mode colour pairs.
     Text is the status colour mixed toward the body colour to keep contrast on the
     light tint. Pairs with the FabStage.LABEL / FabStage.className helpers in
     public/js/fab-shared.js. --}}
<style>
  .fab-stage {
    --fab-stage-color: var(--tblr-secondary);
    display: inline-flex; align-items: center; gap: .25rem;
    padding: .2rem .5rem; border-radius: var(--tblr-border-radius);
    font-size: .75rem; font-weight: 600; line-height: 1.2;
    border: 1px solid transparent; white-space: nowrap;
    background: color-mix(in oklab, var(--fab-stage-color) 14%, transparent);
    color: color-mix(in oklab, var(--fab-stage-color) 70%, var(--tblr-body-color));
  }

  .fab-stage.pending      { --fab-stage-color: var(--tblr-secondary); }
  .fab-stage.in_progress  { --fab-stage-color: var(--tblr-yellow); }
  .fab-stage.complete     { --fab-stage-color: var(--tblr-green); }
  .fab-stage.blocked      { --fab-stage-color: var(--tblr-red); }
  .fab-stage.not_required { --fab-stage-color: var(--tblr-blue); }
  .fab-stage.on_hold      { --fab-stage-color: var(--tblr-orange); border-style: dashed; border-color: var(--fab-stage-color); }
</style>

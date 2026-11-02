<script>
  // Shared helpers for the Shipments pages (builds on the fleet helpers: flModal, flApi, flField, ...).
  const SH = {
    STATUS: {
      draft: ['Draft', 'secondary'], pre_check_done: ['Pre-check done', 'blue'], shipped: ['Shipped', 'cyan'],
      delivered: ['Delivered', 'success'], delivered_issues: ['Delivered – issues', 'danger'], cancelled: ['Cancelled', 'secondary'],
    },
    RESULT: { pass: ['Pass', 'success'], pass_with_issues: ['Pass with issues', 'warning'], fail: ['Fail', 'danger'] },
    INPUTS: { pass_fail: 'Pass / Fail / N/A', number: 'Number', text: 'Text', choice: 'Choice list', photo: 'Photos' },
  };
  const shStatus = s => { const m = SH.STATUS[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const shResult = s => { const m = SH.RESULT[s] || [s, 'secondary']; return flBadge(m[0], m[1]); };
  const shPhotoUrl = (shipmentId, photoId) => `/api/v1/shipments/${shipmentId}/photos/${photoId}`;
</script>

<?php
$adminTitle = 'Activity';
$adminSubtitle = 'A clean audit trail of order movements and updates.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Activity</h2>
  <div class="mt-3 hidden grid-cols-[1fr_0.9fr_1.4fr] gap-3 border-b pb-2 text-xs font-medium text-muted-foreground md:grid">
    <span>Reference</span><span>Status</span><span>Note</span>
  </div>
  <ul id="admin-activity" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

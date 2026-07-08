<?php
$adminTitle = 'Activity';
$adminSubtitle = 'A clean audit trail of order movements and updates.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Activity</h2>
  <ul id="admin-activity" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

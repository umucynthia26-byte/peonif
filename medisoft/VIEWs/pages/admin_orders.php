<?php
$adminTitle = 'Orders';
$adminSubtitle = 'Every order here is paid. Mark delivered when the customer receives flowers.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Orders</h2>
  <ul id="admin-orders" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

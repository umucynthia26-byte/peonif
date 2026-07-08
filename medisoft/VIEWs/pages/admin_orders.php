<?php
$adminTitle = 'Orders';
$adminSubtitle = 'Every order here is paid. Mark delivered when the customer receives flowers.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Orders</h2>
  <div class="mt-3 hidden grid-cols-[1.1fr_1fr_0.9fr_0.8fr_0.8fr] gap-3 border-b pb-2 text-xs font-medium text-muted-foreground md:grid">
    <span>Reference</span><span>Customer</span><span>Delivery</span><span>Total</span><span>Status</span>
  </div>
  <ul id="admin-orders" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

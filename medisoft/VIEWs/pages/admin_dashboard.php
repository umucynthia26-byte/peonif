<?php
$adminTitle = 'Dashboard';
$adminSubtitle = 'Your boutique at a glance.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="grid grid-cols-2 gap-4 xl:grid-cols-5">
  <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Revenue</p><p id="admin-stat-revenue" class="font-heading text-2xl">$0</p></div>
  <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Orders</p><p id="admin-stat-orders" class="font-heading text-2xl">0</p></div>
  <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">To deliver</p><p id="admin-stat-active" class="font-heading text-2xl">0</p></div>
  <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Products</p><p id="admin-stat-products" class="font-heading text-2xl">0</p></div>
  <div class="rounded-xl border bg-card p-4"><p class="text-xs text-muted-foreground">Customers</p><p id="admin-stat-customers" class="font-heading text-2xl">0</p></div>
</div>
<div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-3">
  <div class="rounded-xl border bg-card p-4 xl:col-span-2"><canvas id="revenueChart"></canvas></div>
  <div class="rounded-xl border bg-card p-4"><canvas id="statusChart"></canvas></div>
</div>
<div class="mt-6 rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Top sellers</h2>
  <ul id="top-products" class="mt-2 divide-y"></ul>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

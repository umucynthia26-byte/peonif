<?php
$pageTitle = 'Activity — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();
$events = $pdo->query('SELECT oe.*, o.reference, o.customer_name FROM order_events oe
                       JOIN orders o ON o.id = oe.order_id ORDER BY oe.created_at DESC LIMIT 200')->fetchAll();
[$pageItems, $pg, $pgs, ] = paginate($events, 10);
?>
<h1>Activity</h1>
<p class="muted">A clean audit trail of every order movement.</p>
<div class="table-wrap mt"><table class="data">
  <tr><th>When</th><th>Order</th><th>Customer</th><th>Status</th><th class="hide-sm">Note</th></tr>
  <?php if (!$pageItems): ?><tr><td colspan="5" class="center muted">No activity yet.</td></tr><?php endif; ?>
  <?php foreach ($pageItems as $a): ?>
  <tr>
    <td style="white-space:nowrap" class="muted"><?= date('M j, g:i A', strtotime($a['created_at'])) ?></td>
    <td><b><?= e($a['reference']) ?></b></td>
    <td><?= e($a['customer_name']) ?></td>
    <td><span class="badge <?= $a['status'] === 'delivered' ? 'badge-ok' : 'badge-soft' ?>"><?= e(status_label($a['status'])) ?></span></td>
    <td class="hide-sm muted" style="font-size:.85rem"><?= e($a['note']) ?></td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

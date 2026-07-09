<?php
$pageTitle = 'Orders — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'deliver') {
    csrf_check();
    $st = $pdo->prepare("SELECT * FROM orders WHERE reference = ? AND status = 'paid'");
    $st->execute([$_POST['reference'] ?? '']);
    if ($o = $st->fetch()) {
        $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$o['id']]);
        $pdo->prepare('INSERT INTO order_events (order_id,status,note) VALUES (?,?,?)')
            ->execute([$o['id'], 'delivered', 'Delivered. We hope it takes their breath away.']);
        notify_user($o['user_id'] ? (int)$o['user_id'] : null, 'Order ' . $o['reference'] . ' delivered',
            "Your flowers have been delivered. We'd love your feedback on the product page!");
        flash('ok', $o['reference'] . ' marked as delivered.');
    }
    header('Location: orders.php?' . http_build_query(array_diff_key($_GET, ['page' => 1]))); exit;
}

$q = trim($_GET['q'] ?? '');
$status = $_GET['status'] ?? '';
$sql = 'SELECT * FROM orders WHERE 1=1';
$args = [];
if ($q) { $sql .= ' AND (reference LIKE ? OR customer_name LIKE ? OR email LIKE ?)'; array_push($args, "%$q%", "%$q%", "%$q%"); }
if (in_array($status, ['paid', 'delivered', 'pending_payment'], true)) { $sql .= ' AND status = ?'; $args[] = $status; }
$st = $pdo->prepare($sql . ' ORDER BY created_at DESC');
$st->execute($args);
$orders = $st->fetchAll();
foreach ($orders as &$o) {
    $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $it->execute([$o['id']]);
    $o['items'] = $it->fetchAll();
}
unset($o);
[$pageOrders, $pg, $pgs, $total] = paginate($orders, 8);
?>
<h1>Orders</h1>
<p class="muted">Every order here is already paid — press Deliver when the flowers arrive.</p>

<form class="filter-bar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search reference, customer, email…">
  <select name="status">
    <option value="">All statuses</option>
    <option value="paid" <?= $status === 'paid' ? 'selected' : '' ?>>Paid — to deliver</option>
    <option value="delivered" <?= $status === 'delivered' ? 'selected' : '' ?>>Delivered</option>
    <option value="pending_payment" <?= $status === 'pending_payment' ? 'selected' : '' ?>>Awaiting payment</option>
  </select>
  <button class="btn btn-primary btn-sm">Apply</button>
</form>

<div class="table-wrap"><table class="data">
  <tr><th>Reference</th><th>Customer</th><th class="hide-sm">Items</th><th class="hide-sm">Delivery</th><th>Total</th><th>Status</th></tr>
  <?php if (!$pageOrders): ?><tr><td colspan="6" class="center muted">No matching orders.</td></tr><?php endif; ?>
  <?php foreach ($pageOrders as $o): ?>
  <tr>
    <td><b><?= e($o['reference']) ?></b></td>
    <td><?= e($o['customer_name']) ?><br><span class="muted" style="font-size:.78rem"><?= e($o['email']) ?></span></td>
    <td class="hide-sm" style="max-width:220px"><span class="muted" style="font-size:.83rem">
      <?= e(implode(', ', array_map(fn($i) => $i['quantity'] . '× ' . $i['name'], $o['items']))) ?></span></td>
    <td class="hide-sm"><?= date('M j', strtotime($o['delivery_date'])) ?><br>
      <span class="muted" style="font-size:.78rem"><?= e($o['delivery_window']) ?> · <?= e($o['address']) ?></span></td>
    <td><b><?= money((int)$o['total_cents']) ?></b></td>
    <td>
      <?php if ($o['status'] === 'paid'): ?>
        <form method="post" data-confirm="Deliver <?= e($o['reference']) ?> to <?= e($o['customer_name']) ?>? The customer will be notified.">
          <?= csrf_field() ?><input type="hidden" name="action" value="deliver">
          <input type="hidden" name="reference" value="<?= e($o['reference']) ?>">
          <button class="btn btn-primary btn-sm"><i data-lucide="truck"></i> Deliver</button>
        </form>
      <?php elseif ($o['status'] === 'delivered'): ?>
        <span class="badge badge-ok">Delivered</span>
      <?php else: ?>
        <span class="badge badge-outline">Awaiting payment</span>
      <?php endif; ?>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

<?php
$pageTitle = 'Feedback — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([(int)$_POST['id']]);
    flash('ok', 'Review removed.');
    header('Location: feedback.php'); exit;
}
$reviews = $pdo->query('SELECT r.*, u.name author, u.email, p.name product_name, p.slug
                        FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
                        ORDER BY r.created_at DESC')->fetchAll();
[$pageItems, $pg, $pgs, ] = paginate($reviews, 8);
?>
<h1>Feedback</h1>
<p class="muted">Product ratings from customers — remove anything inappropriate.</p>
<div class="card card-pad mt">
  <?php if (!$pageItems): ?><p class="muted center">No customer feedback yet.</p><?php endif; ?>
  <?php foreach ($pageItems as $r): ?>
    <div class="notif">
      <div class="section-head" style="margin:0">
        <span><b><?= e($r['author']) ?></b>
          <span class="stars"><?= str_repeat('★', (int)$r['rating']) ?></span>
          <span class="muted" style="font-size:.8rem">on <a href="../product.php?slug=<?= e($r['slug']) ?>" style="text-decoration:underline"><?= e($r['product_name']) ?></a>
            · <?= date('M j', strtotime($r['created_at'])) ?></span></span>
        <form method="post" data-confirm="Remove this review?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <button class="btn btn-outline btn-sm"><i data-lucide="trash-2"></i></button></form>
      </div>
      <?php if ($r['comment']): ?><p class="muted" style="margin:4px 0 0"><?= e($r['comment']) ?></p><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

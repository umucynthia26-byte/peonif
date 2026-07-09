<?php
$pageTitle = 'Support Inbox — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    csrf_check();
    $pdo->prepare('DELETE FROM messages WHERE id = ?')->execute([(int)$_POST['id']]);
    flash('ok', 'Message removed.');
    header('Location: inbox.php'); exit;
}
$messages = $pdo->query('SELECT * FROM messages ORDER BY created_at DESC')->fetchAll();
[$pageItems, $pg, $pgs, ] = paginate($messages, 8);
?>
<h1>Support</h1>
<p class="muted">Messages from customers — reply by clicking their email address.</p>
<div class="card card-pad mt">
  <?php if (!$pageItems): ?><p class="muted center">No messages — contact form submissions land here.</p><?php endif; ?>
  <?php foreach ($pageItems as $m): ?>
    <div class="notif">
      <div class="section-head" style="margin:0">
        <span><b><?= e($m['name']) ?></b>
          <a href="mailto:<?= e($m['email']) ?>" style="color:var(--primary);font-size:.85rem"><?= e($m['email']) ?></a>
          <span class="muted" style="font-size:.8rem"><?= date('M j, g:i A', strtotime($m['created_at'])) ?></span></span>
        <form method="post" data-confirm="Remove this message?"><?= csrf_field() ?>
          <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
          <button class="btn btn-outline btn-sm"><i data-lucide="trash-2"></i></button></form>
      </div>
      <?php if ($m['subject']): ?><b style="font-size:.9rem"><?= e($m['subject']) ?></b><?php endif; ?>
      <p class="muted" style="margin:4px 0 0"><?= e($m['body']) ?></p>
    </div>
  <?php endforeach; ?>
</div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

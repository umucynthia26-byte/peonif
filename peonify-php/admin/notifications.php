<?php
$pageTitle = 'Notifications — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();
$uid = (int)$admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'read_one') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int)$_POST['id'], $uid]);
    } else {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
    }
    header('Location: notifications.php'); exit;
}
$st = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 100');
$st->execute([$uid]);
$notifs = $st->fetchAll();
$unread = count(array_filter($notifs, fn($n) => !$n['is_read']));
[$pageItems, $pg, $pgs, ] = paginate($notifs, 10);
?>
<div class="section-head"><div><h1>Notifications</h1>
  <p class="muted"><?= $unread ? "$unread unread — new orders, support messages and product feedback." : 'All caught up.' ?></p></div>
  <?php if ($unread): ?>
    <form method="post"><?= csrf_field() ?><button class="btn btn-outline btn-sm">Mark all as read</button></form>
  <?php endif; ?>
</div>
<div class="card card-pad">
  <?php if (!$pageItems): ?><p class="muted center">Nothing yet.</p><?php endif; ?>
  <?php foreach ($pageItems as $n): ?>
    <div class="notif <?= $n['is_read'] ? '' : 'unread' ?>">
      <div class="section-head" style="margin:0">
        <b><?= $n['is_read'] ? '' : '● ' ?><?= e($n['title']) ?></b>
        <span class="muted" style="font-size:.78rem"><?= date('M j, g:i A', strtotime($n['created_at'])) ?></span>
      </div>
      <p class="muted" style="margin:4px 0"><?= e($n['body']) ?></p>
      <?php if (!$n['is_read']): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_one">
          <input type="hidden" name="id" value="<?= (int)$n['id'] ?>">
          <button class="btn btn-outline btn-sm">Mark as read</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

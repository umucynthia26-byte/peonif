<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login('account.php');
if ($me['role'] === 'admin') { header('Location: admin/index.php'); exit; }
$pdo = db();
$uid = (int)$me['id'];

// ---------- actions ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'received') { // customer confirms delivery
        $st = $pdo->prepare("SELECT * FROM orders WHERE reference = ? AND user_id = ? AND status = 'paid'");
        $st->execute([$_POST['reference'] ?? '', $uid]);
        if ($o = $st->fetch()) {
            $pdo->prepare("UPDATE orders SET status = 'delivered' WHERE id = ?")->execute([$o['id']]);
            $pdo->prepare("INSERT INTO order_events (order_id,status,note) VALUES (?,?,?)")
                ->execute([$o['id'], 'delivered', 'Delivery confirmed by the customer.']);
            notify_admins($o['reference'] . ' confirmed received', $o['customer_name'] . ' confirmed their flowers arrived.');
            flash('ok', 'Thanks for confirming — enjoy your flowers! 🌸');
        }
        header('Location: account.php'); exit;
    }
    if ($action === 'read_one') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([(int)$_POST['id'], $uid]);
        header('Location: account.php?tab=notifications'); exit;
    }
    if ($action === 'read_all') {
        $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([$uid]);
        header('Location: account.php?tab=notifications'); exit;
    }
    if ($action === 'support') {
        $body = trim($_POST['body'] ?? '');
        if ($body) {
            $pdo->prepare('INSERT INTO messages (name,email,subject,body) VALUES (?,?,?,?)')
                ->execute([$me['name'], $me['email'], trim($_POST['subject'] ?? '') ?: 'Customer support request', $body]);
            notify_admins('New message in the inbox', $me['name'] . ' sent a support message.');
            flash('ok', 'Message sent — our team replies within one business day.');
        }
        header('Location: account.php?tab=support'); exit;
    }
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $avatar = isset($_FILES['avatar']) ? save_upload($_FILES['avatar']) : null;
            if ($avatar) {
                $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $uid]);
            }
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ?, city = ? WHERE id = ?')
                ->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['city'] ?? ''), $uid]);
            flash('ok', 'Profile saved.');
        }
        header('Location: account.php?tab=profile'); exit;
    }
    if ($action === 'password') {
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        if (!password_verify($_POST['current'] ?? '', $st->fetch()['password_hash'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($_POST['next'] ?? '') < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif (($_POST['next'] ?? '') !== ($_POST['confirm'] ?? '')) {
            flash('error', 'New passwords do not match.');
        } else {
            $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
                ->execute([password_hash($_POST['next'], PASSWORD_BCRYPT, ['cost' => 12]), $uid]);
            flash('ok', 'Password changed.');
        }
        header('Location: account.php?tab=profile'); exit;
    }
}

// ---------- data ----------
$orders = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC');
$orders->execute([$uid]);
$orders = $orders->fetchAll();
foreach ($orders as &$o) {
    $it = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
    $it->execute([$o['id']]);
    $o['items'] = $it->fetchAll();
    $ev = $pdo->prepare('SELECT * FROM order_events WHERE order_id = ? ORDER BY created_at');
    $ev->execute([$o['id']]);
    $o['events'] = $ev->fetchAll();
}
unset($o);
$notifs = $pdo->prepare('SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 60');
$notifs->execute([$uid]);
$notifs = $notifs->fetchAll();
$unreadN = count(array_filter($notifs, fn($n) => !$n['is_read']));

$stats = [
    'total' => count($orders),
    'awaiting' => count(array_filter($orders, fn($o) => $o['status'] === 'paid')),
    'delivered' => count(array_filter($orders, fn($o) => $o['status'] === 'delivered')),
    'spent' => array_sum(array_map(fn($o) => $o['status'] !== 'pending_payment' ? (int)$o['total_cents'] : 0, $orders)),
];
$tab = $_GET['tab'] ?? 'orders';
[$pageOrders, $pg, $pgs, ] = paginate($orders, 5);

$pageTitle = 'My Account — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:900px">
  <div class="section-head">
    <div style="display:flex;gap:14px;align-items:center">
      <span class="avatar" style="width:56px;height:56px;font-size:1.1rem">
        <?php if ($me['avatar_url']): ?><img src="<?= e($me['avatar_url']) ?>" alt=""><?php else: ?><?= e(initials($me['name'])) ?><?php endif; ?>
      </span>
      <div><h1 style="margin:0">Hello, <?= e(explode(' ', $me['name'])[0]) ?></h1><span class="muted"><?= e($me['email']) ?></span></div>
    </div>
    <a class="btn btn-outline btn-sm" href="logout.php">Sign out</a>
  </div>

  <div class="stats mt mb">
    <div class="stat"><span class="ico"><i data-lucide="package"></i></span><div><b><?= $stats['total'] ?></b><span>Total orders</span></div></div>
    <div class="stat"><span class="ico"><i data-lucide="truck"></i></span><div><b><?= $stats['awaiting'] ?></b><span>Awaiting delivery</span></div></div>
    <div class="stat"><span class="ico"><i data-lucide="circle-check"></i></span><div><b><?= $stats['delivered'] ?></b><span>Delivered</span></div></div>
    <div class="stat"><span class="ico"><i data-lucide="flower-2"></i></span><div><b><?= money_compact($stats['spent']) ?></b><span>Total spent</span></div></div>
  </div>

  <div class="chips mb" id="notifications">
    <a class="chip <?= $tab === 'orders' ? 'active' : '' ?>" href="?tab=orders"><i data-lucide="package"></i> Orders</a>
    <a class="chip <?= $tab === 'notifications' ? 'active' : '' ?>" href="?tab=notifications"><i data-lucide="bell"></i> Notifications
      <?php if ($unreadN): ?><span class="badge"><?= $unreadN ?></span><?php endif; ?></a>
    <a class="chip <?= $tab === 'support' ? 'active' : '' ?>" href="?tab=support"><i data-lucide="headphones"></i> Support</a>
    <a class="chip <?= $tab === 'profile' ? 'active' : '' ?>" href="?tab=profile"><i data-lucide="user-round"></i> Profile</a>
  </div>

<?php if ($tab === 'orders'): ?>
  <?php if (!$orders): ?>
    <div class="card card-pad center"><p class="muted">No orders yet.</p><a class="btn btn-primary" href="shop.php">Browse the Boutique</a></div>
  <?php endif; ?>
  <?php foreach ($pageOrders as $o): ?>
    <div class="card card-pad mb">
      <div class="section-head" style="margin-bottom:6px">
        <span><b><?= e($o['reference']) ?></b> <span class="muted"><?= date('F j, Y', strtotime($o['created_at'])) ?></span></span>
        <span class="badge <?= $o['status'] === 'delivered' ? 'badge-ok' : ($o['status'] === 'paid' ? '' : 'badge-outline') ?>">
          <?= $o['status'] === 'paid' ? 'Paid · awaiting delivery' : status_label($o['status']) ?></span>
      </div>
      <p class="muted" style="margin:4px 0"><?= e(implode(', ', array_map(fn($i) => $i['quantity'] . '× ' . $i['name'], $o['items']))) ?></p>
      <div class="card-row">
        <b style="font-family:var(--serif);font-size:1.2rem"><?= money((int)$o['total_cents']) ?></b>
        <span style="display:flex;gap:8px">
          <?php if ($o['status'] === 'paid'): ?>
            <form method="post" data-confirm="Confirm you received your flowers?"><?= csrf_field() ?>
              <input type="hidden" name="action" value="received"><input type="hidden" name="reference" value="<?= e($o['reference']) ?>">
              <button class="btn btn-primary btn-sm"><i data-lucide="circle-check"></i> I received it</button></form>
          <?php endif; ?>
          <button class="btn btn-outline btn-sm" data-modal="#d<?= (int)$o['id'] ?>">Details</button>
        </span>
      </div>
      <!-- Order details modal (matches the React dialog) -->
      <div class="modal-overlay" id="d<?= (int)$o['id'] ?>" hidden>
        <div class="modal">
          <div class="section-head" style="margin-bottom:4px">
            <h3 style="margin:0"><?= e($o['reference']) ?></h3>
            <button class="btn btn-outline btn-sm" data-close aria-label="Close">✕</button>
          </div>
          <p class="muted" style="margin-top:0">Delivery <?= date('l, F j', strtotime($o['delivery_date'])) ?> · <?= e($o['delivery_window']) ?><br><?= e($o['address']) ?></p>
          <div class="breakdown">
            <?php foreach ($o['items'] as $it): ?>
              <div><span class="muted"><?= (int)$it['quantity'] ?> × <?= e($it['name']) ?></span>
                <b><?= money((int)$it['unit_price_cents'] * (int)$it['quantity']) ?></b></div>
            <?php endforeach; ?>
          </div>
          <hr style="border:none;border-top:1px solid var(--border)">
          <div class="breakdown"><div><b>Total</b><b style="font-family:var(--serif);font-size:1.2rem"><?= money((int)$o['total_cents']) ?></b></div></div>
          <ul class="timeline" style="margin-top:18px">
            <?php $reached = array_column($o['events'], null, 'status'); ?>
            <?php foreach (['paid' => 'Paid', 'delivered' => 'Delivered'] as $sKey => $sLabel): $ev = $reached[$sKey] ?? null; ?>
            <li class="<?= $ev ? 'done' : '' ?>"><span class="dot"></span>
              <b><?= $sLabel ?></b>
              <?php if ($ev): ?><span class="muted"> — <?= date('M j, g:i A', strtotime($ev['created_at'])) ?></span>
                <p class="muted" style="margin:2px 0 0"><?= e($ev['note']) ?></p>
              <?php else: ?><span class="muted"> — Upcoming</span><?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
  <?= pager_links($pg, $pgs, ['tab' => 'orders']) ?>

<?php elseif ($tab === 'notifications'): ?>
  <div class="section-head">
    <span class="muted"><?= $unreadN ? "$unreadN unread" : 'All caught up' ?></span>
    <?php if ($unreadN): ?>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="read_all">
        <button class="btn btn-outline btn-sm">Mark all as read</button></form>
    <?php endif; ?>
  </div>
  <div class="card card-pad">
    <?php if (!$notifs): ?><p class="muted center">Nothing yet — updates about your orders will land here.</p><?php endif; ?>
    <?php foreach ($notifs as $n): ?>
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

<?php elseif ($tab === 'support'): ?>
  <div class="grid" style="grid-template-columns:260px 1fr;align-items:start">
    <div class="card card-pad">
      <h3>Customer Support</h3>
      <p class="muted"><i data-lucide="mail"></i> hello@peonify.com<br><i data-lucide="phone"></i> +1 (555) 010-2030<br>Mon–Sat, 8am – 6pm.</p>
      <p class="muted" style="font-size:.8rem">For order issues, include your order reference (e.g. PNY-A1B2C3).</p>
    </div>
    <div class="card card-pad">
      <h3>Send us a message</h3>
      <p class="muted">Sent as <?= e($me['name']) ?> (<?= e($me['email']) ?>) — straight to our team.</p>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="support">
        <div class="field"><label>Subject</label><input name="subject" placeholder="Question about order PNY-…"></div>
        <div class="field"><label>Message</label><textarea name="body" rows="5" required></textarea></div>
        <button class="btn btn-primary">Send Message</button>
      </form>
    </div>
  </div>

<?php else: /* profile */ ?>
  <div class="card card-pad mb">
    <h3>Profile</h3>
    <p class="muted">Your delivery details pre-fill checkout so orders always reach the right place.</p>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
      <div class="form-grid">
              <div class="field full" style="display:flex;gap:14px;align-items:center">
        <div class="avatar-upload" data-upload title="Click to change your photo">
          <?php if ($me['avatar_url']): ?><img class="preview" src="<?= e($me['avatar_url']) ?>" alt=""><?php else: ?><span class="ph"><?= e(initials($me['name'])) ?></span><?php endif; ?>
          <span class="cam"><i data-lucide="camera"></i></span>
          <input type="file" name="avatar" accept="image/*" hidden>
        </div>
        <div><b style="font-size:.9rem">Profile photo</b><br><span class="muted" style="font-size:.78rem">Click the photo to change it (max 5MB).</span></div>
      </div>
        <div class="field"><label>Full name</label><input name="name" required value="<?= e($me['name']) ?>"></div>
        <div class="field"><label>Phone</label><input name="phone" value="<?= e($me['phone']) ?>"></div>
        <div class="field"><label>Delivery address</label><input name="address" value="<?= e($me['address']) ?>"></div>
        <div class="field"><label>City</label><input name="city" value="<?= e($me['city']) ?>"></div>
      </div>
      <button class="btn btn-primary">Save Profile</button>
    </form>
  </div>
  <div class="card card-pad">
    <h3>Change password</h3>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
      <div class="field"><label>Current password</label>
        <div class="pw-wrap"><input type="password" name="current" required><button type="button" class="pw-eye">👁</button></div></div>
      <div class="form-grid">
        <div class="field"><label>New password</label>
          <div class="pw-wrap"><input type="password" name="next" required><button type="button" class="pw-eye">👁</button></div></div>
        <div class="field"><label>Repeat new password</label>
          <div class="pw-wrap"><input type="password" name="confirm" required><button type="button" class="pw-eye">👁</button></div></div>
      </div>
      <button class="btn btn-primary">Update Password</button>
    </form>
  </div>
<?php endif; ?>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>

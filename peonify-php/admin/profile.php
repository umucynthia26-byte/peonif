<?php
$pageTitle = 'Profile — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();
$uid = (int)$admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (($_POST['action'] ?? '') === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $avatar = isset($_FILES['avatar']) ? save_upload($_FILES['avatar']) : null;
            if ($avatar) $pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$avatar, $uid]);
            $pdo->prepare('UPDATE users SET name = ?, phone = ?, address = ?, city = ? WHERE id = ?')
                ->execute([$name, trim($_POST['phone'] ?? ''), trim($_POST['address'] ?? ''), trim($_POST['city'] ?? ''), $uid]);
            flash('ok', 'Profile saved.');
        }
    }
    if (($_POST['action'] ?? '') === 'password') {
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
    }
    header('Location: profile.php'); exit;
}
?>
<h1>Profile</h1>
<p class="muted">Your details as the shop owner.</p>
<div class="card card-pad mt mb">
  <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
    <div class="form-grid">
            <div class="field full" style="display:flex;gap:14px;align-items:center">
        <div class="avatar-upload" data-upload title="Click to change your photo">
          <?php if ($admin['avatar_url']): ?><img class="preview" src="../<?= e($admin['avatar_url']) ?>" alt=""><?php else: ?><span class="ph"><?= e(initials($admin['name'])) ?></span><?php endif; ?>
          <span class="cam"><i data-lucide="camera"></i></span>
          <input type="file" name="avatar" accept="image/*" hidden>
        </div>
        <div><b style="font-size:.9rem">Profile photo</b><br><span class="muted" style="font-size:.78rem">Click the photo to change it (max 5MB).</span></div>
      </div>
      <div class="field"><label>Full name</label><input name="name" required value="<?= e($admin['name']) ?>"></div>
      <div class="field"><label>Phone</label><input name="phone" value="<?= e($admin['phone']) ?>"></div>
      <div class="field"><label>Address</label><input name="address" value="<?= e($admin['address']) ?>"></div>
      <div class="field"><label>City</label><input name="city" value="<?= e($admin['city']) ?>"></div>
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
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

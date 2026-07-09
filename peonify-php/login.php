<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) { header('Location: ' . (is_admin() ? 'admin/index.php' : 'account.php')); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([strtolower(trim($_POST['email'] ?? ''))]);
    $user = $st->fetch();
    if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$user['id'];
        flash('ok', 'Welcome back, ' . explode(' ', $user['name'])[0] . '!');
        $to = $_SESSION['after_login'] ?? ($user['role'] === 'admin' ? 'admin/index.php' : 'account.php');
        unset($_SESSION['after_login']);
        header('Location: ' . $to);
        exit;
    }
    flash('error', 'Invalid email or password.');
}
$pageTitle = 'Sign In — Peonify';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-grid">
  <div class="auth-photo">
    <img src="assets/images/hero.jpg" alt="">
    <div class="auth-quote">
      <p>“Flowers are the music of the ground.”</p>
      <p>Your orders, your saved delivery details, your notifications — all one sign-in away.</p>
    </div>
  </div>
  <div class="auth-form"><div>
    <h1>Welcome back</h1>
    <p class="muted mb">Sign in to your Peonify account.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="email"><i data-lucide="mail"></i> Email</label>
        <input id="email" type="email" name="email" required autocomplete="username" placeholder="you@example.com"></div>
      <div class="field"><label for="password"><i data-lucide="lock"></i> Password</label>
        <div class="pw-wrap"><input id="password" type="password" name="password" required autocomplete="current-password">
        <button type="button" class="pw-eye">👁</button></div></div>
      <button class="btn btn-primary btn-lg btn-block"><i data-lucide="log-in"></i> Sign In</button>
    </form>
    <p class="center muted mt">New to Peonify? <a href="register.php" style="color:var(--primary)">Create an account</a></p>
  </div></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

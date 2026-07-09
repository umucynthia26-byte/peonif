<?php
require_once __DIR__ . '/includes/functions.php';
if (current_user()) { header('Location: account.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = strtolower(trim($_POST['email'] ?? ''));
    $pw = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';
    if (!$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('error', 'Please provide your name and a valid email.');
    } elseif (strlen($pw) < 8) {
        flash('error', 'Password must be at least 8 characters.');
    } elseif ($pw !== $confirm) {
        flash('error', 'Passwords do not match.');
    } else {
        try {
            db()->prepare('INSERT INTO users (name, email, password_hash) VALUES (?,?,?)')
                ->execute([$name, $email, password_hash($pw, PASSWORD_BCRYPT, ['cost' => 12])]);
            $_SESSION['uid'] = (int)db()->lastInsertId();
            session_regenerate_id(true);
            flash('ok', 'Welcome to Peonify, ' . explode(' ', $name)[0] . '!');
            $to = $_SESSION['after_login'] ?? 'account.php';
            unset($_SESSION['after_login']);
            header('Location: ' . $to);
            exit;
        } catch (PDOException $e) {
            flash('error', 'An account with that email already exists.');
        }
    }
}
$pageTitle = 'Create Account — Peonify';
include __DIR__ . '/includes/header.php';
?>
<div class="auth-grid">
  <div class="auth-photo">
    <img src="assets/images/garden-whisper.jpg" alt="">
    <div class="auth-quote">
      <p>“Every bloom begins with a single seed.”</p>
      <p>Join Peonify — your flowers, your delivery window, updates at every step.</p>
    </div>
  </div>
  <div class="auth-form"><div>
    <h1>Create your account</h1>
    <p class="muted mb">Get delivery updates, save your details, and check out faster.</p>
    <form method="post">
      <?= csrf_field() ?>
      <div class="field"><label for="name"><i data-lucide="user-round"></i> Full name</label>
        <input id="name" name="name" required placeholder="Amara Chen" value="<?= e($_POST['name'] ?? '') ?>"></div>
      <div class="field"><label for="email"><i data-lucide="mail"></i> Email</label>
        <input id="email" type="email" name="email" required autocomplete="username" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>"></div>
      <div class="field"><label for="password"><i data-lucide="lock"></i> Password</label>
        <div class="pw-wrap"><input id="password" type="password" name="password" required autocomplete="new-password">
        <button type="button" class="pw-eye">👁</button></div>
        <span class="muted" style="font-size:.78rem">At least 8 characters.</span></div>
      <div class="field"><label for="confirm"><i data-lucide="lock-keyhole"></i> Confirm password</label>
        <div class="pw-wrap"><input id="confirm" type="password" name="confirm" required autocomplete="new-password">
        <button type="button" class="pw-eye">👁</button></div></div>
      <button class="btn btn-primary btn-lg btn-block"><i data-lucide="user-plus"></i> Create Account</button>
    </form>
    <p class="center muted mt">Already have an account? <a href="login.php" style="color:var(--primary)">Sign in</a></p>
  </div></div>
</div>
<?php include __DIR__ . '/includes/footer.php'; ?>

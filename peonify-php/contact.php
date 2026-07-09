<?php
require_once __DIR__ . '/includes/functions.php';
$me = current_user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $body = trim($_POST['body'] ?? '');
    if ($name && filter_var($email, FILTER_VALIDATE_EMAIL) && $body) {
        db()->prepare('INSERT INTO messages (name,email,subject,body) VALUES (?,?,?,?)')
            ->execute([$name, $email, trim($_POST['subject'] ?? ''), $body]);
        notify_admins('New message in the inbox', $name . (trim($_POST['subject'] ?? '') ? ' — ' . trim($_POST['subject']) : ''));
        flash('ok', "Message sent — we'll reply within one business day.");
        header('Location: contact.php'); exit;
    }
    flash('error', 'Please fill in your name, a valid email, and a message.');
}
$pageTitle = 'Contact Us — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:900px">
  <h1>Contact Us</h1>
  <p class="muted mb">Questions about an order, a special event, or anything else — we're here to help.</p>
  <div class="grid" style="grid-template-columns:260px 1fr;align-items:start">
    <div class="card card-pad">
      <p><i data-lucide="mail"></i> hello@peonify.com</p>
      <p><i data-lucide="phone"></i> +1 (555) 010-2030</p>
      <p><i data-lucide="map-pin"></i> 12 Bloom Street, Portland, OR</p>
      <p><i data-lucide="clock"></i> Mon–Sat, 8am – 6pm</p>
    </div>
    <div class="card card-pad">
      <form method="post"><?= csrf_field() ?>
        <div class="form-grid">
          <div class="field"><label>Name</label><input name="name" required value="<?= e($me['name'] ?? '') ?>"></div>
          <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($me['email'] ?? '') ?>"></div>
          <div class="field full"><label>Subject (optional)</label><input name="subject" placeholder="Wedding flowers for October"></div>
          <div class="field full"><label>Message</label><textarea name="body" rows="5" required></textarea></div>
        </div>
        <button class="btn btn-primary btn-lg">Send Message</button>
      </form>
    </div>
  </div>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>

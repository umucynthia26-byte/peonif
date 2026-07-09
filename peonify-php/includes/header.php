<?php
require_once __DIR__ . '/functions.php';
$me = current_user();
if ($me) maybe_run_reminders();
$unread = $me ? unread_count((int)$me['id']) : 0;
$pageTitle = $pageTitle ?? 'Peonify — Luxury Floral Atelier';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="assets/favicon.svg">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script src="assets/js/main.js"></script>
</head>
<body data-role="<?= $me ? e($me['role']) : 'guest' ?>">
<div id="preloader" aria-hidden="true">
  <div class="pre-ring"><span><img src="assets/favicon.svg" alt="" style="width:26px;height:26px;display:block"></span></div>
  <p class="pre-brand">Peonify</p>
  <p class="pre-sub">Floral Atelier</p>
</div>

<header class="navbar">
  <div class="container nav-inner">
    <button class="nav-burger" id="navBurger" aria-label="Open menu"><i data-lucide="menu"></i></button>
    <a href="index.php" class="brand">
      <span class="brand-name">Peonify</span>
      <span class="brand-sub">Floral Atelier</span>
    </a>
    <nav class="nav-links" id="navLinks">
      <a href="shop.php">Shop</a>
      <a href="builder.php">Bouquet Builder</a>
      <a href="about.php">About</a>
      <a href="contact.php">Contact</a>
    </nav>
    <div class="nav-actions">
      <?php if (!$me || $me['role'] !== 'admin'): ?>
      <a href="cart.php" class="btn btn-outline btn-sm cart-btn"><i data-lucide="shopping-bag"></i> Cart <span class="badge cart-count" data-cart-count hidden>0</span></a>
      <?php endif; ?>
      <?php if ($me): ?>
        <a href="<?= $me['role'] === 'admin' ? 'admin/index.php' : 'account.php#notifications' ?>" class="icon-btn bell" title="Notifications">
          <i data-lucide="bell"></i><?php if ($unread): ?><span class="badge"><?= $unread ?></span><?php endif; ?>
        </a>
        <a href="<?= $me['role'] === 'admin' ? 'admin/index.php' : 'account.php' ?>" class="avatar" title="My Dashboard">
          <?php if ($me['avatar_url']): ?><img src="<?= e($me['avatar_url']) ?>" alt=""><?php else: ?><?= e(initials($me['name'])) ?><?php endif; ?>
        </a>
      <?php else: ?>
        <a href="login.php" class="btn btn-primary btn-sm">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</header>

<?php foreach (take_flashes() as [$type, $msg]): ?>
  <div class="flash flash-<?= e($type) ?>"><?= e($msg) ?></div>
<?php endforeach; ?>

<main>

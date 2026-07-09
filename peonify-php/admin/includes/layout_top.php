<?php
require_once dirname(__DIR__, 2) . '/includes/functions.php';
$admin = require_admin();
maybe_run_reminders();
$unreadA = unread_count((int)$admin['id']);
$active = basename($_SERVER['SCRIPT_NAME']);
$pageTitle = $pageTitle ?? 'Atelier Admin — Peonify';
$NAV = [
    'index.php' => ['layout-dashboard', 'Dashboard'],
    'orders.php' => ['shopping-bag', 'Orders'],
    'products.php' => ['flower-2', 'Products'],
    'catalog.php' => ['layers', 'Catalog'],
    'feedback.php' => ['message-square-heart', 'Feedback'],
    'inbox.php' => ['inbox', 'Support'],
    'notifications.php' => ['bell', 'Notifications'],
    'activity.php' => ['activity', 'Activity'],
    'profile.php' => ['user-round', 'Profile'],
];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle) ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Jost:wght@300;400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/style.css">
  <link rel="icon" type="image/svg+xml" href="../assets/favicon.svg">
  <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
  <script src="../assets/js/main.js"></script>
</head>
<body data-role="admin">
<div class="admin-topbar">
  <button class="nav-burger" id="adminBurger" style="display:block"><i data-lucide="menu"></i></button>
  <span class="brand-name">Peonify</span>
  <span class="badge badge-soft">Atelier Admin</span>
  <span style="margin-left:auto;display:flex;gap:12px;align-items:center">
    <a class="btn btn-outline btn-sm" href="../index.php"><i data-lucide="store"></i> Storefront</a>
    <a class="icon-btn" href="notifications.php" title="Notifications"><i data-lucide="bell"></i><?php if ($unreadA): ?><span class="badge"><?= $unreadA ?></span><?php endif; ?></a>
    <a class="avatar" href="profile.php" title="Profile">
      <?php if ($admin['avatar_url']): ?><img src="../<?= e($admin['avatar_url']) ?>" alt=""><?php else: ?><?= e(initials($admin['name'])) ?><?php endif; ?>
    </a>
  </span>
</div>
<div class="admin-wrap">
  <aside class="admin-side" id="adminSide">
    <?php foreach ($NAV as $file => [$icon, $label]): ?>
      <a href="<?= $file ?>" class="<?= $active === $file ? 'active' : '' ?>"><i data-lucide="<?= $icon ?>"></i> <?= $label ?>
        <?php if ($file === 'notifications.php' && $unreadA): ?><span class="badge" style="margin-left:auto"><?= $unreadA ?></span><?php endif; ?></a>
    <?php endforeach; ?>
    <a href="../logout.php" class="signout"><i data-lucide="log-out"></i> Sign out</a>
  </aside>
  <main class="admin-main">
    <?php foreach (take_flashes() as [$type, $msg]): ?>
      <div class="flash flash-<?= e($type) ?>" style="margin:0 0 16px"><?= e($msg) ?></div>
    <?php endforeach; ?>

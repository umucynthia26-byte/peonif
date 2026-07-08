<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Peonify | <?= htmlspecialchars(ucfirst($page)) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Jost:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="/assets/index.css" />
  <link rel="stylesheet" href="/assets/app.css" />
  <script defer src="https://unpkg.com/lucide@latest"></script>
  <script defer src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script defer src="/assets/app.js"></script>
</head>
<body class="bg-background text-foreground page-<?= htmlspecialchars($page) ?>">
  <?php if ($page !== 'admin' && !str_starts_with($page, 'admin_')): ?>
    <?php include __DIR__ . '/partials/navbar.php'; ?>
  <?php endif; ?>
  <main>
    <?php include $pageFile; ?>
  </main>
  <?php if (!in_array($page, ['login', 'signup', 'admin'], true) && !str_starts_with($page, 'admin_')): ?>
    <?php include __DIR__ . '/partials/footer.php'; ?>
  <?php endif; ?>
</body>
</html>

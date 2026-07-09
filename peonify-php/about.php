<?php
require_once __DIR__ . '/includes/functions.php';
$pageTitle = 'About Us — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="hero"><div class="container center">
  <h1>Our Story</h1>
  <p class="muted" style="font-size:1.05rem">Peonify began with a simple belief: sending flowers should feel as special as receiving them.</p>
</div></section>
<section class="section"><div class="container grid grid-2" style="align-items:center;gap:36px">
  <img src="assets/images/garden-whisper.jpg" alt="Hand-tied peony bouquet" style="border-radius:18px;box-shadow:var(--shadow);aspect-ratio:1;object-fit:cover">
  <div>
    <h2>A boutique atelier, not a warehouse</h2>
    <p class="muted">Every arrangement that leaves our studio is hand-selected and hand-tied the same morning it ships. We work directly with local growers, choose stems at peak bloom, and never hold flowers longer than a day.</p>
    <p class="muted">Our couriers deliver inside the window you choose, and our team personally follows every order from the florist's bench to your recipient's door.</p>
    <p class="muted">Whether it's a single anniversary bouquet or flowers for an entire event, we treat each order like it's the only one.</p>
    <a class="btn btn-primary" href="shop.php">Visit the Boutique</a>
  </div>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>

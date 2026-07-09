<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$featured   = $pdo->query("SELECT * FROM products WHERE collection = 'signature' AND in_stock = 1 ORDER BY id LIMIT 3")->fetchAll();
$best       = $pdo->query("SELECT p.*, COALESCE(SUM(oi.quantity),0) sold FROM products p
                           JOIN order_items oi ON oi.product_id = p.id
                           WHERE p.in_stock = 1 GROUP BY p.id ORDER BY sold DESC LIMIT 4")->fetchAll();
$fresh      = $pdo->query("SELECT * FROM products WHERE in_stock = 1 ORDER BY created_at DESC, id DESC LIMIT 4")->fetchAll();
$collections = $pdo->query("SELECT * FROM collections ORDER BY id")->fetchAll();
$covers = [];
foreach ($pdo->query("SELECT collection, MIN(id) mid FROM products WHERE image_url <> '' GROUP BY collection") as $row) {
    $img = $pdo->prepare('SELECT image_url FROM products WHERE id = ?');
    $img->execute([$row['mid']]);
    $covers[$row['collection']] = $img->fetch()['image_url'] ?? '';
}
$testimonials = $pdo->query("SELECT r.rating, r.comment, r.created_at, u.name author, u.avatar_url, p.name product_name, p.slug
                             FROM reviews r JOIN users u ON u.id = r.user_id JOIN products p ON p.id = r.product_id
                             WHERE r.rating >= 4 AND r.comment <> '' ORDER BY r.created_at DESC LIMIT 3")->fetchAll();
include __DIR__ . '/includes/header.php';
?>

<section class="hero">
  <div class="container hero-grid">
    <div>
      <span class="eyebrow">Luxury Floral Atelier</span>
      <h1>Peonies, curated.<br>Delivered like clockwork.</h1>
      <p class="muted" style="font-size:1.05rem;max-width:460px">Hand-selected stems, artisanal arrangements and precision delivery windows — from our atelier to your door.</p>
      <p style="margin-top:22px">
        <a class="btn btn-primary btn-lg" href="shop.php">Shop the Collection</a>
        <a class="btn btn-outline btn-lg" href="builder.php">Build Your Bouquet</a>
      </p>
      <div class="hero-points">
        <span><i data-lucide="check"></i> Same-day before 2pm</span><span><i data-lucide="check"></i> Order updates in your account</span><span><i data-lucide="check"></i> Freshness guaranteed</span>
      </div>
    </div>
    <div class="hero3d" id="hero3d">
      <div class="hero3d-stack" id="hero3dStack">
        <img class="hero-photo" src="assets/images/hero.jpg" alt="Signature peony arrangement">
        <img class="hero3d-thumb" src="assets/images/garden-whisper.jpg" alt="Garden Whisper bouquet">
        <div class="hero3d-review">
          <span class="stars">★★★★★</span>
          <p class="muted" style="margin:4px 0 0;font-size:.75rem">“The peonies arrived at 9:02.<br>She cried. Perfect.”</p>
        </div>
        <div class="hero3d-pill"><i data-lucide="sparkles"></i> Hand-tied this morning</div>
      </div>
    </div>
  </div>
</section>
<script>
/* 3D parallax hero — the photo stack tilts toward the pointer (same as the React version) */
document.addEventListener("DOMContentLoaded", function () {
  var frame = document.getElementById("hero3d");
  var stack = document.getElementById("hero3dStack");
  if (!frame || !stack) return;
  frame.addEventListener("mousemove", function (e) {
    var r = frame.getBoundingClientRect();
    var px = (e.clientX - r.left) / r.width - 0.5;
    var py = (e.clientY - r.top) / r.height - 0.5;
    stack.style.transform = "rotateX(" + (py * -10) + "deg) rotateY(" + (px * 12) + "deg)";
  });
  frame.addEventListener("mouseleave", function () {
    stack.style.transform = "rotateX(0deg) rotateY(0deg)";
  });
});
</script>

<section class="section"><div class="container">
  <div class="section-head"><h2>The Signature Collection</h2><a class="muted" href="shop.php">View all →</a></div>
  <div class="grid grid-3"><?php foreach ($featured as $p) ui_product_card($p); ?></div>
</div></section>

<section class="section"><div class="container">
  <a href="shop.php?sale=1" class="banner">
    <img src="assets/images/velvet-crush.jpg" alt="Flash sale">
    <span class="banner-inner">
      <span class="badge badge-sale"><i data-lucide="flame"></i> Flash Sale</span>
      <h2 style="color:#fff;margin-top:10px">Up to 20% off summer blooms</h2>
      <p>Velvet Crush, Golden Hour, Petite Poème and more — while stems last.</p>
      <span class="btn btn-outline btn-sm" style="background:#fff">Shop the sale →</span>
    </span>
  </a>
</div></section>

<?php if ($best): ?>
<section class="section"><div class="container">
  <div class="section-head"><h2><i data-lucide="trending-up"></i> Most Loved</h2><span class="muted">our customers' favourites</span></div>
  <div class="grid grid-4"><?php foreach ($best as $p) ui_product_card($p); ?></div>
</div></section>
<?php endif; ?>

<section class="section"><div class="container">
  <div class="section-head"><h2><i data-lucide="sparkles"></i> Fresh In</h2><span class="muted">the latest additions to the boutique</span></div>
  <div class="grid grid-4"><?php foreach ($fresh as $p) ui_product_card($p); ?></div>
</div></section>

<?php if ($collections): ?>
<section class="section"><div class="container">
  <h2 class="mb">Shop by Collection</h2>
  <div class="grid grid-3">
    <?php foreach ($collections as $c): ?>
    <a class="card product-card" href="shop.php?collection=<?= e($c['slug']) ?>">
      <img class="photo" style="aspect-ratio:16/9" src="<?= e($covers[$c['slug']] ?? 'assets/images/garden-whisper.jpg') ?>" alt="">
      <span class="body">
        <span class="eyebrow"><?= e($c['name']) ?> Collection</span>
        <h3><?= e($c['name']) ?></h3>
        <span class="muted"><?= e($c['description'] ?: 'Explore the ' . $c['name'] . ' collection.') ?></span>
      </span>
    </a>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="section" style="background:var(--secondary)"><div class="container">
  <h2 class="center">How Peonify Works</h2>
  <p class="center muted mb">From first click to fresh flowers at the door — in four simple steps.</p>
  <div class="grid grid-4">
    <?php $steps = [
      ['1', 'Choose', 'Pick a ready-made arrangement from the boutique, or design your own in the Bouquet Builder.'],
      ['2', 'Pay & schedule', 'Choose same-day or a future date with a delivery window that suits, and pay securely at checkout.'],
      ['3', 'We deliver', 'Our florists hand-tie your flowers the morning of delivery and bring them right to the door.'],
      ['4', 'Share the joy', "You're notified when it's delivered — then rate the arrangement and tell us how we did."],
    ]; foreach ($steps as $s): ?>
    <div class="card card-pad"><span class="step-num"><?= $s[0] ?></span><h3 style="display:inline"><?= e($s[1]) ?></h3>
      <p class="muted"><?= e($s[2]) ?></p></div>
    <?php endforeach; ?>
  </div>
  <p class="center mt"><a class="btn btn-primary btn-lg" href="shop.php">Start with Step 1 →</a></p>
</div></section>

<?php if ($testimonials): ?>
<section class="section"><div class="container">
  <h2 class="center mb">What Customers Say</h2>
  <div class="grid grid-3">
    <?php foreach ($testimonials as $t): ?>
    <div class="card card-pad">
      <span class="stars"><?= str_repeat('★', (int)$t['rating']) ?><span class="off"><?= str_repeat('★', 5 - (int)$t['rating']) ?></span></span>
      <p class="muted">“<?= e($t['comment']) ?>”</p>
      <p><b><?= e($t['author']) ?></b><br><a class="muted" href="product.php?slug=<?= e($t['slug']) ?>">on <?= e($t['product_name']) ?></a></p>
    </div>
    <?php endforeach; ?>
  </div>
</div></section>
<?php endif; ?>

<section class="section"><div class="container">
  <div class="banner right">
    <img src="assets/images/provence-morning.jpg" alt="Events">
    <span class="banner-inner">
      <span class="badge"><i data-lucide="party-popper"></i> Weddings &amp; Events</span>
      <h2 style="color:#fff;margin-top:10px">Flowers for your big day</h2>
      <p>Weddings, corporate events, celebrations — our florists design at any scale.</p>
      <a class="btn btn-outline btn-sm" style="background:#fff" href="contact.php">Talk to our florists →</a>
    </span>
  </div>
</div></section>

<?php include __DIR__ . '/includes/footer.php'; ?>

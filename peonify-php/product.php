<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();
$me = current_user();

$st = $pdo->prepare('SELECT * FROM products WHERE slug = ?');
$st->execute([$_GET['slug'] ?? '']);
$p = $st->fetch();
if (!$p) { http_response_code(404); flash('error', 'Product not found.'); header('Location: shop.php'); exit; }

// Post a review (one per customer per product; posting again updates it)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rating'])) {
    csrf_check();
    if (!$me) { flash('error', 'Sign in to leave feedback.'); header('Location: login.php'); exit; }
    $rating = max(1, min(5, (int)$_POST['rating']));
    $comment = trim($_POST['comment'] ?? '');
    $pdo->prepare('INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)
                   ON DUPLICATE KEY UPDATE rating = VALUES(rating), comment = VALUES(comment), created_at = NOW()')
        ->execute([(int)$me['id'], (int)$p['id'], $rating, $comment]);
    notify_admins('New product feedback', $me['name'] . ' rated ' . $p['name'] . ' ' . $rating . '/5.');
    flash('ok', 'Thank you for your feedback 🌸');
    header('Location: product.php?slug=' . urlencode($p['slug']));
    exit;
}

$rv = $pdo->prepare('SELECT r.*, u.name author, u.avatar_url FROM reviews r JOIN users u ON u.id = r.user_id
                     WHERE r.product_id = ? ORDER BY r.created_at DESC');
$rv->execute([(int)$p['id']]);
$reviews = $rv->fetchAll();
$avg = $reviews ? array_sum(array_column($reviews, 'rating')) / count($reviews) : 0;
$price = effective_price($p);
$pageTitle = $p['name'] . ' — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container">
  <p><a class="muted" href="shop.php">← Back to boutique</a></p>
  <div class="grid grid-2" style="gap:36px;align-items:start">
    <img src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" style="border-radius:18px;box-shadow:var(--shadow);aspect-ratio:1;object-fit:cover;width:100%">
    <div>
      <span class="badge badge-soft" style="text-transform:capitalize"><?= e($p['collection']) ?> collection</span>
      <h1 style="margin-top:10px"><?= e($p['name']) ?></h1>
      <p class="muted" style="font-size:1.05rem"><?= e($p['description']) ?></p>
      <p class="price" style="font-size:1.5rem"><?= money($price) ?>
        <?php if ($p['discount_percent'] > 0): ?>
          <s><?= money((int)$p['price_cents']) ?></s>
          <span class="badge badge-sale">-<?= (int)$p['discount_percent'] ?>%</span>
        <?php endif; ?>
      </p>
      <hr style="border:none;border-top:1px solid var(--border);margin:20px 0">
      <?php if (is_admin()): ?>
        <p class="muted">You're signed in as the admin — ordering is for customers. Edit this product from the admin dashboard.</p>
      <?php elseif (!$p['in_stock']): ?>
        <p class="muted">This arrangement is currently out of stock.</p>
      <?php else: ?>
        <button class="btn btn-primary btn-lg" data-add-cart data-id="<?= (int)$p['id'] ?>"
          data-name="<?= e($p['name']) ?>" data-price="<?= $price ?>">Add to Cart</button>
        <a class="btn btn-outline btn-lg" href="cart.php" onclick="Cart.add({product_id:<?= (int)$p['id'] ?>,name:'<?= e(addslashes($p['name'])) ?>',unit_price_cents:<?= $price ?>})">Buy Now</a>
      <?php endif; ?>
      <p class="muted mt">Same-day delivery when ordered before 2pm, or schedule a precise window at checkout.</p>
    </div>
  </div>

  <hr style="border:none;border-top:1px solid var(--border);margin:44px 0 28px">
  <div class="section-head">
    <h2>Customer Feedback</h2>
    <?php if ($reviews): ?>
      <span class="muted"><span class="stars"><?= str_repeat('★', (int)round($avg)) ?></span>
        <?= number_format($avg, 1) ?> · <?= count($reviews) ?> review<?= count($reviews) > 1 ? 's' : '' ?></span>
    <?php endif; ?>
  </div>
  <div class="grid" style="grid-template-columns:1.4fr 1fr;align-items:start">
    <div>
      <?php if (!$reviews): ?><p class="muted">No feedback yet — be the first to share your experience.</p><?php endif; ?>
      <?php foreach ($reviews as $r): ?>
        <div class="card card-pad mb">
          <b><?= e($r['author']) ?></b>
          <span class="stars" style="margin-left:8px"><?= str_repeat('★', (int)$r['rating']) ?><span class="off"><?= str_repeat('★', 5 - (int)$r['rating']) ?></span></span>
          <span class="muted" style="margin-left:8px"><?= date('M j, Y', strtotime($r['created_at'])) ?></span>
          <?php if ($r['comment']): ?><p class="muted" style="margin-bottom:0"><?= e($r['comment']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="card card-pad">
      <h3>Share your experience</h3>
      <?php if ($me && $me['role'] === 'customer'): ?>
        <form method="post">
          <?= csrf_field() ?>
          <div class="review-stars field">
            <input type="hidden" name="rating" value="5">
            <?php for ($i = 1; $i <= 5; $i++): ?><label class="<?= $i <= 5 ? 'on' : '' ?>">★</label><?php endfor; ?>
          </div>
          <div class="field"><textarea name="comment" rows="4" placeholder="How were the flowers? How was the delivery?"></textarea></div>
          <button class="btn btn-primary btn-block">Post Feedback</button>
        </form>
      <?php elseif ($me): ?>
        <p class="muted">Admins moderate feedback — customers write it.</p>
      <?php else: ?>
        <p class="muted">Sign in to leave feedback about this arrangement.</p>
        <a class="btn btn-outline btn-block" href="login.php">Sign In</a>
      <?php endif; ?>
    </div>
  </div>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/includes/functions.php';
$pdo = db();

$categories  = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$collections = $pdo->query('SELECT * FROM collections ORDER BY id')->fetchAll();

$q          = trim($_GET['q'] ?? '');
$category   = $_GET['category'] ?? '';
$collection = $_GET['collection'] ?? '';
$priceRange = $_GET['price'] ?? '';
$sort       = $_GET['sort'] ?? 'newest';
$saleOnly   = ($_GET['sale'] ?? '') === '1';

$sql = 'SELECT * FROM products WHERE 1=1';
$args = [];
if ($category)   { $sql .= ' AND category = ?';   $args[] = $category; }
if ($collection) { $sql .= ' AND collection = ?'; $args[] = $collection; }
if ($q)          { $sql .= ' AND (name LIKE ? OR description LIKE ?)'; $args[] = "%$q%"; $args[] = "%$q%"; }
if ($saleOnly)   { $sql .= ' AND discount_percent > 0'; }
$st = $pdo->prepare($sql);
$st->execute($args);
$products = $st->fetchAll();

// price filter + sorting on effective (discounted) prices
$products = array_values(array_filter($products, function ($p) use ($priceRange) {
    $price = effective_price($p);
    return match ($priceRange) {
        'under75'  => $price < 7500,
        '75to120'  => $price >= 7500 && $price < 12000,
        'over120'  => $price >= 12000,
        default    => true,
    };
}));
usort($products, fn($a, $b) => match ($sort) {
    'price_asc'  => effective_price($a) <=> effective_price($b),
    'price_desc' => effective_price($b) <=> effective_price($a),
    default      => strtotime($b['created_at']) <=> strtotime($a['created_at']),
});

[$pageItems, $page, $pages, $total] = paginate($products, 9);

$collName = '';
foreach ($collections as $c) if ($c['slug'] === $collection) $collName = $c['name'];
$pageTitle = 'Shop — Peonify';
include __DIR__ . '/includes/header.php';
$keep = fn(array $extra) => e('?' . http_build_query(array_merge($_GET, $extra, ['page' => 1])));
?>
<section class="section"><div class="container">
  <h1><?= $collName ? 'The ' . e($collName) . ' Collection' : 'The Boutique' ?></h1>

  <div class="chips mt">
    <a class="chip <?= $category === '' ? 'active' : '' ?>" href="<?= $keep(['category' => '']) ?>">All</a>
    <?php foreach ($categories as $c): ?>
      <a class="chip <?= $category === $c['slug'] ? 'active' : '' ?>" href="<?= $keep(['category' => $c['slug']]) ?>"><?= e($c['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <form class="filter-bar" method="get">
    <input type="hidden" name="category" value="<?= e($category) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search flowers…">
    <select name="collection">
      <option value="">All collections</option>
      <?php foreach ($collections as $c): ?>
        <option value="<?= e($c['slug']) ?>" <?= $collection === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?> Collection</option>
      <?php endforeach; ?>
    </select>
    <select name="price">
      <option value="">Any price</option>
      <option value="under75" <?= $priceRange === 'under75' ? 'selected' : '' ?>>Under $75</option>
      <option value="75to120" <?= $priceRange === '75to120' ? 'selected' : '' ?>>$75 – $120</option>
      <option value="over120" <?= $priceRange === 'over120' ? 'selected' : '' ?>>Over $120</option>
    </select>
    <select name="sort">
      <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest first</option>
      <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
      <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
    </select>
    <label style="display:flex;align-items:center;gap:6px;margin:0;font-weight:400">
      <input type="checkbox" name="sale" value="1" style="width:auto" <?= $saleOnly ? 'checked' : '' ?>> <i data-lucide="flame"></i> On sale
    </label>
    <button class="btn btn-primary btn-sm">Apply</button>
  </form>

  <?php if (!$pageItems): ?>
    <p class="muted">No arrangements match your search.</p>
  <?php else: ?>
    <div class="grid grid-3"><?php foreach ($pageItems as $p) ui_product_card($p); ?></div>
  <?php endif; ?>
  <?= pager_links($page, $pages) ?>
</div></section>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
$pageTitle = 'Products — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();
$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$collections = $pdo->query('SELECT * FROM collections ORDER BY id')->fetchAll();

$slugify = fn(string $s) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('ok', 'Product removed. Past orders keep their history.');
        header('Location: products.php'); exit;
    }
    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $price = (int)round(((float)($_POST['price'] ?? 0)) * 100);
        $discount = max(0, min(90, (int)($_POST['discount'] ?? 0)));
        $category = $_POST['category'] ?? '';
        $collection = $_POST['collection'] ?? '';
        $inStock = isset($_POST['in_stock']) ? 1 : 0;
        $image = isset($_FILES['image']) ? save_upload($_FILES['image']) : null;

        if (!$name || !$desc || $price <= 0) {
            flash('error', 'Name, description and a price are required.');
        } else {
            try {
                if ($id) {
                    $sql = 'UPDATE products SET name=?, description=?, price_cents=?, discount_percent=?, category=?, collection=?, in_stock=?' . ($image ? ', image_url=?' : '') . ' WHERE id=?';
                    $args = [$name, $desc, $price, $discount, $category, $collection, $inStock];
                    if ($image) $args[] = $image;
                    $args[] = $id;
                    $pdo->prepare($sql)->execute($args);
                    flash('ok', "$name updated.");
                } else {
                    $slug = $slugify($name);
                    $pdo->prepare('INSERT INTO products (slug,name,description,price_cents,discount_percent,category,collection,in_stock,image_url)
                                   VALUES (?,?,?,?,?,?,?,?,?)')
                        ->execute([$slug, $name, $desc, $price, $discount, $category, $collection, $inStock, $image ?: 'assets/images/garden-whisper.jpg']);
                    flash('ok', "$name added to the boutique.");
                }
            } catch (PDOException $e) {
                flash('error', 'A product with that name already exists.');
            }
        }
        header('Location: products.php'); exit;
    }
}

$q = trim($_GET['q'] ?? '');
$edit = null;
if (!empty($_GET['edit'])) {
    $st = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $st->execute([(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$showForm = $edit || isset($_GET['new']);

$sql = 'SELECT * FROM products';
$args = [];
if ($q) { $sql .= ' WHERE name LIKE ? OR category LIKE ? OR collection LIKE ?'; array_push($args, "%$q%", "%$q%", "%$q%"); }
$st = $pdo->prepare($sql . ' ORDER BY id DESC');
$st->execute($args);
[$pageItems, $pg, $pgs, ] = paginate($st->fetchAll(), 8);
?>
<div class="section-head"><div><h1>Products</h1></div>
  <a class="btn btn-primary" href="products.php?new=1"><i data-lucide="plus"></i> New Product</a></div>

<?php if ($showForm): ?>
<!-- Product form modal (matches the React ProductForm dialog) -->
<div class="modal-overlay" id="productModal">
  <div class="modal">
    <div class="section-head" style="margin-bottom:6px">
      <h3 style="margin:0"><?= $edit ? 'Edit ' . e($edit['name']) : 'New Product' ?></h3>
      <a class="btn btn-outline btn-sm" href="products.php" aria-label="Close">✕</a>
    </div>
    <p class="muted" style="margin-top:0"><?= $edit ? 'Changes go live in the boutique immediately.' : 'Add a new arrangement to the boutique.' ?></p>
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?>
      <input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
      <div style="display:flex;gap:16px;align-items:flex-start;margin-bottom:14px">
        <div class="upload-square" data-upload title="Click to upload a photo">
          <?php if ($edit && $edit['image_url']): ?><img class="preview" src="../<?= e($edit['image_url']) ?>" alt=""><?php endif; ?>
          <span class="ph" <?= $edit && $edit['image_url'] ? 'hidden' : '' ?>><i data-lucide="image-plus"></i></span>
          <input type="file" name="image" accept="image/*" hidden>
        </div>
        <div style="flex:1">
          <div class="field"><label>Name</label><input name="name" required value="<?= e($edit['name'] ?? '') ?>" placeholder="Blush Peony Dream"></div>
          <p class="muted" style="font-size:.78rem;margin:0">Click the square to upload a photo (max 5MB).</p>
        </div>
      </div>
      <div class="form-grid">
        <div class="field full"><label>Description</label><textarea name="description" rows="3" required><?= e($edit['description'] ?? '') ?></textarea></div>
        <div class="field"><label>Category</label>
          <select name="category"><?php foreach ($categories as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= ($edit['category'] ?? '') === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?></select></div>
        <div class="field"><label>Collection</label>
          <select name="collection"><?php foreach ($collections as $c): ?>
            <option value="<?= e($c['slug']) ?>" <?= ($edit['collection'] ?? '') === $c['slug'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?></select></div>
        <div class="field"><label>Price (USD)</label>
          <input type="number" name="price" min="0.5" step="0.01" required value="<?= $edit ? number_format($edit['price_cents'] / 100, 2, '.', '') : '' ?>" placeholder="129.00"></div>
        <div class="field"><label>Discount % (0 = none)</label>
          <input type="number" name="discount" min="0" max="90" value="<?= (int)($edit['discount_percent'] ?? 0) ?>"></div>
        <div class="field full"><label style="display:flex;gap:8px;align-items:center;font-weight:500">
          <input type="checkbox" name="in_stock" style="width:auto" <?= !$edit || $edit['in_stock'] ? 'checked' : '' ?>> In stock</label></div>
      </div>
      <button class="btn btn-primary btn-block"><?= $edit ? 'Save Changes' : 'Add Product' ?></button>
    </form>
  </div>
</div>
<?php endif; ?>

<form class="filter-bar" method="get">
  <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search products…">
  <button class="btn btn-primary btn-sm">Apply</button>
</form>

<div class="table-wrap"><table class="data">
  <tr><th>Photo</th><th>Name</th><th class="hide-sm">Category</th><th class="hide-sm">Collection</th><th>Price</th><th>Stock</th><th></th></tr>
  <?php foreach ($pageItems as $p): ?>
  <tr>
    <td><img class="thumb" src="../<?= e($p['image_url']) ?>" alt=""></td>
    <td><b><?= e($p['name']) ?></b></td>
    <td class="hide-sm" style="text-transform:capitalize"><?= e($p['category']) ?></td>
    <td class="hide-sm" style="text-transform:capitalize"><?= e($p['collection']) ?></td>
    <td><?= money((int)$p['price_cents']) ?>
      <?php if ($p['discount_percent']): ?><span class="badge badge-sale">-<?= (int)$p['discount_percent'] ?>%</span><?php endif; ?></td>
    <td><span class="badge <?= $p['in_stock'] ? 'badge-soft' : 'badge-sale' ?>"><?= $p['in_stock'] ? 'In stock' : 'Out' ?></span></td>
    <td style="white-space:nowrap">
      <a class="btn btn-outline btn-sm" href="products.php?edit=<?= (int)$p['id'] ?>"><i data-lucide="pencil"></i></a>
      <form method="post" style="display:inline" data-confirm="Remove <?= e($p['name']) ?> from the boutique?">
        <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
        <button class="btn btn-outline btn-sm"><i data-lucide="trash-2"></i></button></form>
    </td>
  </tr>
  <?php endforeach; ?>
</table></div>
<?= pager_links($pg, $pgs) ?>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

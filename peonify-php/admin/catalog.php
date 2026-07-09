<?php
$pageTitle = 'Catalog — Atelier Admin';
include __DIR__ . '/includes/layout_top.php';
$pdo = db();
$slugify = fn(string $s) => trim(preg_replace('/[^a-z0-9]+/', '-', strtolower($s)), '-');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $table = $_POST['table'] === 'collections' ? 'collections' : 'categories';
    if (($_POST['action'] ?? '') === 'add' && trim($_POST['name'] ?? '')) {
        $name = trim($_POST['name']);
        try {
            if ($table === 'collections') {
                $pdo->prepare('INSERT INTO collections (slug,name,description) VALUES (?,?,?)')
                    ->execute([$slugify($name), $name, trim($_POST['description'] ?? '')]);
            } else {
                $pdo->prepare('INSERT INTO categories (slug,name) VALUES (?,?)')->execute([$slugify($name), $name]);
            }
            flash('ok', "$name added.");
        } catch (PDOException $e) { flash('error', 'That name already exists.'); }
    }
    if (($_POST['action'] ?? '') === 'delete') {
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([(int)$_POST['id']]);
        flash('ok', 'Removed.');
    }
    header('Location: catalog.php'); exit;
}
$categories = $pdo->query('SELECT * FROM categories ORDER BY id')->fetchAll();
$collections = $pdo->query('SELECT * FROM collections ORDER BY id')->fetchAll();
?>
<h1>Catalog</h1>
<p class="muted">The categories and collections customers filter by.</p>
<div class="grid grid-2 mt">
  <div class="card card-pad">
    <h3>Categories</h3>
    <form method="post" class="filter-bar"><?= csrf_field() ?>
      <input type="hidden" name="table" value="categories"><input type="hidden" name="action" value="add">
      <input name="name" placeholder="New category name" required style="flex:1">
      <button class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Add</button></form>
    <?php foreach ($categories as $c): ?>
      <div class="section-head" style="margin:8px 0;border-bottom:1px solid var(--border);padding-bottom:8px">
        <span><?= e($c['name']) ?> <span class="muted">/<?= e($c['slug']) ?></span></span>
        <form method="post" data-confirm="Remove this category?"><?= csrf_field() ?>
          <input type="hidden" name="table" value="categories"><input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-outline btn-sm"><i data-lucide="trash-2"></i></button></form>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="card card-pad">
    <h3>Collections</h3>
    <form method="post" class="filter-bar"><?= csrf_field() ?>
      <input type="hidden" name="table" value="collections"><input type="hidden" name="action" value="add">
      <input name="name" placeholder="New collection name" required style="flex:1">
      <button class="btn btn-primary btn-sm"><i data-lucide="plus"></i> Add</button></form>
    <?php foreach ($collections as $c): ?>
      <div class="section-head" style="margin:8px 0;border-bottom:1px solid var(--border);padding-bottom:8px">
        <span><?= e($c['name']) ?> <span class="muted">/<?= e($c['slug']) ?></span></span>
        <form method="post" data-confirm="Remove this collection?"><?= csrf_field() ?>
          <input type="hidden" name="table" value="collections"><input type="hidden" name="action" value="delete">
          <input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button class="btn btn-outline btn-sm"><i data-lucide="trash-2"></i></button></form>
      </div>
    <?php endforeach; ?>
  </div>
</div>
<?php include __DIR__ . '/includes/layout_bottom.php'; ?>

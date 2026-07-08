<?php
$adminTitle = 'Catalog';
$adminSubtitle = 'Manage categories and collections shown in shop filters.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
  <div class="rounded-xl border bg-card p-4">
    <h2 class="font-heading text-xl">Categories</h2>
    <p class="mt-1 text-sm text-muted-foreground">Shop filters — bouquets, stems, arrangements...</p>
    <div class="mt-4 flex gap-2">
      <input id="admin-category-name" class="flex-1 rounded-md border px-3 py-2" placeholder="New category name" />
      <button id="admin-category-add-btn" class="rounded-md bg-primary px-3 py-2 text-primary-foreground">Add</button>
    </div>
    <p id="admin-category-result" class="mt-2 text-sm text-muted-foreground"></p>
    <ul id="admin-categories" class="mt-3 divide-y"></ul>
  </div>
  <div class="rounded-xl border bg-card p-4">
    <h2 class="font-heading text-xl">Collections</h2>
    <p class="mt-1 text-sm text-muted-foreground">Seasonal edits shown on home and shop pages.</p>
    <div class="mt-4 flex gap-2">
      <input id="admin-collection-name" class="flex-1 rounded-md border px-3 py-2" placeholder="New collection name" />
      <button id="admin-collection-add-btn" class="rounded-md bg-primary px-3 py-2 text-primary-foreground">Add</button>
    </div>
    <p id="admin-collection-result" class="mt-2 text-sm text-muted-foreground"></p>
    <ul id="admin-collections" class="mt-3 divide-y"></ul>
  </div>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

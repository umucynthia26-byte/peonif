<?php
$adminTitle = 'Products';
$adminSubtitle = 'Search, add and manage boutique products.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <div class="mb-4">
    <input id="admin-products-search" class="w-full rounded-md border px-3 py-2" placeholder="Search products..." />
  </div>
  <h2 class="font-heading text-xl">New Product</h2>
  <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="space-y-2">
      <label class="text-sm font-medium">Name</label>
      <input id="admin-product-name" class="w-full rounded-md border px-3 py-2" placeholder="Golden Hour" />
    </div>
    <div class="space-y-2">
      <label class="text-sm font-medium">Slug (optional)</label>
      <input id="admin-product-slug" class="w-full rounded-md border px-3 py-2" placeholder="golden-hour" />
    </div>
    <div class="space-y-2 md:col-span-2">
      <label class="text-sm font-medium">Description</label>
      <input id="admin-product-description" class="w-full rounded-md border px-3 py-2" placeholder="Amber roses and butterscotch ranunculus." />
    </div>
    <div class="space-y-2">
      <label class="text-sm font-medium">Category</label>
      <select id="admin-product-category" class="w-full rounded-md border px-3 py-2"></select>
    </div>
    <div class="space-y-2">
      <label class="text-sm font-medium">Collection</label>
      <select id="admin-product-collection" class="w-full rounded-md border px-3 py-2"></select>
    </div>
    <div class="space-y-2">
      <label class="text-sm font-medium">Price (cents)</label>
      <input id="admin-product-price" type="number" min="1" step="1" class="w-full rounded-md border px-3 py-2" placeholder="11200" />
    </div>
    <div class="space-y-2">
      <label class="text-sm font-medium">Discount %</label>
      <input id="admin-product-discount" type="number" min="0" max="90" step="1" class="w-full rounded-md border px-3 py-2" placeholder="15" />
    </div>
    <div class="space-y-2 md:col-span-2">
      <label class="text-sm font-medium">Image URL</label>
      <input id="admin-product-image-url" class="w-full rounded-md border px-3 py-2" placeholder="/images/golden-hour.jpg" />
    </div>
    <label class="inline-flex items-center gap-2 text-sm md:col-span-2"><input id="admin-product-in-stock" type="checkbox" checked /> In stock</label>
  </div>
  <div class="mt-3 flex items-center gap-2">
    <button id="admin-product-create-btn" class="rounded-md bg-primary px-4 py-2 text-primary-foreground">Add Product</button>
    <p id="admin-product-result" class="text-sm text-muted-foreground"></p>
  </div>
</div>
<div class="mt-6 rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Products</h2>
  <ul id="admin-products" class="mt-2 divide-y"></ul>
</div>
<div class="mt-6 rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Upload product image</h2>
  <input id="admin-upload-file" type="file" class="mt-2 w-full rounded-md border px-3 py-2" />
  <button id="admin-upload-btn" class="mt-2 rounded-md border px-3 py-2">Upload</button>
  <p id="admin-upload-result" class="mt-2 text-sm text-muted-foreground"></p>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

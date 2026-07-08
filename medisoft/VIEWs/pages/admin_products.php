<?php
$adminTitle = 'Products';
$adminSubtitle = 'Search, add and manage boutique products.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="admin-products-panel rounded-xl border bg-card p-4">
  <div class="mb-3 flex items-center justify-between gap-2">
    <div>
      <h2 class="font-heading text-2xl">Products</h2>
      <p class="text-sm text-muted-foreground">Manage inventory, pricing and stock states.</p>
    </div>
  </div>
  <div class="mb-4 flex flex-wrap items-center gap-2">
    <input id="admin-products-search" class="admin-products-search min-w-52 flex-1 rounded-md border px-3 py-2" placeholder="Search by product name or slug..." />
    <button id="admin-product-open-btn" class="admin-product-open-btn inline-flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-primary-foreground">
      <i data-lucide="plus"></i>
      New Product
    </button>
  </div>
  <div class="admin-products-table-wrap overflow-x-auto rounded-lg border">
    <table class="admin-products-table w-full min-w-[760px] text-sm">
      <thead>
        <tr class="text-left text-xs text-muted-foreground">
          <th class="py-2 pr-3">Photo</th>
          <th class="py-2 pr-3">Name</th>
          <th class="py-2 pr-3">Category</th>
          <th class="py-2 pr-3">Collection</th>
          <th class="py-2 pr-3">Price</th>
          <th class="py-2 pr-3">Stock</th>
          <th class="py-2 text-right">Actions</th>
        </tr>
      </thead>
      <tbody id="admin-products-table-body"></tbody>
    </table>
  </div>
</div>
<div id="admin-product-modal" class="admin-modal hidden">
  <div id="admin-product-modal-backdrop" class="admin-modal-backdrop"></div>
  <div class="admin-modal-card rounded-xl border bg-card p-5">
    <div class="mb-4 flex items-center justify-between">
      <h2 id="admin-product-modal-title" class="font-heading text-2xl">New Product</h2>
      <button id="admin-product-modal-close-btn" class="rounded-md border px-3 py-1.5 text-xs">Close</button>
    </div>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
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
      <div class="space-y-2 md:col-span-2">
        <label class="text-sm font-medium">Or upload image</label>
        <div class="rounded-lg border p-3">
          <input id="admin-upload-file" type="file" class="w-full rounded-md border px-3 py-2" />
          <div class="mt-2 flex items-center gap-2">
            <button id="admin-upload-btn" type="button" class="rounded-md border px-3 py-2 text-sm">Upload</button>
            <p id="admin-upload-result" class="text-sm text-muted-foreground"></p>
          </div>
        </div>
      </div>
      <label class="inline-flex items-center gap-2 text-sm md:col-span-2"><input id="admin-product-in-stock" type="checkbox" checked /> In stock</label>
    </div>
    <div class="mt-4 flex items-center gap-2">
      <button id="admin-product-save-btn" class="rounded-md bg-primary px-4 py-2 text-primary-foreground">Save Product</button>
      <p id="admin-product-result" class="text-sm text-muted-foreground"></p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

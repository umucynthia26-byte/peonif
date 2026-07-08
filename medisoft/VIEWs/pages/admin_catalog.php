<?php
$adminTitle = 'Catalog';
$adminSubtitle = 'Add products and keep the boutique updated.';
include __DIR__ . '/../partials/admin_workspace_start.php';
?>
<div class="rounded-xl border bg-card p-4">
  <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
    <input id="admin-product-name" class="rounded-md border px-3 py-2" placeholder="Name" />
    <input id="admin-product-slug" class="rounded-md border px-3 py-2" placeholder="Slug (optional)" />
    <input id="admin-product-description" class="rounded-md border px-3 py-2 md:col-span-2" placeholder="Description" />
    <select id="admin-product-category" class="rounded-md border px-3 py-2">
      <option value="bouquet">Bouquet</option>
      <option value="arrangement">Arrangement</option>
      <option value="stems">Premium Stems</option>
    </select>
    <select id="admin-product-collection" class="rounded-md border px-3 py-2">
      <option value="signature">Signature</option>
      <option value="spring">Spring</option>
      <option value="summer">Summer</option>
    </select>
    <input id="admin-product-price" type="number" min="1" step="1" class="rounded-md border px-3 py-2" placeholder="Price (cents)" />
    <input id="admin-product-discount" type="number" min="0" max="90" step="1" class="rounded-md border px-3 py-2" placeholder="Discount %" />
    <input id="admin-product-image-url" class="rounded-md border px-3 py-2 md:col-span-2" placeholder="Image URL (/images/...)" />
    <label class="inline-flex items-center gap-2 text-sm"><input id="admin-product-in-stock" type="checkbox" checked /> In stock</label>
  </div>
  <div class="mt-3 flex items-center gap-2">
    <button id="admin-product-create-btn" class="rounded-md bg-primary px-4 py-2 text-primary-foreground">Add Product</button>
    <p id="admin-product-result" class="text-sm text-muted-foreground"></p>
  </div>
  <h3 class="mt-6 font-heading text-lg">Current products</h3>
  <ul id="admin-products" class="mt-2 divide-y"></ul>
</div>
<div class="mt-6 rounded-xl border bg-card p-4">
  <h2 class="font-heading text-xl">Upload product image</h2>
  <input id="admin-upload-file" type="file" class="mt-2 w-full rounded-md border px-3 py-2" />
  <button id="admin-upload-btn" class="mt-2 rounded-md border px-3 py-2">Upload</button>
  <p id="admin-upload-result" class="mt-2 text-sm text-muted-foreground"></p>
</div>
<?php include __DIR__ . '/../partials/admin_workspace_end.php'; ?>

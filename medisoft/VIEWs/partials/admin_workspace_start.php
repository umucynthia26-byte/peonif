<?php
$adminTitle = $adminTitle ?? 'Dashboard';
$adminSubtitle = $adminSubtitle ?? 'Your boutique at a glance.';
$isActive = static fn(string $target): string => (($page ?? '') === $target) ? ' is-active' : '';
?>
<section class="admin-shell min-h-screen">
  <header class="admin-topbar sticky top-0 z-40 border-b bg-background/90 backdrop-blur-md">
    <div class="flex h-14 items-center justify-between gap-2 px-4">
      <div class="flex items-center gap-2">
        <span class="font-heading text-xl font-semibold">Peonify</span>
        <span class="hidden rounded-full bg-secondary px-2 py-0.5 text-xs text-secondary-foreground sm:inline-flex">Atelier Admin</span>
      </div>
      <div class="flex items-center gap-2">
        <button id="seed-everything-btn" class="rounded-md border px-3 py-1.5 text-sm">Seed Everything</button>
        <a href="/shop" class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm">
          <i data-lucide="store"></i>
          <span class="hidden sm:inline">Storefront</span>
        </a>
        <button id="admin-logout-btn" class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 text-sm">
          <i data-lucide="log-out"></i>
          <span class="hidden sm:inline">Sign out</span>
        </button>
      </div>
    </div>
  </header>

  <div class="mx-auto flex w-full max-w-7xl">
    <aside class="admin-sidebar sticky top-14 hidden h-[calc(100vh-3.5rem)] w-56 shrink-0 border-r lg:block">
      <div class="flex h-full flex-col gap-1 p-3">
        <a href="/admin/dashboard" class="admin-nav-link<?= $isActive('admin_dashboard') ?>">Dashboard</a>
        <a href="/admin/products" class="admin-nav-link<?= $isActive('admin_products') ?>">Products</a>
        <a href="/admin/catalog" class="admin-nav-link<?= $isActive('admin_catalog') ?>">Catalog</a>
        <a href="/admin/orders" class="admin-nav-link<?= $isActive('admin_orders') ?>">Orders</a>
        <a href="/admin/reviews" class="admin-nav-link<?= $isActive('admin_reviews') ?>">Reviews</a>
        <a href="/admin/support" class="admin-nav-link<?= $isActive('admin_support') ?>">Support</a>
        <a href="/admin/activity" class="admin-nav-link<?= $isActive('admin_activity') ?>">Activity</a>
        <button id="admin-sidebar-logout-btn" class="admin-nav-link mt-auto text-left">
          <i data-lucide="log-out"></i>
          <span class="ml-1">Sign out</span>
        </button>
      </div>
    </aside>
    <div class="min-w-0 flex-1 px-4 py-8 lg:px-8">
      <div class="mb-6">
        <h1 class="font-heading text-3xl font-medium"><?= htmlspecialchars($adminTitle) ?></h1>
        <p class="text-sm text-muted-foreground"><?= htmlspecialchars($adminSubtitle) ?></p>
      </div>

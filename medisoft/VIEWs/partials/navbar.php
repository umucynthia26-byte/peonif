<header class="sticky top-0 z-50 border-b bg-background/85 backdrop-blur-md">
  <div class="mx-auto flex h-16 max-w-6xl items-center justify-between px-4">
    <a href="/" class="flex flex-col leading-none">
      <span class="font-heading text-2xl font-semibold tracking-wide">Peonify</span>
      <span class="text-[10px] uppercase tracking-[0.35em] text-muted-foreground">Floral Atelier</span>
    </a>
    <nav class="hidden md:flex items-center gap-1">
      <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/shop"><i data-lucide="store"></i>Shop</a>
      <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/builder"><i data-lucide="sparkles"></i>Bouquet Builder</a>
      <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/about"><i data-lucide="flower-2"></i>About</a>
      <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/contact"><i data-lucide="mail"></i>Contact</a>
      <a class="nav-cart-link inline-flex items-center gap-1.5 rounded-md border px-3 py-1.5 relative <?= $page === 'cart' ? 'is-active' : '' ?>" href="/cart">
        <i data-lucide="shopping-bag"></i>
        Cart
        <span id="nav-cart-count" class="nav-cart-count hidden">0</span>
      </a>
      <?php if (($viewer['role'] ?? '') === 'admin'): ?>
        <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/admin"><i data-lucide="layout-dashboard"></i>Admin</a>
      <?php else: ?>
        <a class="px-3 py-1.5 rounded-md hover:bg-accent inline-flex items-center gap-1.5" href="/account"><i data-lucide="user-round"></i>Account</a>
      <?php endif; ?>
    </nav>
  </div>
</header>

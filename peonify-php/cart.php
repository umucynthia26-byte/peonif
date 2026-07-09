<?php
require_once __DIR__ . '/includes/functions.php';
$me = current_user();
$pageTitle = 'Your Cart — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:760px">
  <h1>Your Cart</h1>
  <div id="cartEmpty" hidden class="center" style="padding:50px 0">
    <p><i data-lucide="shopping-bag" style="width:44px;height:44px;color:var(--muted)"></i></p>
    <p class="muted">Your cart is empty — it deserves better.</p>
    <a class="btn btn-primary" href="shop.php">Browse the Boutique</a>
  </div>
  <div id="cartFull" hidden>
    <div class="card"><div id="cartRows"></div></div>
    <div class="pager" style="margin-top:22px">
      <b style="font-family:var(--serif);font-size:1.4rem">Total <span id="cartTotal"></span></b>
      <span>
        <a class="btn btn-primary btn-lg" href="checkout.php"><?= $me ? 'Proceed to Checkout' : 'Sign in to Checkout' ?></a>
        <?php if (!$me): ?><br><span class="muted" style="font-size:.78rem">An account is required to place an order.</span><?php endif; ?>
      </span>
    </div>
  </div>
</div></section>

<script>
function renderCart() {
  var items = Cart.read();
  document.getElementById("cartEmpty").hidden = items.length > 0;
  document.getElementById("cartFull").hidden = items.length === 0;
  var rows = document.getElementById("cartRows");
  rows.innerHTML = "";
  items.forEach(function (i) {
    var row = document.createElement("div");
    row.style.cssText = "display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--border)";
    row.innerHTML =
      '<div style="flex:1;min-width:0"><b>' + i.name.replace(/</g, "&lt;") + "</b>" +
      (i.custom_config ? '<br><span class="muted" style="font-size:.78rem">Custom arrangement — made to order</span>' : "") + "</div>" +
      '<span style="display:flex;gap:6px;align-items:center">' +
      '<button class="btn btn-outline btn-sm" data-dec>−</button><b>' + i.quantity + '</b>' +
      '<button class="btn btn-outline btn-sm" data-inc>+</button></span>' +
      '<b style="width:90px;text-align:right">' + money(i.unit_price_cents * i.quantity) + "</b>" +
      '<button class="btn btn-outline btn-sm" data-del>✕</button>';
    row.querySelector("[data-dec]").onclick = function () { Cart.setQty(i.line_id, i.quantity - 1); renderCart(); };
    row.querySelector("[data-inc]").onclick = function () { Cart.setQty(i.line_id, i.quantity + 1); renderCart(); };
    row.querySelector("[data-del]").onclick = function () { Cart.remove(i.line_id); renderCart(); };
    rows.appendChild(row);
  });
  document.getElementById("cartTotal").textContent = money(Cart.total());
}
renderCart();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

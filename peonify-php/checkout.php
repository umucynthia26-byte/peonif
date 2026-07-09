<?php
require_once __DIR__ . '/includes/functions.php';
$me = require_login('checkout.php');
if ($me['role'] === 'admin') { flash('error', "Admin accounts can't place orders."); header('Location: admin/index.php'); exit; }
$pdo = db();
$windows = ['09:00 - 12:00', '12:00 - 15:00', '15:00 - 18:00', '18:00 - 21:00'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $items = json_decode($_POST['cart_json'] ?? '[]', true) ?: [];
    $name = trim($_POST['customer_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $orderType = ($_POST['order_type'] ?? 'on_demand') === 'scheduled' ? 'scheduled' : 'on_demand';
    $date = $orderType === 'scheduled' ? ($_POST['delivery_date'] ?? '') : date('Y-m-d');
    $window = in_array($_POST['delivery_window'] ?? '', $windows, true) ? $_POST['delivery_window'] : '';

    if (!$items) { flash('error', 'Your cart is empty.'); header('Location: cart.php'); exit; }
    if (!$name || !$email || !$address || !$date || !$window || $date < date('Y-m-d')) {
        flash('error', 'Please complete all delivery details.');
        header('Location: checkout.php'); exit;
    }

    // Recompute every price on the server — never trust the browser.
    $total = 0;
    $lines = [];
    foreach ($items as $it) {
        $qty = max(1, min(50, (int)($it['quantity'] ?? 1)));
        if (!empty($it['product_id'])) {
            $st = $pdo->prepare('SELECT * FROM products WHERE id = ? AND in_stock = 1');
            $st->execute([(int)$it['product_id']]);
            if (!$p = $st->fetch()) continue;
            $unit = effective_price($p);
            $lines[] = ['product_id' => (int)$p['id'], 'name' => $p['name'], 'config' => null, 'qty' => $qty, 'unit' => $unit];
        } elseif (!empty($it['custom_config']) && is_array($it['custom_config'])) {
            $unit = 0; $names = [];
            foreach ($it['custom_config'] as $step => $optName) {
                $st = $pdo->prepare('SELECT price_cents, name FROM builder_options WHERE step = ? AND name = ?');
                $st->execute([$step, (string)$optName]);
                if (!$o = $st->fetch()) { $unit = -1; break; }
                $unit += (int)$o['price_cents'];
                $names[] = $o['name'];
            }
            if ($unit < 0) continue;
            $lines[] = ['product_id' => null, 'name' => 'Custom Bouquet — ' . implode(' · ', $names),
                        'config' => json_encode($it['custom_config']), 'qty' => $qty, 'unit' => $unit];
        }
    }
    foreach ($lines as $l) $total += $l['unit'] * $l['qty'];
    if (!$lines || $total <= 0) { flash('error', 'Your cart items are no longer available.'); header('Location: cart.php'); exit; }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $callback = $scheme . '://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . '/payment_callback.php';
    try {
        $payment = create_payment($total, $email, $callback);
    } catch (Throwable $ex) {
        flash('error', 'Payment could not be started: ' . $ex->getMessage());
        header('Location: checkout.php'); exit;
    }

    $pdo->beginTransaction();
    $ref = make_reference();
    $pdo->prepare("INSERT INTO orders (reference,user_id,customer_name,email,phone,address,delivery_date,delivery_window,
                   order_type,gift_note,total_cents,payment_ref,status)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$ref, (int)$me['id'], $name, $email, trim($_POST['phone'] ?? ''), $address, $date, $window,
                   $orderType, trim($_POST['gift_note'] ?? ''), $total, $payment['payment_ref'],
                   $payment['paid'] ? 'paid' : 'pending_payment']);
    $orderId = (int)$pdo->lastInsertId();
    foreach ($lines as $l) {
        $pdo->prepare('INSERT INTO order_items (order_id,product_id,name,custom_config,quantity,unit_price_cents) VALUES (?,?,?,?,?,?)')
            ->execute([$orderId, $l['product_id'], $l['name'], $l['config'], $l['qty'], $l['unit']]);
    }
    $pdo->prepare('INSERT INTO order_events (order_id,status,note) VALUES (?,?,?)')
        ->execute([$orderId, $payment['paid'] ? 'paid' : 'pending_payment',
                   $payment['paid'] ? 'Payment received — the order is with our atelier.' : 'Awaiting payment on Paystack.']);
    $pdo->commit();

    if ($payment['paid']) {
        notify_user((int)$me['id'], "Order $ref paid", "Payment received. We'll prepare your flowers and deliver them in your chosen window.");
        notify_admins("New paid order $ref", "$name — " . count($lines) . ' item(s), ' . money($total) . ", delivery $date $window.");
        flash('ok', "Order $ref placed — follow it in your account.");
        echo '<script>localStorage.removeItem("peonify_cart");location.href="account.php";</script>';
        exit;
    }
    echo '<script>localStorage.removeItem("peonify_cart");location.href=' . json_encode($payment['authorization_url']) . ';</script>';
    exit;
}

$pageTitle = 'Checkout — Peonify';
include __DIR__ . '/includes/header.php';
?>
<section class="section"><div class="container" style="max-width:960px">
  <h1>Checkout</h1>
  <form method="post" id="checkoutForm" class="grid mt" style="grid-template-columns:1.4fr 1fr;gap:26px;align-items:start">
    <?= csrf_field() ?>
    <input type="hidden" name="cart_json" id="cartJson">
    <div>
      <div class="card card-pad mb">
        <h3><span class="step-num">1</span>Recipient</h3>
        <div class="form-grid">
          <div class="field"><label>Full name</label><input name="customer_name" required value="<?= e($me['name']) ?>"></div>
          <div class="field"><label>Email</label><input type="email" name="email" required value="<?= e($me['email']) ?>"></div>
          <div class="field"><label>Phone (optional)</label><input name="phone" value="<?= e($me['phone']) ?>"></div>
          <div class="field"><label>Delivery address</label>
            <input name="address" required value="<?= e(trim($me['address'] . ($me['city'] ? ', ' . $me['city'] : ''), ', ')) ?>" placeholder="12 Bloom Street, Apt 4"></div>
          <div class="field full"><label>Gift note (optional)</label>
            <textarea name="gift_note" rows="2" placeholder="Happy anniversary — these reminded me of you."></textarea></div>
        </div>
      </div>

      <div class="card card-pad mb">
        <h3><span class="step-num">2</span>Delivery</h3>
        <p class="muted">Choose today for same-day delivery, or pick a future date and a time window that suits — we arrive inside it.</p>
        <div class="form-grid">
          <label class="card card-pad" style="cursor:pointer;margin:0">
            <input type="radio" name="order_type" value="on_demand" checked style="width:auto"> <b>On-demand</b>
            <span class="muted" style="display:block;font-size:.83rem">Delivered today</span></label>
          <label class="card card-pad" style="cursor:pointer;margin:0">
            <input type="radio" name="order_type" value="scheduled" style="width:auto"> <b>Scheduled</b>
            <span class="muted" style="display:block;font-size:.83rem">Pick a future date — events &amp; anniversaries</span></label>
          <div class="field" id="dateField" hidden><label>Delivery date</label>
            <input type="date" name="delivery_date" min="<?= date('Y-m-d') ?>">
            <span class="muted" style="font-size:.78rem">We'll remind you the day before and again 5 hours before delivery.</span></div>
          <div class="field"><label>Delivery window</label>
            <select name="delivery_window" required>
              <option value="">Select a window</option>
              <?php foreach ($windows as $w): ?><option><?= e($w) ?></option><?php endforeach; ?>
            </select></div>
        </div>
      </div>

      <div class="card card-pad">
        <h3><span class="step-num">3</span>Payment</h3>
        <?php if (paystack_enabled()): ?>
          <p class="muted">Secure payment by Paystack. After placing the order you'll be taken to Paystack's payment page.</p>
        <?php else: ?>
          <p class="muted">Payments run in demo mode — no card required.</p>
          <p class="muted" style="border:1px dashed var(--border);border-radius:10px;padding:12px"><i data-lucide="lock"></i> Demo mode active — your order is marked paid instantly. Add a Paystack test key in config.php to enable real payments.</p>
        <?php endif; ?>
      </div>
    </div>

    <div class="card card-pad sticky">
      <h3>Order Summary</h3>
      <div class="breakdown" id="summaryRows"></div>
      <hr style="border:none;border-top:1px solid var(--border)">
      <div class="breakdown"><div><b>Total</b><b style="font-family:var(--serif);font-size:1.25rem" id="summaryTotal"></b></div></div>
      <button class="btn btn-primary btn-lg btn-block mt"><?= paystack_enabled() ? 'Place Order & Pay' : 'Place Order' ?></button>
      <p class="muted center" style="font-size:.78rem">You can follow the delivery from your account.</p>
    </div>
  </form>
</div></section>

<script>
(function () {
  var items = Cart.read();
  if (!items.length) { location.href = "cart.php"; return; }
  document.getElementById("cartJson").value = JSON.stringify(items);
  var rows = document.getElementById("summaryRows");
  items.forEach(function (i) {
    var d = document.createElement("div");
    d.innerHTML = '<span class="muted">' + i.quantity + " × " + i.name.replace(/</g, "&lt;") + "</span><b>" + money(i.unit_price_cents * i.quantity) + "</b>";
    rows.appendChild(d);
  });
  document.getElementById("summaryTotal").textContent = money(Cart.total());
  document.querySelectorAll('input[name=order_type]').forEach(function (r) {
    r.addEventListener("change", function () {
      var sched = document.querySelector('input[name=order_type]:checked').value === "scheduled";
      document.getElementById("dateField").hidden = !sched;
      document.querySelector('input[name=delivery_date]').required = sched;
    });
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

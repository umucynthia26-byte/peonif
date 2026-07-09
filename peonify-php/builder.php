<?php
require_once __DIR__ . '/includes/functions.php';
$options = db()->query('SELECT * FROM builder_options ORDER BY FIELD(step,"size","focal","foliage","packaging"), id')->fetchAll();
$byStep = ['size' => [], 'focal' => [], 'foliage' => [], 'packaging' => []];
foreach ($options as $o) $byStep[$o['step']][] = $o;
$pageTitle = 'Bouquet Builder — Peonify';
include __DIR__ . '/includes/header.php';

$stepTitles = ['size' => 'Choose your size', 'focal' => 'Focal flower', 'foliage' => 'Foliage accent', 'packaging' => 'Finishing'];
$optionImages = [
    'Blush Peony' => 'assets/images/petite-poeme.jpg',
    'Coral Charm Peony' => 'assets/images/coral-charm-cascade.jpg',
    'White Peony' => 'assets/images/duchesse-blanche-stems.jpg',
    'Garden Rose' => 'assets/images/builder/garden-rose.jpg',
    'Silver Eucalyptus' => 'assets/images/builder/eucalyptus.jpg',
    'Olive Branch' => 'assets/images/builder/olive.jpg',
    'Ruscus & Fern' => 'assets/images/builder/fern.jpg',
    'Ivory Silk Wrap' => 'assets/images/garden-whisper.jpg',
    'Black Boutique Box' => 'assets/images/ivory-noir.jpg',
    'Ceramic Vessel' => 'assets/images/hero.jpg',
];
?>
<section class="section"><div class="container">
  <h1>Bouquet Builder</h1>
  <p class="muted">Four decisions. One extraordinary arrangement — watch it take shape as you go.</p>

  <div class="builder-grid mt">
    <div>
      <?php $n = 1; foreach ($byStep as $step => $opts): ?>
        <h3 style="margin-top:<?= $n > 1 ? '28px' : '0' ?>"><span class="step-num"><?= $n ?></span><?= e($stepTitles[$step]) ?></h3>
        <div class="grid grid-2" style="gap:12px">
          <?php foreach ($opts as $o): ?>
          <div class="option-card" data-step="<?= e($step) ?>" data-name="<?= e($o['name']) ?>" data-price="<?= (int)$o['price_cents'] ?>"
               data-img="<?= e($optionImages[$o['name']] ?? '') ?>">
            <?php if (!empty($optionImages[$o['name']])): ?><img src="<?= e($optionImages[$o['name']]) ?>" alt=""><?php endif; ?>
            <div>
              <b><?= e($o['name']) ?></b>
              <p class="muted" style="margin:2px 0"><?= e($o['detail']) ?></p>
              <span class="price"><?= $o['price_cents'] > 0 ? '+ ' . money((int)$o['price_cents']) : 'Included' ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php $n++; endforeach; ?>
    </div>

    <div class="sticky">
      <div class="card card-pad">
        <h3><i data-lucide="sparkles"></i> Your Bouquet</h3>
        <div class="preview-box" id="previewBox">
          <span class="hint" id="previewHint">Your bouquet takes shape here as you choose.</span>
        </div>
        <div class="breakdown mt" id="breakdown"></div>
        <hr style="border:none;border-top:1px solid var(--border)">
        <div class="breakdown"><div><b>Total</b><b style="font-family:var(--serif);font-size:1.3rem" id="builderTotal">$0.00</b></div></div>
        <?php if (is_admin()): ?>
          <p class="muted center" style="font-size:.8rem">Ordering is for customers — admins manage the boutique.</p>
        <?php else: ?>
          <button class="btn btn-primary btn-lg btn-block" id="builderAdd" disabled>4 steps to go</button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div></section>

<script>
(function () {
  var steps = ["size", "focal", "foliage", "packaging"];
  var labels = { size: "Size", focal: "Focal flower", foliage: "Foliage", packaging: "Finishing" };
  var blooms = { "Petite": 5, "Classic": 7, "Grand": 9, "Opulent": 12 };
  var chosen = {};

  document.querySelectorAll(".option-card").forEach(function (card) {
    card.addEventListener("click", function () {
      var step = card.dataset.step;
      document.querySelectorAll('.option-card[data-step="' + step + '"]').forEach(function (c) { c.classList.remove("selected"); });
      card.classList.add("selected");
      chosen[step] = { name: card.dataset.name, price: parseInt(card.dataset.price, 10), img: card.dataset.img };
      update();
    });
  });

  function update() {
    var bd = document.getElementById("breakdown");
    bd.innerHTML = "";
    var total = 0;
    steps.forEach(function (s) {
      var row = document.createElement("div");
      var c = chosen[s];
      if (c) total += c.price;
      row.innerHTML = '<span class="muted">' + labels[s] + "</span><span>" +
        (c ? c.name + (c.price ? ' · <b>' + money(c.price) + "</b>" : "") : '<i class="muted">Not chosen</i>') + "</span>";
      bd.appendChild(row);
    });
    document.getElementById("builderTotal").textContent = money(total);

    var missing = steps.filter(function (s) { return !chosen[s]; }).length;
    var btn = document.getElementById("builderAdd");
    if (btn) {
      btn.disabled = missing > 0;
      btn.textContent = missing > 0 ? missing + " step" + (missing > 1 ? "s" : "") + " to go" : "Add to Cart";
    }

    // live preview: foliage layer + circular focal blooms in a dome
    var box = document.getElementById("previewBox");
    box.querySelectorAll(".bloom,.foliage").forEach(function (el) { el.remove(); });
    document.getElementById("previewHint").hidden = !!(chosen.size || chosen.focal);
    if (chosen.foliage && chosen.foliage.img) {
      var fol = document.createElement("img");
      fol.className = "foliage"; fol.src = chosen.foliage.img;
      box.appendChild(fol);
    }
    if (chosen.size && chosen.focal && chosen.focal.img) {
      var count = blooms[chosen.size.name] || 7;
      for (var i = 0; i < count; i++) {
        var angle = (i / Math.max(count - 1, 1)) * Math.PI;
        var ring = i % 2 === 0 ? 30 : 18;
        var img = document.createElement("img");
        img.className = "bloom"; img.src = chosen.focal.img;
        var size = 20 + ((i * 7) % 10);
        img.style.width = size + "%";
        img.style.height = size + "%";
        img.style.left = (50 + Math.cos(angle) * ring - size / 2) + "%";
        img.style.top = (42 - Math.sin(angle) * ring * 0.75 + (i % 3) * 4 - size / 2) + "%";
        box.appendChild(img);
      }
    }
  }
  update();

  var addBtn = document.getElementById("builderAdd");
  if (addBtn) addBtn.addEventListener("click", function () {
    var total = steps.reduce(function (s, k) { return s + chosen[k].price; }, 0);
    var summary = steps.map(function (k) { return chosen[k].name; }).join(" · ");
    var config = {};
    steps.forEach(function (k) { config[k] = chosen[k].name; });
    Cart.add({ product_id: null, name: "Custom Bouquet — " + summary, unit_price_cents: total, custom_config: config });
    setTimeout(function () { location.href = "cart.php"; }, 600);
  });
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

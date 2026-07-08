function qs(id) { return document.getElementById(id); }
async function parseJsonSafe(response) {
  const text = await response.text();
  if (!text) return {};
  try {
    return JSON.parse(text);
  } catch {
    return { error: response.ok ? "Invalid JSON response" : `Server error (${response.status})`, raw: text };
  }
}
async function getJson(url) {
  const r = await fetch(url);
  const j = await parseJsonSafe(r);
  if (!r.ok) throw new Error(j.error || `Request failed (${r.status})`);
  return j;
}
async function sendJson(url, method, data = {}) {
  const r = await fetch(url, { method, headers: { "Content-Type": "application/json" }, body: JSON.stringify(data) });
  const j = await parseJsonSafe(r);
  if (!r.ok) throw new Error(j.error || `Request failed (${r.status})`);
  return j;
}
async function sendForm(url, method, formData) {
  const r = await fetch(url, { method, body: formData });
  const j = await parseJsonSafe(r);
  if (!r.ok) throw new Error(j.error || `Request failed (${r.status})`);
  return j;
}
const money = (c) => `$${(Number(c || 0) / 100).toFixed(2)}`;
const moneyCompact = (c) => {
  const v = Number(c || 0) / 100;
  if (v >= 1_000_000) return `$${(v / 1_000_000).toFixed(1)}M`;
  if (v >= 1_000) return `$${(v / 1_000).toFixed(1)}K`;
  return `$${v.toFixed(2)}`;
};

function ensureToastRoot() {
  let root = document.getElementById("toast-root");
  if (!root) {
    root = document.createElement("div");
    root.id = "toast-root";
    document.body.appendChild(root);
  }
  return root;
}

function showToast(message) {
  let type = "success";
  if (typeof message === "object" && message !== null) {
    type = message.type || "success";
    message = message.message || "";
  }
  const root = ensureToastRoot();
  const toast = document.createElement("div");
  toast.className = `toast toast-${type}`;
  const icon = type === "error" ? "circle-alert" : type === "warning" ? "triangle-alert" : "check-circle-2";
  toast.innerHTML = `<i data-lucide="${icon}"></i><span>${message}</span>`;
  root.innerHTML = "";
  root.appendChild(toast);
  if (window.lucide?.createIcons) window.lucide.createIcons();
  requestAnimationFrame(() => toast.classList.add("show"));
  setTimeout(() => {
    toast.classList.remove("show");
    setTimeout(() => toast.remove(), 180);
  }, 2500);
}

async function refreshCartBadge() {
  const badge = document.getElementById("nav-cart-count");
  if (!badge) return;
  try {
    const cart = await getJson("/api/cart");
    const items = cart.items || [];
    const count = items.reduce((sum, it) => sum + Number(it.quantity || 0), 0);
    if (count > 0) {
      badge.textContent = String(count);
      badge.classList.remove("hidden");
    } else {
      badge.classList.add("hidden");
    }
  } catch {
    // ignore badge errors
  }
}

function setupHero3D() {
  const frame = qs("hero-3d");
  const card = qs("hero-card");
  if (!frame || !card) return;
  frame.addEventListener("mousemove", (e) => {
    const r = card.getBoundingClientRect();
    const px = (e.clientX - r.left) / r.width - 0.5;
    const py = (e.clientY - r.top) / r.height - 0.5;
    card.style.transform = `rotateX(${py * -10}deg) rotateY(${px * 14}deg)`;
  });
  frame.addEventListener("mouseleave", () => { card.style.transform = "rotateX(0deg) rotateY(0deg)"; });
}

function productCard(p) {
  const sale = Number(p.discount_percent || 0);
  const final = sale > 0 ? Math.round(p.price_cents * (100 - sale) / 100) : p.price_cents;
  const createdAt = p.created_at ? new Date(p.created_at).getTime() : 0;
  const isNew = createdAt > 0 && Date.now() - createdAt < 14 * 24 * 3600 * 1000;
  const inStock = Boolean(Number(p.in_stock));
  const a = document.createElement("article");
  a.className = "overflow-hidden rounded-xl border bg-card transition-all duration-300 hover:-translate-y-1 hover:shadow-lg";
  a.innerHTML = `
    <a href="/shop/${encodeURIComponent(p.slug)}">
      <div class="relative overflow-hidden">
        <img src="${p.image_url || "/images/hero.jpg"}" class="aspect-[4/3] w-full object-cover transition-transform duration-500 hover:scale-105" alt="${p.name}">
        <div class="absolute top-3 left-3 flex flex-col gap-1.5">
          ${sale ? `<span class="inline-flex w-fit rounded-md bg-destructive px-2 py-1 text-[10px] font-semibold text-white">-${sale}%</span>` : ""}
          ${isNew ? `<span class="inline-flex w-fit rounded-md bg-primary px-2 py-1 text-[10px] font-semibold text-primary-foreground">New</span>` : ""}
          ${!inStock ? `<span class="inline-flex w-fit rounded-md bg-secondary px-2 py-1 text-[10px] font-semibold text-secondary-foreground">Out of stock</span>` : ""}
        </div>
      </div>
    </a>
    <div class="p-4">
      <span class="inline-flex rounded-md bg-secondary px-2 py-1 text-[10px] uppercase tracking-wide">${p.collection || ""}</span>
      <h3 class="font-heading text-xl">${p.name}</h3>
      <p class="mt-1 text-sm text-muted-foreground">${p.description}</p>
      <div class="mt-3 flex items-center justify-between">
        <p class="text-base font-semibold text-primary">${money(final)} ${sale ? `<small class="ml-2 text-xs text-muted-foreground line-through">${money(p.price_cents)}</small>` : ""}</p>
        <button class="inline-flex size-9 items-center justify-center rounded-md border border-primary/40 bg-primary text-white shadow-sm add-cart-btn ${!inStock ? "opacity-50 cursor-not-allowed" : ""}" aria-label="Add to cart" ${!inStock ? "disabled" : ""}>
          <i data-lucide="shopping-bag"></i>
        </button>
      </div>
    </div>`;
  a.querySelector(".add-cart-btn").addEventListener("click", async () => {
    if (!inStock) return;
    await sendJson("/api/cart/add", "POST", { product_id: p.id, name: p.name, quantity: 1, unit_price_cents: final });
    showToast(`${p.name} added to cart`);
    refreshCartBadge();
  });
  return a;
}

async function renderProductsInto(id) {
  const root = qs(id);
  if (!root) return;
  const search = qs("shop-search");
  const collection = qs("shop-collection");
  const sort = qs("shop-sort");
  const saleToggle = qs("shop-sale-toggle");
  const prevBtn = qs("shop-prev");
  const nextBtn = qs("shop-next");
  const pageLabel = qs("shop-page-label");
  let category = "all";
  let saleOnly = false;
  let page = 1;
  const perPage = 9;

  const allProducts = await getJson("/api/products");
  const render = () => {
    let items = [...allProducts];
    const q = (search?.value || "").trim().toLowerCase();
    const col = collection?.value || "all";
    if (category !== "all") items = items.filter((p) => p.category === category);
    if (col !== "all") items = items.filter((p) => p.collection === col);
    if (q) items = items.filter((p) => p.name.toLowerCase().includes(q) || p.description.toLowerCase().includes(q));
    if (saleOnly) items = items.filter((p) => Number(p.discount_percent || 0) > 0);
    if ((sort?.value || "newest") === "price_asc") items.sort((a, b) => Number(a.price_cents) - Number(b.price_cents));
    if ((sort?.value || "newest") === "price_desc") items.sort((a, b) => Number(b.price_cents) - Number(a.price_cents));
    if ((sort?.value || "newest") === "newest") items.sort((a, b) => new Date(b.created_at).getTime() - new Date(a.created_at).getTime());

    const pageCount = Math.max(1, Math.ceil(items.length / perPage));
    page = Math.min(page, pageCount);
    const start = (page - 1) * perPage;
    const pageItems = items.slice(start, start + perPage);
    root.innerHTML = "";
    pageItems.forEach((p) => root.appendChild(productCard(p)));
    if (window.lucide?.createIcons) window.lucide.createIcons();
    if (pageLabel) pageLabel.textContent = `Page ${page} of ${pageCount}`;
    if (prevBtn) prevBtn.disabled = page <= 1;
    if (nextBtn) nextBtn.disabled = page >= pageCount;
  };

  document.querySelectorAll(".shop-cat-btn").forEach((btn) => {
    btn.addEventListener("click", () => {
      category = btn.getAttribute("data-category") || "all";
      document.querySelectorAll(".shop-cat-btn").forEach((b) => {
        b.classList.remove("shop-cat-active");
      });
      btn.classList.add("shop-cat-active");
      page = 1;
      render();
    });
  });
  search?.addEventListener("input", () => { page = 1; render(); });
  collection?.addEventListener("change", () => { page = 1; render(); });
  sort?.addEventListener("change", () => { page = 1; render(); });
  saleToggle?.addEventListener("click", () => {
    saleOnly = !saleOnly;
    saleToggle.classList.toggle("shop-toggle-active", saleOnly);
    page = 1;
    render();
  });
  prevBtn?.addEventListener("click", () => { page = Math.max(1, page - 1); render(); });
  nextBtn?.addEventListener("click", () => { page = page + 1; render(); });
  document.querySelector('.shop-cat-btn[data-category="all"]')?.classList.add("shop-cat-active");
  render();
}

async function loadCartInto(listId, totalId) {
  const list = qs(listId), total = qs(totalId);
  if (!list || !total) return;
  const cart = await getJson("/api/cart");
  list.innerHTML = "";
  let t = 0;
  (cart.items || []).forEach((it) => {
    t += Number(it.unit_price_cents) * Number(it.quantity);
    const li = document.createElement("li");
    li.className = "py-3";
    li.innerHTML = `
      <div class="flex items-center gap-3">
        <div class="min-w-0 flex-1">
          <p class="truncate font-medium">${it.name}</p>
          ${it.custom_config ? `<p class="text-xs text-muted-foreground">Custom arrangement</p>` : ""}
        </div>
        <div class="flex items-center gap-1">
          <button class="qty-minus inline-flex size-7 items-center justify-center rounded-md border"><i data-lucide="minus"></i></button>
          <span class="w-8 text-center text-sm">${it.quantity}</span>
          <button class="qty-plus inline-flex size-7 items-center justify-center rounded-md border"><i data-lucide="plus"></i></button>
        </div>
        <span class="w-24 text-right font-medium tabular-nums">${money(Number(it.unit_price_cents) * Number(it.quantity))}</span>
        <button class="remove-line inline-flex size-7 items-center justify-center rounded-md text-muted-foreground"><i data-lucide="x"></i></button>
      </div>
    `;
    li.querySelector(".qty-minus")?.addEventListener("click", async () => {
      await sendJson("/api/cart/update", "POST", { line_id: it.line_id, quantity: Number(it.quantity) - 1 });
      await loadCartInto(listId, totalId);
      refreshCartBadge();
    });
    li.querySelector(".qty-plus")?.addEventListener("click", async () => {
      await sendJson("/api/cart/update", "POST", { line_id: it.line_id, quantity: Number(it.quantity) + 1 });
      await loadCartInto(listId, totalId);
      refreshCartBadge();
    });
    li.querySelector(".remove-line")?.addEventListener("click", async () => {
      await sendJson("/api/cart/remove", "POST", { line_id: it.line_id });
      await loadCartInto(listId, totalId);
      refreshCartBadge();
    });
    list.appendChild(li);
  });
  total.textContent = money(t);
  if (window.lucide?.createIcons) window.lucide.createIcons();
  return { items: cart.items || [], total: t };
}

async function initAuth() {
  const loginBtn = qs("login-btn"), signupBtn = qs("signup-btn");
  if (loginBtn) {
    loginBtn.addEventListener("click", async () => {
      try {
        await sendJson("/api/auth/login", "POST", { email: qs("login-email").value, password: qs("login-password").value });
        window.location.href = "/account";
      } catch (e) { showToast({ type: "error", message: e.message || "Login failed" }); }
    });
  }
  if (signupBtn) {
    signupBtn.addEventListener("click", async () => {
      try {
        await sendJson("/api/auth/signup", "POST", { name: qs("signup-name").value, email: qs("signup-email").value, password: qs("signup-password").value });
        window.location.href = "/account";
      } catch (e) { showToast({ type: "error", message: e.message || "Signup failed" }); }
    });
  }
}

async function initProduct() {
  if (!qs("product-name")) return;
  const fromQuery = new URLSearchParams(window.location.search).get("slug");
  const fromPath = window.location.pathname.startsWith("/shop/") ? decodeURIComponent(window.location.pathname.replace("/shop/", "")) : "";
  const slug = fromQuery || fromPath;
  if (!slug) return;
  const p = await getJson(`/api/products/${encodeURIComponent(slug)}`);
  const sale = Number(p.discount_percent || 0);
  const finalPrice = sale > 0 ? Math.round(Number(p.price_cents) * (100 - sale) / 100) : Number(p.price_cents);
  qs("product-name").textContent = p.name;
  qs("product-description").textContent = p.description;
  qs("product-price").textContent = money(finalPrice);
  qs("product-collection").textContent = `${p.collection} collection`;
  qs("product-image").src = p.image_url || "/images/hero.jpg";
  qs("product-stock").textContent = Number(p.in_stock) ? "In stock and ready to deliver." : "Currently out of stock.";
  const oldPrice = qs("product-old-price");
  const saleBadge = qs("product-sale-badge");
  if (sale > 0) {
    oldPrice.textContent = money(p.price_cents);
    oldPrice.classList.remove("hidden");
    saleBadge.textContent = `-${sale}%`;
    saleBadge.classList.remove("hidden");
  }
  const addBtn = qs("product-add-cart-btn");
  const buyBtn = qs("product-buy-now-btn");
  if (!Number(p.in_stock)) {
    addBtn.disabled = true;
    addBtn.classList.add("opacity-50", "cursor-not-allowed");
    buyBtn.disabled = true;
    buyBtn.classList.add("opacity-50", "cursor-not-allowed");
  }
  let me = null;
  try { me = await getJson("/api/auth/me"); } catch {}
  if (me?.user?.role === "admin") {
    qs("product-actions").innerHTML = `<p class="text-sm text-muted-foreground">You're signed in as admin. Ordering is customer-only from the storefront.</p>`;
  }
  addBtn?.addEventListener("click", async () => {
    if (!Number(p.in_stock)) return;
    await sendJson("/api/cart/add", "POST", { product_id: p.id, name: p.name, quantity: 1, unit_price_cents: finalPrice });
    showToast(`${p.name} added to cart`);
    refreshCartBadge();
  });
  buyBtn?.addEventListener("click", async () => {
    if (!Number(p.in_stock)) return;
    await sendJson("/api/cart/add", "POST", { product_id: p.id, name: p.name, quantity: 1, unit_price_cents: finalPrice });
    showToast(`${p.name} added to cart`);
    refreshCartBadge();
    window.location.href = "/checkout";
  });
  const reviewsRoot = qs("product-reviews-list");
  const avgRoot = qs("product-reviews-average");
  const starsRoot = qs("review-stars");
  const submitBtn = qs("review-submit-btn");
  if (reviewsRoot && avgRoot && starsRoot && submitBtn) {
    let selectedRating = 0;
    const renderStars = () => {
      starsRoot.innerHTML = "";
      for (let i = 1; i <= 5; i++) {
        const b = document.createElement("button");
        b.type = "button";
        b.className = `review-star-btn inline-flex size-8 items-center justify-center ${i <= selectedRating ? "is-active" : ""}`;
        b.setAttribute("aria-label", `Rate ${i} star${i > 1 ? "s" : ""}`);
        b.innerHTML = `<i data-lucide="star"></i>`;
        b.addEventListener("click", () => { selectedRating = i; renderStars(); });
        starsRoot.appendChild(b);
      }
      if (window.lucide?.createIcons) window.lucide.createIcons();
    };
    const loadReviews = async () => {
      const reviews = await getJson(`/api/products/${encodeURIComponent(slug)}/reviews`);
      reviewsRoot.innerHTML = "";
      if (!reviews.length) reviewsRoot.innerHTML = `<p class="text-sm text-muted-foreground">No feedback yet — be the first to share your experience.</p>`;
      let sum = 0;
      reviews.forEach((r) => {
        sum += Number(r.rating);
        const card = document.createElement("article");
        const stars = Math.max(1, Number(r.rating || 0));
        card.className = "rounded-xl border bg-card p-4";
        card.innerHTML = `
          <div class="flex items-center gap-3">
            <div class="inline-flex size-8 items-center justify-center rounded-full bg-secondary text-xs font-medium text-secondary-foreground">${(r.author || "?").slice(0, 1).toUpperCase()}</div>
            <div class="flex-1">
              <p class="text-sm font-medium">${r.author}</p>
              <p class="text-xs text-muted-foreground">${new Date(r.created_at).toLocaleDateString([], { month: "long", day: "numeric", year: "numeric" })}</p>
            </div>
            <p class="text-sm text-primary">${"★".repeat(stars)}</p>
          </div>
          ${r.comment ? `<p class="mt-3 text-sm text-muted-foreground">${r.comment}</p>` : ""}
        `;
        reviewsRoot.appendChild(card);
      });
      avgRoot.textContent = reviews.length ? `${(sum / reviews.length).toFixed(1)} · ${reviews.length} reviews` : "";
    };
    submitBtn.addEventListener("click", async () => {
      try {
        if (!selectedRating) throw new Error("Pick a star rating first");
        await sendJson(`/api/products/${encodeURIComponent(slug)}/reviews`, "POST", {
          rating: selectedRating,
          comment: qs("review-comment").value,
        });
        showToast("Thank you for your feedback");
        qs("review-result").textContent = "Feedback posted.";
        qs("review-comment").value = "";
        selectedRating = 0;
        renderStars();
        await loadReviews();
      } catch (e) {
        qs("review-result").textContent = e.message;
        showToast({ type: "error", message: e.message || "Could not post feedback" });
      }
    });
    renderStars();
    await loadReviews();
  }
}

document.addEventListener("DOMContentLoaded", () => {
  refreshCartBadge();
});

async function initBuilder() {
  const add = qs("builder-add-cart-btn");
  if (!add) return;
  const steps = [
    { key: "size", title: "Size", hint: "How generous should it be?" },
    { key: "focal", title: "Focal flower", hint: "The star of the arrangement." },
    { key: "foliage", title: "Foliage", hint: "Texture and movement." },
    { key: "packaging", title: "Finishing", hint: "How it arrives matters." },
  ];
  const options = {
    size: [
      { name: "Petite", detail: "Compact and elegant.", price_cents: 7900 },
      { name: "Classic", detail: "Balanced and versatile.", price_cents: 9900 },
      { name: "Grand", detail: "Lush statement bouquet.", price_cents: 12900 },
      { name: "Opulent", detail: "Ceremony-worthy abundance.", price_cents: 16900 },
    ],
    focal: [
      { name: "Blush Peony", detail: "Soft romantic blooms.", price_cents: 1200 },
      { name: "Coral Charm Peony", detail: "Warm coral tones.", price_cents: 1400 },
      { name: "White Peony", detail: "Clean and timeless.", price_cents: 1300 },
      { name: "Garden Rose", detail: "Layered fragrant petals.", price_cents: 1100 },
    ],
    foliage: [
      { name: "Silver Eucalyptus", detail: "Airy silver-green texture.", price_cents: 500 },
      { name: "Olive Branch", detail: "Mediterranean softness.", price_cents: 600 },
      { name: "Ruscus & Fern", detail: "Structured modern depth.", price_cents: 700 },
    ],
    packaging: [
      { name: "Ivory Silk Wrap", detail: "Elegant soft wrap.", price_cents: 0 },
      { name: "Black Boutique Box", detail: "Contemporary luxe presentation.", price_cents: 900 },
      { name: "Ceramic Vessel", detail: "Ready-to-display arrangement.", price_cents: 1200 },
    ],
  };
  const focalToImg = {
    "Blush Peony": "/images/petite-poeme.jpg",
    "Coral Charm Peony": "/images/coral-charm-cascade.jpg",
    "White Peony": "/images/duchesse-blanche-stems.jpg",
    "Garden Rose": "/images/builder/garden-rose.jpg",
  };
  const foliageToImg = {
    "Silver Eucalyptus": "/images/builder/eucalyptus.jpg",
    "Olive Branch": "/images/builder/olive.jpg",
    "Ruscus & Fern": "/images/builder/fern.jpg",
  };
  const packToImg = {
    "Ivory Silk Wrap": "/images/garden-whisper.jpg",
    "Black Boutique Box": "/images/ivory-noir.jpg",
    "Ceramic Vessel": "/images/hero.jpg",
  };
  const state = { stepIdx: 0, choices: {} };
  const title = qs("builder-step-title");
  const hint = qs("builder-step-hint");
  const stepRoot = qs("builder-steps");
  const optsRoot = qs("builder-options");
  const summaryRoot = qs("builder-summary");
  const totalRoot = qs("builder-total");
  const progress = qs("builder-progress-bar");
  const prev = qs("builder-back-btn");
  const next = qs("builder-next-btn");
  const preview = qs("builder-preview-image");
  const totalOf = () => steps.reduce((s, st) => s + Number(state.choices[st.key]?.price_cents || 0), 0);
  const isComplete = () => steps.every((st) => Boolean(state.choices[st.key]));
  const pick = (opt) => {
    const key = steps[state.stepIdx].key;
    state.choices[key] = opt;
    if (state.stepIdx < steps.length - 1) state.stepIdx += 1;
    render();
  };
  const previewFromChoices = () => {
    const f = state.choices.focal?.name;
    const g = state.choices.foliage?.name;
    const p = state.choices.packaging?.name;
    preview.src = focalToImg[f] || foliageToImg[g] || packToImg[p] || "/images/petite-poeme.jpg";
  };
  const render = () => {
    const step = steps[state.stepIdx];
    title.textContent = step.title;
    hint.textContent = step.hint;
    progress.style.width = `${(steps.filter((s) => state.choices[s.key]).length / steps.length) * 100}%`;
    stepRoot.innerHTML = "";
    steps.forEach((s, i) => {
      const btn = document.createElement("button");
      const done = Boolean(state.choices[s.key]);
      btn.className = `builder-step-chip inline-flex items-center gap-1 rounded-md border px-2.5 py-1.5 text-[0.8rem] ${i === state.stepIdx ? "is-current" : ""} ${done ? "is-done" : ""}`;
      btn.innerHTML = `${done ? `<i data-lucide="check"></i>` : ""}${i + 1}. ${s.title}`;
      btn.addEventListener("click", () => { state.stepIdx = i; render(); });
      stepRoot.appendChild(btn);
    });
    optsRoot.innerHTML = "";
    options[step.key].forEach((opt) => {
      const chosen = state.choices[step.key]?.name === opt.name;
      const card = document.createElement("article");
      card.className = `builder-option-card rounded-xl border bg-card p-4 ${chosen ? "is-selected" : ""}`;
      const imageMap = step.key === "focal" ? focalToImg : step.key === "foliage" ? foliageToImg : step.key === "packaging" ? packToImg : {};
      const image = imageMap[opt.name];
      card.innerHTML = `
        <div class="flex items-start gap-3">
          ${image ? `<img src="${image}" alt="${opt.name}" class="size-14 shrink-0 rounded-full border object-cover" />` : ""}
          <div class="min-w-0 flex-1">
            <div class="flex items-start justify-between gap-2">
              <p class="font-medium">${opt.name}</p>
              ${chosen ? `<i data-lucide="check" class="text-primary"></i>` : ""}
            </div>
            <p class="mt-0.5 text-sm text-muted-foreground">${opt.detail}</p>
            <p class="mt-1.5 text-sm font-medium text-primary">${opt.price_cents > 0 ? `+ ${money(opt.price_cents)}` : "Included"}</p>
          </div>
        </div>
      `;
      card.addEventListener("click", () => pick(opt));
      optsRoot.appendChild(card);
    });
    summaryRoot.innerHTML = "";
    steps.forEach((s) => {
      const opt = state.choices[s.key];
      const row = document.createElement("div");
      row.className = "flex justify-between gap-2 text-sm";
      row.innerHTML = `<span class="text-muted-foreground">${s.title}</span><span class="text-right">${opt ? `${opt.name} <span class="ml-2 text-muted-foreground">${opt.price_cents > 0 ? money(opt.price_cents) : "—"}</span>` : `<span class="italic text-muted-foreground/60">Not chosen</span>`}</span>`;
      summaryRoot.appendChild(row);
    });
    totalRoot.textContent = money(totalOf());
    prev.disabled = state.stepIdx === 0;
    next.disabled = state.stepIdx === steps.length - 1;
    add.disabled = !isComplete();
    add.textContent = isComplete() ? "Add to Cart" : `${steps.length - steps.filter((s) => state.choices[s.key]).length} step${steps.length - steps.filter((s) => state.choices[s.key]).length > 1 ? "s" : ""} to go`;
    previewFromChoices();
    if (window.lucide?.createIcons) window.lucide.createIcons();
  };
  prev.addEventListener("click", () => { state.stepIdx = Math.max(0, state.stepIdx - 1); render(); });
  next.addEventListener("click", () => { state.stepIdx = Math.min(steps.length - 1, state.stepIdx + 1); render(); });
  add.addEventListener("click", async () => {
    if (!isComplete()) return;
    const summary = steps.map((s) => state.choices[s.key].name).join(" · ");
    await sendJson("/api/cart/add", "POST", {
      product_id: null,
      name: `Custom Bouquet — ${summary}`,
      quantity: 1,
      unit_price_cents: totalOf(),
      custom_config: Object.fromEntries(steps.map((s) => [s.key, state.choices[s.key].name])),
    });
    showToast("Custom bouquet added to cart");
    refreshCartBadge();
    window.location.href = "/cart";
  });
  render();
}

async function initCart() {
  if (!qs("cart-list")) return;
  const refresh = async () => {
    const data = await loadCartInto("cart-list", "cart-total");
    const empty = (data?.items || []).length === 0;
    qs("cart-empty")?.classList.toggle("hidden", !empty);
    qs("cart-main")?.classList.toggle("hidden", empty);
  };
  await refresh();
  try {
    await getJson("/api/auth/me");
  } catch {
    qs("cart-checkout-btn").textContent = "Sign in to Checkout";
    qs("cart-checkout-btn").setAttribute("href", "/login");
    qs("cart-auth-note")?.classList.remove("hidden");
  }
  qs("clear-cart-btn")?.addEventListener("click", async () => {
    await sendJson("/api/cart/clear", "POST");
    await refresh();
  });
}

async function initCheckout() {
  if (!qs("checkout-btn")) return;
  const payments = await getJson("/api/payments/config").catch(() => ({ provider: "mock" }));
  if (payments.provider === "paystack") {
    qs("checkout-btn").textContent = "Place Order & Pay";
  }
  const me = await getJson("/api/auth/me").catch(() => null);
  if (!me?.user) {
    window.location.href = "/login";
    return;
  }
  if (me.user.role === "admin") {
    qs("checkout-main")?.classList.add("hidden");
    qs("checkout-admin")?.classList.remove("hidden");
    return;
  }
  const data = await loadCartInto("checkout-cart-list", "checkout-total");
  if (!data?.items?.length) {
    qs("checkout-main")?.classList.add("hidden");
    qs("checkout-empty")?.classList.remove("hidden");
    return;
  }
  const today = new Date().toISOString().slice(0, 10);
  if (qs("delivery-date")) qs("delivery-date").value = today;
  if (qs("checkout-name")) qs("checkout-name").value = me.user.name || "";
  if (qs("checkout-email")) qs("checkout-email").value = me.user.email || "";
  if (qs("checkout-phone")) qs("checkout-phone").value = me.user.phone || "";
  if (qs("checkout-address")) qs("checkout-address").value = [me.user.address, me.user.city].filter(Boolean).join(", ");
  const updateTypeUi = () => {
    const type = document.querySelector("input[name='order-type']:checked")?.value || "on_demand";
    qs("checkout-date-wrap")?.classList.toggle("hidden", type !== "scheduled");
    document.querySelectorAll(".checkout-radio-card").forEach((label) => {
      const input = label.querySelector("input[name='order-type']");
      label.classList.toggle("is-active", Boolean(input?.checked));
    });
  };
  document.querySelectorAll("input[name='order-type']").forEach((r) => r.addEventListener("change", updateTypeUi));
  updateTypeUi();
  qs("checkout-btn").addEventListener("click", async () => {
    try {
      const orderType = document.querySelector("input[name='order-type']:checked")?.value || "on_demand";
      const deliveryDate = orderType === "scheduled" ? (qs("delivery-date")?.value || "") : today;
      if (!qs("checkout-name").value.trim()) throw new Error("Enter full name");
      if (!qs("checkout-email").value.trim()) throw new Error("Enter email");
      if (!qs("checkout-address").value.trim()) throw new Error("Enter delivery address");
      if (!qs("delivery-window")?.value) throw new Error("Select a delivery window");
      if (orderType === "scheduled" && !deliveryDate) throw new Error("Select a delivery date");
      qs("checkout-btn").disabled = true;
      qs("checkout-btn").textContent = "Placing order...";
      const order = await sendJson("/api/orders/checkout", "POST", {
        customer_name: qs("checkout-name").value,
        email: qs("checkout-email").value,
        phone: qs("checkout-phone")?.value || "",
        address: qs("checkout-address")?.value || "",
        gift_note: qs("checkout-note")?.value || "",
        delivery_window: qs("delivery-window")?.value || "",
        order_type: orderType,
        delivery_date: deliveryDate,
        items: data.items,
      });
      showToast(`Order ${order.reference} placed`);
      qs("checkout-result").textContent = `Order created: ${order.reference}`;
      await sendJson("/api/cart/clear", "POST");
      refreshCartBadge();
      if (order.authorization_url) {
        showToast("Redirecting to Paystack...");
        window.location.href = order.authorization_url;
        return;
      }
      window.location.href = "/account";
    } catch (e) {
      qs("checkout-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not place order" });
      qs("checkout-btn").disabled = false;
      qs("checkout-btn").textContent = "Place Order";
    }
  });
}

async function initAccount() {
  if (!qs("my-orders")) return;
  try {
    const me = await getJson("/api/auth/me");
    const firstName = (me.user.name || "Customer").split(" ")[0];
    qs("me-title").textContent = `Hello, ${firstName}`;
    qs("me-badge").textContent = me.user.email || "";
    const initials = (me.user.name || "U").split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase();
    if (qs("me-avatar")) {
      qs("me-avatar").textContent = initials || "U";
      if (me.user.avatar_url) {
        qs("me-avatar").innerHTML = `<img src="${me.user.avatar_url}" alt="${me.user.name}" class="size-full rounded-full object-cover" />`;
      }
    }
    qs("support-sent-as").textContent = `Sent as ${me.user.name} (${me.user.email})`;
    qs("account-logout-btn")?.addEventListener("click", async () => {
      await sendJson("/api/auth/logout", "POST");
      window.location.href = "/";
    });
    if (qs("profile-name")) {
      qs("profile-name").value = me.user.name || "";
      qs("profile-phone").value = me.user.phone || "";
      qs("profile-address").value = me.user.address || "";
      qs("profile-city").value = me.user.city || "";
    }
    const avatarPreview = qs("profile-avatar-preview");
    if (avatarPreview) {
      const initials = (me.user.name || "U").split(" ").map((p) => p[0]).slice(0, 2).join("").toUpperCase() || "U";
      avatarPreview.textContent = initials;
      if (me.user.avatar_url) {
        avatarPreview.innerHTML = `<img src="${me.user.avatar_url}" alt="${me.user.name}" class="size-full rounded-full object-cover" />`;
      }
    }
    const [orders, notes] = await Promise.all([
      getJson("/api/me/orders"),
      getJson("/api/me/notifications"),
    ]);
    const oRoot = qs("my-orders"), nRoot = qs("my-notifications");
    oRoot.innerHTML = ""; nRoot.innerHTML = "";
    const stats = {
      total: orders.length,
      awaiting: orders.filter((o) => o.status === "paid" || o.status === "pending_payment").length,
      delivered: orders.filter((o) => o.status === "delivered").length,
      spent: orders.reduce((sum, o) => sum + Number(o.total_cents || 0), 0),
    };
    qs("account-stat-total").textContent = String(stats.total);
    qs("account-stat-awaiting").textContent = String(stats.awaiting);
    qs("account-stat-delivered").textContent = String(stats.delivered);
    qs("account-stat-spent").textContent = moneyCompact(stats.spent);
    qs("my-orders-empty")?.classList.toggle("hidden", orders.length > 0);
    orders.forEach((o) => {
      const li = document.createElement("li");
      li.className = "rounded-xl border bg-card p-4 text-sm";
      const statusLabel = o.status === "paid" ? "Paid · awaiting delivery" : (o.status || "Order");
      const created = new Date(o.created_at).toLocaleDateString([], { month: "long", day: "numeric", year: "numeric" });
      li.innerHTML = `
        <div class="flex items-center justify-between gap-2">
          <div>
            <p class="font-medium">${o.reference}</p>
            <p class="text-xs text-muted-foreground">${created}</p>
          </div>
          <span class="inline-flex rounded-md bg-secondary px-2 py-1 text-xs">${statusLabel}</span>
        </div>
        <div class="mt-3 flex items-center justify-between gap-2">
          <span class="font-heading text-xl font-semibold">${money(o.total_cents)}</span>
          ${o.status === "paid" ? `<button class="confirm-delivered inline-flex rounded-md border px-2.5 py-1.5 text-xs">I received it</button>` : ""}
        </div>
      `;
      li.querySelector(".confirm-delivered")?.addEventListener("click", async () => {
        try {
          await sendJson(`/api/orders/${encodeURIComponent(o.reference)}/deliver`, "POST");
          showToast("Order marked as delivered");
          window.location.reload();
        } catch (e) {
          showToast({ type: "error", message: e.message || "Could not update order" });
        }
      });
      oRoot.appendChild(li);
    });
    notes.forEach((n) => {
      const li = document.createElement("li");
      li.className = `py-3 text-sm ${n.read ? "" : "bg-secondary/30 -mx-4 px-4"}`;
      li.innerHTML = `
        <div class="flex items-center justify-between gap-2">
          <p class="font-medium">${n.title}</p>
          ${n.read ? "" : `<button class="mark-read inline-flex rounded-md border px-2 py-1 text-xs">Mark read</button>`}
        </div>
        <p class="mt-1 text-sm text-muted-foreground">${n.body}</p>
      `;
      li.querySelector(".mark-read")?.addEventListener("click", async () => {
        await sendJson(`/api/me/notifications/${n.id}/read`, "POST");
        window.location.reload();
      });
      nRoot.appendChild(li);
    });
    const unreadCount = notes.filter((n) => !n.read).length;
    qs("account-notif-summary").textContent = unreadCount > 0 ? `${unreadCount} unread` : "All caught up";
    const unreadBadge = qs("account-unread-badge");
    if (unreadBadge) {
      unreadBadge.textContent = String(unreadCount);
      unreadBadge.classList.toggle("hidden", unreadCount <= 0);
    }
    qs("mark-all-notifications-btn")?.addEventListener("click", async () => {
      await sendJson("/api/me/notifications/read", "POST");
      window.location.reload();
    });
    qs("support-send-btn")?.addEventListener("click", async () => {
      try {
        await sendJson("/api/contact", "POST", {
          name: me.user.name,
          email: me.user.email,
          subject: qs("support-subject").value,
          body: qs("support-body").value,
        });
        qs("support-result").textContent = "Support message sent.";
      } catch (e) {
        qs("support-result").textContent = e.message;
        showToast({ type: "error", message: e.message || "Could not send support message" });
      }
    });

    document.querySelectorAll(".tab-btn").forEach((btn) => {
      btn.addEventListener("click", () => {
        const key = btn.getAttribute("data-tab");
        document.querySelectorAll(".tab-panel").forEach((p) => p.classList.add("hidden"));
        qs(`tab-${key}`)?.classList.remove("hidden");
        document.querySelectorAll(".tab-btn").forEach((b) => b.classList.remove("is-active"));
        btn.classList.add("is-active");
      });
    });
    document.querySelector('.tab-btn[data-tab="orders"]')?.classList.add("is-active");
    if (window.lucide?.createIcons) window.lucide.createIcons();
    qs("profile-save-btn")?.addEventListener("click", async () => {
      try {
        const out = await sendJson("/api/me/profile", "PUT", {
          name: qs("profile-name").value,
          phone: qs("profile-phone").value,
          address: qs("profile-address").value,
          city: qs("profile-city").value,
        });
        qs("profile-result").textContent = `Saved profile for ${out.user.name}`;
        showToast("Profile saved");
      } catch (e) {
        qs("profile-result").textContent = e.message;
        showToast({ type: "error", message: e.message || "Could not save profile" });
      }
    });
    qs("avatar-picker-btn")?.addEventListener("click", () => qs("avatar-file")?.click());
    qs("password-save-btn")?.addEventListener("click", async () => {
      try {
        const next = qs("new-password").value;
        const confirm = qs("confirm-password")?.value || "";
        if (next !== confirm) throw new Error("New passwords do not match");
        await sendJson("/api/me/password", "POST", {
          current_password: qs("current-password").value,
          new_password: next,
        });
        qs("account-security-result").textContent = "Password changed.";
        showToast("Password changed");
      } catch (e) {
        qs("account-security-result").textContent = e.message;
        showToast({ type: "error", message: e.message || "Could not change password" });
      }
    });
    qs("avatar-upload-btn")?.addEventListener("click", async () => {
      try {
        const f = qs("avatar-file").files?.[0];
        if (!f) throw new Error("Select an image first");
        const fd = new FormData();
        fd.append("image", f);
        const out = await sendForm("/api/me/avatar", "POST", fd);
        qs("account-security-result").textContent = `Avatar uploaded: ${out.url}`;
        showToast("Avatar uploaded");
      } catch (e) {
        qs("account-security-result").textContent = e.message;
        showToast({ type: "error", message: e.message || "Could not upload avatar" });
      }
    });
    qs("avatar-file")?.addEventListener("change", async () => {
      try {
        const f = qs("avatar-file").files?.[0];
        if (!f) return;
        const fd = new FormData();
        fd.append("image", f);
        const out = await sendForm("/api/me/avatar", "POST", fd);
        if (avatarPreview) avatarPreview.innerHTML = `<img src="${out.url}" alt="${me.user.name}" class="size-full rounded-full object-cover" />`;
        showToast("Profile photo updated");
      } catch (e) {
        showToast({ type: "error", message: e.message || "Could not upload profile photo" });
      } finally {
        qs("avatar-file").value = "";
      }
    });
  } catch {
    qs("me-badge").textContent = "Please login first.";
  }
}

async function initAdmin() {
  const logoutAdmin = async () => {
    try {
      await sendJson("/api/auth/logout", "POST");
      window.location.href = "/";
    } catch (e) {
      showToast({ type: "error", message: e.message || "Could not logout" });
    }
  };
  qs("admin-logout-btn")?.addEventListener("click", logoutAdmin);
  qs("admin-sidebar-logout-btn")?.addEventListener("click", logoutAdmin);
  const loadAdminProducts = async () => {
    const root = qs("admin-products");
    if (!root) return;
    const products = await getJson("/api/products");
    const q = (qs("admin-products-search")?.value || "").trim().toLowerCase();
    root.innerHTML = "";
    products
      .filter((p) => !q || p.name.toLowerCase().includes(q) || p.slug.toLowerCase().includes(q))
      .forEach((p) => {
      const li = document.createElement("li");
      li.className = "py-2 text-sm";
      li.innerHTML = `<div class="flex items-center justify-between gap-2"><p>${p.name} <span class="text-xs text-muted-foreground">/${p.slug}</span></p><div class="flex items-center gap-2"><span class="text-xs text-muted-foreground">${money(p.price_cents)}${Number(p.discount_percent || 0) ? ` · -${p.discount_percent}%` : ""}</span><button class="delete-product inline-flex rounded-md border px-2 py-1 text-xs text-destructive">Delete</button></div></div>`;
      li.querySelector(".delete-product")?.addEventListener("click", async () => {
        try {
          await sendJson(`/api/admin/products/${p.id}`, "DELETE");
          showToast(`${p.name} removed`);
          await loadAdminProducts();
        } catch (e) {
          showToast({ type: "error", message: e.message || "Could not delete product" });
        }
      });
      root.appendChild(li);
    });
  };
  const loadProductMetaOptions = async () => {
    const catSelect = qs("admin-product-category");
    const colSelect = qs("admin-product-collection");
    if (!catSelect && !colSelect) return;
    const [cats, cols] = await Promise.all([
      getJson("/api/categories").catch(() => []),
      getJson("/api/collections").catch(() => []),
    ]);
    if (catSelect) {
      const current = catSelect.value;
      catSelect.innerHTML = "";
      cats.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.slug;
        opt.textContent = c.name;
        catSelect.appendChild(opt);
      });
      if (current) catSelect.value = current;
    }
    if (colSelect) {
      const current = colSelect.value;
      colSelect.innerHTML = "";
      cols.forEach((c) => {
        const opt = document.createElement("option");
        opt.value = c.slug;
        opt.textContent = c.name;
        colSelect.appendChild(opt);
      });
      if (current) colSelect.value = current;
    }
  };
  const loadAdminCatalog = async () => {
    const cRoot = qs("admin-categories");
    const colRoot = qs("admin-collections");
    if (!cRoot && !colRoot) return;
    const [cats, cols] = await Promise.all([
      getJson("/api/categories").catch(() => []),
      getJson("/api/collections").catch(() => []),
    ]);
    if (cRoot) {
      cRoot.innerHTML = "";
      cats.forEach((c) => {
        const li = document.createElement("li");
        li.className = "py-2 flex items-center justify-between gap-2 text-sm";
        li.innerHTML = `<span>${c.name} <span class="text-xs text-muted-foreground">/${c.slug}</span></span><button class="delete-category inline-flex rounded-md border px-2 py-1 text-xs text-destructive">Delete</button>`;
        li.querySelector(".delete-category")?.addEventListener("click", async () => {
          try {
            await sendJson(`/api/admin/categories/${c.id}`, "DELETE");
            await loadAdminCatalog();
          } catch (e) {
            showToast({ type: "error", message: e.message || "Could not delete category" });
          }
        });
        cRoot.appendChild(li);
      });
    }
    if (colRoot) {
      colRoot.innerHTML = "";
      cols.forEach((c) => {
        const li = document.createElement("li");
        li.className = "py-2 flex items-center justify-between gap-2 text-sm";
        li.innerHTML = `<span>${c.name} <span class="text-xs text-muted-foreground">/${c.slug}</span></span><button class="delete-collection inline-flex rounded-md border px-2 py-1 text-xs text-destructive">Delete</button>`;
        li.querySelector(".delete-collection")?.addEventListener("click", async () => {
          try {
            await sendJson(`/api/admin/collections/${c.id}`, "DELETE");
            await loadAdminCatalog();
          } catch (e) {
            showToast({ type: "error", message: e.message || "Could not delete collection" });
          }
        });
        colRoot.appendChild(li);
      });
    }
  };
  if (qs("revenueChart") || qs("admin-stat-revenue")) {
    try {
      const stats = await getJson("/api/admin/stats");
      if (qs("admin-stat-revenue")) qs("admin-stat-revenue").textContent = moneyCompact(stats.revenue_cents);
      if (qs("admin-stat-orders")) qs("admin-stat-orders").textContent = String(stats.total_orders);
      if (qs("admin-stat-active")) qs("admin-stat-active").textContent = String(stats.active_orders);
      if (qs("admin-stat-products")) qs("admin-stat-products").textContent = String(stats.products);
      if (qs("admin-stat-customers")) qs("admin-stat-customers").textContent = String(stats.customers);
    } catch {}
    try {
      const metrics = await getJson("/api/admin/metrics");
      if (qs("revenueChart")) {
        new Chart(qs("revenueChart"), { type: "line", data: { labels: metrics.revenue_by_day.map((r) => r.day), datasets: [{ label: "Revenue", data: metrics.revenue_by_day.map((r) => Number(r.revenue_cents) / 100), borderColor: "#5b2a52", backgroundColor: "rgba(91,42,82,.15)" }] } });
      }
      if (qs("statusChart")) {
        new Chart(qs("statusChart"), { type: "bar", data: { labels: metrics.orders_by_status.map((s) => s.status), datasets: [{ label: "Orders", data: metrics.orders_by_status.map((s) => s.count), backgroundColor: "#cd8d2f" }] } });
      }
      const top = qs("top-products");
      if (top) {
        top.innerHTML = "";
        metrics.top_products.forEach((p) => { const li = document.createElement("li"); li.className = "py-2 border-b text-sm"; li.textContent = `${p.name} — ${p.sold} sold`; top.appendChild(li); });
      }
    } catch {}
  }
  try {
    const orders = await getJson("/api/admin/orders");
    const root = qs("admin-orders");
    if (!root) throw new Error("skip");
    root.innerHTML = "";
    orders.forEach((o) => {
      const li = document.createElement("li");
      li.className = "py-3 text-sm";
      const deliveryDate = o.delivery_date ? new Date(o.delivery_date).toLocaleDateString([], { month: "short", day: "numeric" }) : "—";
      const statusBadge = o.status === "delivered"
        ? `<span class="inline-flex rounded-md bg-primary px-2 py-1 text-xs text-primary-foreground">Delivered</span>`
        : o.status === "pending_payment"
          ? `<span class="inline-flex rounded-md border px-2 py-1 text-xs text-muted-foreground">Awaiting payment</span>`
          : `<span class="inline-flex rounded-md bg-secondary px-2 py-1 text-xs">Paid · awaiting</span>`;
      li.innerHTML = `
        <div class="grid gap-2 md:grid-cols-[1.1fr_1fr_0.9fr_0.8fr_0.8fr] md:items-center md:gap-3">
          <div>
            <p class="font-medium">${o.reference}</p>
            <p class="text-xs text-muted-foreground">${new Date(o.created_at).toLocaleDateString([], { month: "short", day: "numeric", year: "numeric" })}</p>
          </div>
          <div>
            <p>${o.customer_name}</p>
            <p class="text-xs text-muted-foreground">${o.email}</p>
          </div>
          <div class="text-xs text-muted-foreground">${deliveryDate}${o.delivery_window ? `<br>${o.delivery_window}` : ""}</div>
          <div class="tabular-nums">${money(o.total_cents)}</div>
          <div class="flex items-center gap-2">
            ${statusBadge}
            ${o.status === "paid" ? `<button class="deliver-btn inline-flex rounded-md border px-2 py-1 text-xs">Deliver</button>` : ""}
          </div>
        </div>
      `;
      li.querySelector(".deliver-btn")?.addEventListener("click", async () => {
        try {
          await sendJson(`/api/orders/${encodeURIComponent(o.reference)}/deliver`, "POST");
          showToast("Order marked as delivered");
          window.location.reload();
        } catch (e) {
          showToast({ type: "error", message: e.message || "Could not update order" });
        }
      });
      root.appendChild(li);
    });
  } catch {}
  try {
    const reviews = await getJson("/api/admin/reviews");
    const root = qs("admin-reviews");
    if (root) {
      root.innerHTML = "";
      reviews.forEach((r) => {
        const li = document.createElement("li");
        li.className = "py-3 text-sm";
        const stars = Array.from({ length: Number(r.rating || 0) }).map(() => "★").join("");
        li.innerHTML = `
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">${r.author}</span>
                <span class="text-primary">${stars}</span>
                <span class="text-xs text-muted-foreground">on ${r.product_name} · ${new Date(r.created_at).toLocaleDateString([], { month: "short", day: "numeric" })}</span>
              </div>
              ${r.comment ? `<p class="mt-1 text-sm text-muted-foreground">${r.comment}</p>` : ""}
            </div>
            <button class="delete-review inline-flex rounded-md border px-2 py-1 text-xs text-destructive">Delete</button>
          </div>
        `;
        li.querySelector(".delete-review")?.addEventListener("click", async () => {
          await sendJson(`/api/admin/reviews/${r.id}`, "DELETE");
          window.location.reload();
        });
        root.appendChild(li);
      });
    }
  } catch {}
  try {
    const msgs = await getJson("/api/admin/messages");
    const root = qs("admin-messages");
    if (root) {
      root.innerHTML = "";
      msgs.forEach((m) => {
        const li = document.createElement("li");
        li.className = "py-3 text-sm";
        li.innerHTML = `
          <div class="flex items-start gap-3">
            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-medium">${m.name}</span>
                <a href="mailto:${m.email}" class="text-xs text-primary underline">${m.email}</a>
                <span class="text-xs text-muted-foreground">${new Date(m.created_at).toLocaleString([], { month: "short", day: "numeric", hour: "2-digit", minute: "2-digit" })}</span>
              </div>
              ${m.subject ? `<p class="mt-1 text-sm font-medium">${m.subject}</p>` : ""}
              <p class="mt-1 text-sm text-muted-foreground">${m.body}</p>
            </div>
            <button class="delete-message inline-flex rounded-md border px-2 py-1 text-xs text-destructive">Delete</button>
          </div>
        `;
        li.querySelector(".delete-message")?.addEventListener("click", async () => {
          await sendJson(`/api/admin/messages/${m.id}`, "DELETE");
          window.location.reload();
        });
        root.appendChild(li);
      });
    }
  } catch {}
  try {
    const acts = await getJson("/api/admin/activity");
    const root = qs("admin-activity");
    if (root) {
      root.innerHTML = "";
      acts.forEach((a) => {
        const li = document.createElement("li");
        li.className = "py-3 text-sm";
        li.innerHTML = `
          <div class="grid gap-2 md:grid-cols-[1fr_0.9fr_1.4fr] md:gap-3">
            <span class="font-medium">${a.reference}</span>
            <span class="text-xs text-muted-foreground">${a.status}</span>
            <span class="text-xs text-muted-foreground">${a.note}</span>
          </div>
        `;
        root.appendChild(li);
      });
    }
  } catch {}
  qs("admin-upload-btn")?.addEventListener("click", async () => {
    try {
      const f = qs("admin-upload-file").files?.[0];
      if (!f) throw new Error("Select an image first");
      const fd = new FormData();
      fd.append("image", f);
      const out = await sendForm("/api/admin/upload", "POST", fd);
      qs("admin-upload-result").textContent = `Uploaded: ${out.url}`;
    } catch (e) {
      qs("admin-upload-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not upload image" });
    }
  });
  if (qs("admin-product-create-btn") || qs("admin-products")) {
    await loadProductMetaOptions().catch(() => {});
    await loadAdminProducts().catch(() => {});
  }
  qs("admin-products-search")?.addEventListener("input", () => { void loadAdminProducts(); });
  qs("admin-product-create-btn")?.addEventListener("click", async () => {
    try {
      const payload = {
        name: qs("admin-product-name").value.trim(),
        slug: qs("admin-product-slug").value.trim() || undefined,
        description: qs("admin-product-description").value.trim(),
        category: qs("admin-product-category").value,
        collection: qs("admin-product-collection").value,
        price_cents: Number(qs("admin-product-price").value || 0),
        discount_percent: Number(qs("admin-product-discount").value || 0),
        image_url: qs("admin-product-image-url").value.trim() || "/images/hero.jpg",
        in_stock: qs("admin-product-in-stock").checked ? 1 : 0,
      };
      if (!payload.name || !payload.description || payload.price_cents <= 0) {
        throw new Error("Name, description and price are required");
      }
      const created = await sendJson("/api/admin/products", "POST", payload);
      qs("admin-product-result").textContent = `Added ${created.name}`;
      showToast("Product added");
      ["admin-product-name","admin-product-slug","admin-product-description","admin-product-price","admin-product-discount","admin-product-image-url"].forEach((id) => { if (qs(id)) qs(id).value = ""; });
      qs("admin-product-in-stock").checked = true;
      await loadAdminProducts();
    } catch (e) {
      qs("admin-product-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not add product" });
    }
  });
  if (qs("admin-categories") || qs("admin-collections")) {
    await loadAdminCatalog().catch(() => {});
  }
  qs("admin-category-add-btn")?.addEventListener("click", async () => {
    try {
      const name = qs("admin-category-name").value.trim();
      if (!name) throw new Error("Category name is required");
      await sendJson("/api/admin/categories", "POST", { name });
      qs("admin-category-name").value = "";
      qs("admin-category-result").textContent = "Category added";
      await loadAdminCatalog();
    } catch (e) {
      qs("admin-category-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not add category" });
    }
  });
  qs("admin-collection-add-btn")?.addEventListener("click", async () => {
    try {
      const name = qs("admin-collection-name").value.trim();
      if (!name) throw new Error("Collection name is required");
      await sendJson("/api/admin/collections", "POST", { name, description: "" });
      qs("admin-collection-name").value = "";
      qs("admin-collection-result").textContent = "Collection added";
      await loadAdminCatalog();
    } catch (e) {
      qs("admin-collection-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not add collection" });
    }
  });
  qs("seed-everything-btn")?.addEventListener("click", async () => {
    try {
      await sendJson("/api/seed/everything", "POST");
      showToast("Database reseeded");
      window.location.reload();
    } catch (e) {
      showToast({ type: "error", message: e.message || "Could not reseed" });
    }
  });
}

async function initContact() {
  const btn = qs("contact-send-btn");
  if (!btn) return;
  btn.addEventListener("click", async () => {
    try {
      btn.disabled = true;
      btn.textContent = "Sending...";
      await sendJson("/api/contact", "POST", {
        name: qs("contact-name").value,
        email: qs("contact-email").value,
        subject: qs("contact-subject").value,
        body: qs("contact-body").value,
      });
      qs("contact-result").textContent = "";
      qs("contact-form-wrap")?.classList.add("hidden");
      qs("contact-thanks")?.classList.remove("hidden");
      showToast("Message sent - we'll reply within one business day.");
    } catch (e) {
      qs("contact-result").textContent = e.message;
      showToast({ type: "error", message: e.message || "Could not send message" });
      btn.disabled = false;
      btn.textContent = "Send Message";
    }
  });
}

document.addEventListener("DOMContentLoaded", async () => {
  setupHero3D();
  await initAuth();
  await renderProductsInto("products").catch(() => {});
  await initProduct().catch(() => {});
  await initBuilder().catch(() => {});
  await initCart().catch(() => {});
  await initCheckout().catch(() => {});
  await initAccount().catch(() => {});
  await initAdmin().catch(() => {});
  await initContact().catch(() => {});
  if (window.lucide?.createIcons) {
    window.lucide.createIcons();
  }
});

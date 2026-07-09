/* Peonify — vanilla JS. Loaded in <head> so page scripts can use Cart/money/toast;
   all DOM work waits for DOMContentLoaded. No frameworks. */
"use strict";

/* ---------- money ---------- */
function money(cents) { return "$" + (cents / 100).toFixed(2); }

/* ---------- toast (no native alerts anywhere) ---------- */
function toast(msg, type) {
  var el = document.getElementById("toast");
  if (!el) return;
  el.textContent = msg;
  el.classList.toggle("err", type === "error");
  el.hidden = false;
  clearTimeout(el._t);
  el._t = setTimeout(function () { el.hidden = true; }, 3600);
}

/* ---------- styled confirm dialog (replaces window.confirm) ---------- */
function confirmDialog(message, onYes) {
  var ov = document.createElement("div");
  ov.className = "modal-overlay";
  ov.innerHTML =
    '<div class="modal confirm-box" style="max-width:380px">' +
    '<h3 style="margin-top:0">Are you sure?</h3>' +
    '<p class="muted">' + message.replace(/</g, "&lt;") + "</p>" +
    '<div class="btns"><button class="btn btn-outline" data-c-no>Cancel</button>' +
    '<button class="btn btn-primary" data-c-yes>Confirm</button></div></div>';
  document.body.appendChild(ov);
  refreshIcons();
  ov.addEventListener("click", function (e) {
    if (e.target === ov || e.target.closest("[data-c-no]")) ov.remove();
    if (e.target.closest("[data-c-yes]")) { ov.remove(); onYes(); }
  });
}

/* ---------- lucide icons ---------- */
function refreshIcons() {
  if (window.lucide && typeof lucide.createIcons === "function") lucide.createIcons();
}

/* ---------- cart (localStorage) ---------- */
var Cart = {
  key: "peonify_cart",
  read: function () {
    try { return JSON.parse(localStorage.getItem(this.key)) || []; } catch (e) { return []; }
  },
  write: function (items) {
    localStorage.setItem(this.key, JSON.stringify(items));
    this.badge();
  },
  add: function (item) {
    if (document.body && document.body.dataset.role === "admin") {
      toast("Admin accounts can't place orders."); return;
    }
    var items = this.read();
    if (!item.custom_config) {
      var found = items.find(function (i) { return i.product_id === item.product_id && !i.custom_config; });
      if (found) { found.quantity += 1; this.write(items); toast(item.name + " added to cart"); return; }
    }
    item.line_id = Date.now() + "-" + Math.floor(Math.random() * 1e5);
    item.quantity = 1;
    items.push(item);
    this.write(items);
    toast(item.name + " added to cart");
  },
  setQty: function (lineId, qty) {
    var items = this.read().map(function (i) { if (i.line_id === lineId) i.quantity = qty; return i; })
      .filter(function (i) { return i.quantity > 0; });
    this.write(items);
  },
  remove: function (lineId) {
    this.write(this.read().filter(function (i) { return i.line_id !== lineId; }));
  },
  clear: function () { this.write([]); },
  total: function () {
    return this.read().reduce(function (s, i) { return s + i.unit_price_cents * i.quantity; }, 0);
  },
  count: function () {
    return this.read().reduce(function (s, i) { return s + i.quantity; }, 0);
  },
  badge: function () {
    var n = this.count();
    document.querySelectorAll("[data-cart-count]").forEach(function (b) {
      b.textContent = n;
      b.hidden = n === 0;
    });
  }
};

/* ---------- delegated events (safe to attach immediately) ---------- */
document.addEventListener("click", function (ev) {
  /* add-to-cart buttons */
  var btn = ev.target.closest("[data-add-cart]");
  if (btn) {
    ev.preventDefault();
    ev.stopPropagation();
    Cart.add({
      product_id: parseInt(btn.dataset.id, 10),
      name: btn.dataset.name,
      unit_price_cents: parseInt(btn.dataset.price, 10)
    });
    return;
  }
  /* modals: open via [data-modal="#id"], close via [data-close] or backdrop click */
  var opener = ev.target.closest("[data-modal]");
  if (opener) {
    ev.preventDefault();
    var m = document.querySelector(opener.dataset.modal);
    if (m) { m.hidden = false; refreshIcons(); }
    return;
  }
  var closer = ev.target.closest("[data-close]");
  if (closer) {
    var ov = closer.closest(".modal-overlay");
    if (ov) ov.hidden = true;
    return;
  }
  if (ev.target.classList && ev.target.classList.contains("modal-overlay")) {
    ev.target.hidden = true;
    return;
  }
  /* password visibility eyes */
  var eye = ev.target.closest(".pw-eye");
  if (eye) {
    var input = eye.parentElement.querySelector("input");
    input.type = input.type === "password" ? "text" : "password";
    eye.textContent = input.type === "password" ? "👁" : "🙈";
  }
});

document.addEventListener("submit", function (ev) {
  var f = ev.target;
  if (f.dataset.confirm) {
    ev.preventDefault();
    confirmDialog(f.dataset.confirm, function () { f.submit(); });
  }
});

/* click-to-upload widgets: [data-upload] wraps a hidden file input + preview */
document.addEventListener("change", function (ev) {
  var input = ev.target;
  if (!input.matches("[data-upload] input[type=file]")) return;
  var file = input.files && input.files[0];
  if (!file) return;
  if (file.size > 5 * 1024 * 1024) { toast("Image must be under 5MB", "error"); input.value = ""; return; }
  var wrap = input.closest("[data-upload]");
  var img = wrap.querySelector("img.preview");
  if (!img) {
    img = document.createElement("img");
    img.className = "preview";
    wrap.insertBefore(img, wrap.firstChild);
  }
  img.src = URL.createObjectURL(file);
  var ph = wrap.querySelector(".ph");
  if (ph) ph.hidden = true;
  toast("Photo selected — save to apply");
});

/* ---------- DOM-dependent boot ---------- */
document.addEventListener("DOMContentLoaded", function () {
  refreshIcons();
  Cart.badge();

  /* server flash messages become toasts (like sonner in the React version) */
  var flashes = Array.prototype.slice.call(document.querySelectorAll(".flash"));
  if (flashes.length) {
    var msg = flashes.map(function (f) { return f.textContent.trim(); }).join(" · ");
    var isErr = flashes.some(function (f) { return f.classList.contains("flash-error"); });
    toast(msg, isErr ? "error" : "ok");
    flashes.forEach(function (f) { f.remove(); });
  }

  /* [data-upload] click opens its file picker */
  document.querySelectorAll("[data-upload]").forEach(function (w) {
    w.addEventListener("click", function (e) {
      if (e.target.tagName !== "INPUT") w.querySelector("input[type=file]").click();
    });
  });

  /* preloader: minimum 1.4s so the branding is seen */
  var pre = document.getElementById("preloader");
  if (pre) {
    var shownAt = Date.now();
    var hide = function () {
      var wait = Math.max(0, 1400 - (Date.now() - shownAt));
      setTimeout(function () {
        pre.classList.add("gone");
        setTimeout(function () { pre.remove(); }, 550);
      }, wait);
    };
    if (document.readyState === "complete") hide();
    else window.addEventListener("load", hide);
  }

  /* mobile navs */
  var burger = document.getElementById("navBurger");
  var links = document.getElementById("navLinks");
  if (burger && links) burger.addEventListener("click", function () { links.classList.toggle("open"); });
  var aBurger = document.getElementById("adminBurger");
  var side = document.getElementById("adminSide");
  if (aBurger && side) aBurger.addEventListener("click", function () { side.classList.toggle("open"); });

  /* cookie banner (localStorage consent) */
  var banner = document.getElementById("cookieBanner");
  if (banner) {
    if (!localStorage.getItem("peonify_cookie_consent")) banner.hidden = false;
    banner.querySelectorAll("[data-cookie]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        localStorage.setItem("peonify_cookie_consent", btn.dataset.cookie);
        banner.hidden = true;
      });
    });
  }

  /* review star picker */
  document.querySelectorAll(".review-stars").forEach(function (wrap) {
    var labels = Array.prototype.slice.call(wrap.querySelectorAll("label"));
    var hidden = wrap.querySelector("input[type=hidden]");
    labels.forEach(function (lab, idx) {
      lab.addEventListener("click", function () {
        hidden.value = idx + 1;
        labels.forEach(function (l, j) { l.classList.toggle("on", j <= idx); });
      });
    });
  });
});

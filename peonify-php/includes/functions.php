<?php
/** Shared helpers: sessions, auth, escaping, money, notifications, payments. */
require_once __DIR__ . '/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}

// ---------- output & misc ----------------------------------------------------
function e(?string $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function money(int $cents): string { return '$' . number_format($cents / 100, 2); }

/** Compact money for dashboards: $950 → $950 · 125000¢*… → $1.3k → $1.5M */
function money_compact(int $cents): string {
    $d = $cents / 100;
    if ($d >= 1000000) return '$' . rtrim(rtrim(number_format($d / 1000000, 1), '0'), '.') . 'M';
    if ($d >= 1000)    return '$' . rtrim(rtrim(number_format($d / 1000, 1), '0'), '.') . 'k';
    return money($cents);
}

function effective_price(array $product): int {
    $disc = (int)($product['discount_percent'] ?? 0);
    return $disc > 0 ? (int)round($product['price_cents'] * (100 - $disc) / 100) : (int)$product['price_cents'];
}

function is_new_product(array $product): bool {
    return strtotime($product['created_at']) > strtotime('-14 days');
}

function status_label(string $s): string {
    return ['pending_payment' => 'Awaiting payment', 'paid' => 'Paid', 'delivered' => 'Delivered'][$s] ?? ucfirst($s);
}

// ---------- flash messages ----------------------------------------------------
function flash(string $type, string $msg): void { $_SESSION['flash'][] = [$type, $msg]; }
function take_flashes(): array { $f = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $f; }

// ---------- CSRF ---------------------------------------------------------------
function csrf_token(): string {
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
    return $_SESSION['csrf'];
}
function csrf_field(): string { return '<input type="hidden" name="csrf" value="' . csrf_token() . '">'; }
function csrf_check(): void {
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? null)) {
        http_response_code(400);
        exit('Invalid request token. Go back and try again.');
    }
}

// ---------- auth ---------------------------------------------------------------
function current_user(): ?array {
    static $user = false;
    if ($user !== false) return $user;
    if (empty($_SESSION['uid'])) return $user = null;
    $st = db()->prepare('SELECT id,name,email,role,avatar_url,phone,address,city FROM users WHERE id = ?');
    $st->execute([$_SESSION['uid']]);
    return $user = ($st->fetch() ?: null);
}
function is_admin(): bool { $u = current_user(); return $u && $u['role'] === 'admin'; }

function require_login(string $back = ''): array {
    $u = current_user();
    if (!$u) {
        $_SESSION['after_login'] = $back ?: ($_SERVER['REQUEST_URI'] ?? 'index.php');
        header('Location: ' . rel_root() . 'login.php');
        exit;
    }
    return $u;
}
function require_admin(): array {
    $u = require_login();
    if ($u['role'] !== 'admin') { header('Location: ' . rel_root() . 'index.php'); exit; }
    return $u;
}
/** Relative path back to the app root ("" from root pages, "../" from admin/). */
function rel_root(): string {
    return str_contains(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? ''), '/admin/') ? '../' : '';
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $ini = strtoupper(substr($parts[0] ?? '', 0, 1) . substr($parts[1] ?? '', 0, 1));
    return $ini !== '' ? $ini : 'U';
}

// ---------- notifications ------------------------------------------------------
function notify_user(?int $userId, string $title, string $body, string $link = ''): void {
    if (!$userId) return;
    db()->prepare('INSERT INTO notifications (user_id,title,body,link) VALUES (?,?,?,?)')
        ->execute([$userId, $title, $body, $link]);
}
function notify_admins(string $title, string $body, string $link = ''): void {
    db()->exec("SET @t := 0"); // no-op keepalive
    $st = db()->prepare("INSERT INTO notifications (user_id,title,body,link)
                         SELECT id, ?, ?, ? FROM users WHERE role = 'admin'");
    $st->execute([$title, $body, $link]);
}
function unread_count(int $userId): int {
    $st = db()->prepare('SELECT COUNT(*) c FROM notifications WHERE user_id = ? AND is_read = 0');
    $st->execute([$userId]);
    return (int)$st->fetch()['c'];
}

// ---------- delivery reminders (lazy cron: runs at most every 10 minutes) ------
function maybe_run_reminders(): void {
    $pdo = db();
    $last = $pdo->query("SELECT v FROM settings WHERE k = 'last_reminder_run'")->fetch();
    if ($last && (time() - (int)$last['v']) < 600) return;
    $pdo->prepare("REPLACE INTO settings (k,v) VALUES ('last_reminder_run', ?)")->execute([time()]);

    $orders = $pdo->query("SELECT id, reference, user_id, delivery_date, delivery_window, reminded_1d, reminded_5h
                           FROM orders
                           WHERE status = 'paid' AND user_id IS NOT NULL
                             AND (reminded_1d = 0 OR reminded_5h = 0)
                             AND delivery_date >= CURDATE()")->fetchAll();
    foreach ($orders as $o) {
        $startHour = (int)explode(':', trim(explode('-', $o['delivery_window'])[0]))[0] ?: 9;
        $start = strtotime($o['delivery_date'] . ' ' . $startHour . ':00');
        if ($start < time()) continue;
        $hoursLeft = ($start - time()) / 3600;
        if (!$o['reminded_1d'] && $hoursLeft <= 24) {
            notify_user((int)$o['user_id'], 'Delivery tomorrow — ' . $o['reference'],
                'Your flowers arrive ' . date('l, F j', strtotime($o['delivery_date'])) . ' between ' . $o['delivery_window'] . '. Make sure someone is around!');
            $pdo->prepare('UPDATE orders SET reminded_1d = 1 WHERE id = ?')->execute([$o['id']]);
        }
        if (!$o['reminded_5h'] && $hoursLeft <= 5) {
            notify_user((int)$o['user_id'], 'Delivery today — ' . $o['reference'],
                'Your flowers arrive today between ' . $o['delivery_window'] . ". They're being arranged right now.");
            $pdo->prepare('UPDATE orders SET reminded_5h = 1 WHERE id = ?')->execute([$o['id']]);
        }
    }
}

// ---------- Paystack gateway (mock mode when no key is configured) -------------
function paystack_enabled(): bool { return PAYSTACK_SECRET_KEY !== ''; }

/** @return array{payment_ref:string, authorization_url:?string, paid:bool} */
function create_payment(int $amountCents, string $email, string $callbackUrl): array {
    if (!paystack_enabled()) {
        return ['payment_ref' => 'py_mock_' . time() . '_' . $amountCents, 'authorization_url' => null, 'paid' => true];
    }
    $body = ['email' => $email, 'amount' => $amountCents, 'callback_url' => $callbackUrl];
    if (PAYSTACK_CURRENCY !== '') $body['currency'] = PAYSTACK_CURRENCY;
    $res = paystack_request('POST', 'https://api.paystack.co/transaction/initialize', $body);
    if (empty($res['status']) || empty($res['data'])) {
        throw new RuntimeException('Paystack initialize failed: ' . ($res['message'] ?? 'unknown error'));
    }
    return ['payment_ref' => $res['data']['reference'], 'authorization_url' => $res['data']['authorization_url'], 'paid' => false];
}

function verify_payment(string $reference): bool {
    if (!paystack_enabled()) return str_starts_with($reference, 'py_mock_');
    $res = paystack_request('GET', 'https://api.paystack.co/transaction/verify/' . rawurlencode($reference));
    return !empty($res['status']) && (($res['data']['status'] ?? '') === 'success');
}

function paystack_request(string $method, string $url, ?array $body = null): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . PAYSTACK_SECRET_KEY, 'Content-Type: application/json'],
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    $raw = curl_exec($ch);
    curl_close($ch);
    return $raw ? (json_decode($raw, true) ?: []) : [];
}

// ---------- uploads -------------------------------------------------------------
/** Validates and stores an uploaded image; returns its relative URL or null. */
function save_upload(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > 5 * 1024 * 1024) return null;
    $mime = mime_content_type($file['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) return null;
    $name = 'uploads/' . time() . '-' . random_int(1000, 999999) . '.' . $ext;
    $dest = dirname(__DIR__) . '/' . $name;
    if (!is_dir(dirname($dest))) mkdir(dirname($dest), 0775, true);
    return move_uploaded_file($file['tmp_name'], $dest) ? $name : null;
}

function make_reference(): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $ref = 'PNY-';
    for ($i = 0; $i < 6; $i++) $ref .= $chars[random_int(0, strlen($chars) - 1)];
    return $ref;
}

/** Simple pagination helper: returns [items, page, pages, total]. */
function paginate(array $items, int $perPage = 8): array {
    $total = count($items);
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    return [array_slice($items, ($page - 1) * $perPage, $perPage), $page, $pages, $total];
}

/** Renders a storefront product card (used by index.php and shop.php). */
function ui_product_card(array $p): void { ?>
  <a class="card product-card" href="product.php?slug=<?= e($p['slug']) ?>">
    <img class="photo" src="<?= e($p['image_url']) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
    <span class="tags">
      <?php if ($p['discount_percent'] > 0): ?><span class="badge badge-sale">-<?= (int)$p['discount_percent'] ?>%</span><?php endif; ?>
      <?php if (is_new_product($p)): ?><span class="badge">New</span><?php endif; ?>
      <?php if (!$p['in_stock']): ?><span class="badge badge-soft">Out of stock</span><?php endif; ?>
    </span>
    <span class="body">
      <span class="badge badge-soft" style="align-self:flex-start;text-transform:capitalize"><?= e($p['collection']) ?></span>
      <h3><?= e($p['name']) ?></h3>
      <span class="card-row">
        <span class="price"><?= money(effective_price($p)) ?>
          <?php if ($p['discount_percent'] > 0): ?><s><?= money((int)$p['price_cents']) ?></s><?php endif; ?>
        </span>
        <?php if (!is_admin() && $p['in_stock']): ?>
        <button class="btn btn-outline btn-sm" data-add-cart data-id="<?= (int)$p['id'] ?>"
          data-name="<?= e($p['name']) ?>" data-price="<?= effective_price($p) ?>"><i data-lucide="shopping-bag"></i> Add</button>
        <?php endif; ?>
      </span>
    </span>
  </a>
<?php }

function pager_links(int $page, int $pages, array $extraParams = []): string {
    if ($pages <= 1) return '';
    $qs = fn(int $p) => '?' . http_build_query(array_merge($_GET, $extraParams, ['page' => $p]));
    $html = '<div class="pager"><span>Page ' . $page . ' of ' . $pages . '</span><span class="pager-btns">';
    $html .= $page > 1 ? '<a class="btn btn-outline btn-sm" href="' . e($qs($page - 1)) . '">‹ Prev</a>' : '';
    $html .= $page < $pages ? '<a class="btn btn-outline btn-sm" href="' . e($qs($page + 1)) . '">Next ›</a>' : '';
    return $html . '</span></div>';
}

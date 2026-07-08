<?php
declare(strict_types=1);

require_once __DIR__ . '/../MODELs/ProductModel.php';
require_once __DIR__ . '/../MODELs/OrderModel.php';
require_once __DIR__ . '/../MODELs/UserModel.php';

class ApiController
{
    public function __construct(private readonly Database $db)
    {
    }

    public function handle(string $route): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        header('Content-Type: application/json');
        set_exception_handler(function (Throwable $e): void {
            if (!headers_sent()) {
                http_response_code(500);
                header('Content-Type: application/json');
            }
            echo json_encode([
                'error' => 'Internal server error',
                'details' => $e->getMessage(),
            ]);
        });
        try {
            $this->db->migrate();
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Database connection failed',
                'details' => $e->getMessage(),
            ]);
            return;
        }

        $productModel = new ProductModel($this->db);
        $orderModel = new OrderModel($this->db);
        $userModel = new UserModel($this->db);
        $pdo = $this->db->getConnection();
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $body = json_decode(file_get_contents('php://input'), true) ?? [];

        if ($route === 'api/products' && $method === 'GET') {
            echo json_encode($productModel->all());
            return;
        }

        if ($route === 'api/categories' && $method === 'GET') {
            echo json_encode($pdo->query("SELECT id, slug, name FROM categories ORDER BY name ASC")->fetchAll());
            return;
        }

        if ($route === 'api/collections' && $method === 'GET') {
            echo json_encode($pdo->query("SELECT id, slug, name, description FROM collections ORDER BY name ASC")->fetchAll());
            return;
        }

        if ($route === 'api/metrics' && $method === 'GET') {
            echo json_encode([
                'revenue_by_day' => $orderModel->revenueByDay(),
                'orders_by_status' => $orderModel->statusCounts(),
                'top_products' => $productModel->topProducts(),
            ]);
            return;
        }

        if ($route === 'api/seed/everything' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $this->db->seedEverything(true);
            echo json_encode(['ok' => true, 'message' => 'Database reseeded']);
            return;
        }

        if ($route === 'api/auth/signup' && $method === 'POST') {
            $user = $userModel->create(
                (string) ($body['name'] ?? ''),
                (string) ($body['email'] ?? ''),
                (string) ($body['password'] ?? '')
            );
            $_SESSION['user_id'] = (int) $user['id'];
            echo json_encode(['user' => $user]);
            return;
        }

        if ($route === 'api/auth/login' && $method === 'POST') {
            $user = $userModel->verify((string) ($body['email'] ?? ''), (string) ($body['password'] ?? ''));
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Invalid credentials']);
                return;
            }
            $_SESSION['user_id'] = (int) $user['id'];
            echo json_encode(['user' => $user]);
            return;
        }

        if ($route === 'api/auth/logout' && $method === 'POST') {
            $_SESSION = [];
            session_destroy();
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/auth/me' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Not authenticated']);
                return;
            }
            echo json_encode(['user' => $user]);
            return;
        }

        if ($route === 'api/payments/config' && $method === 'GET') {
            echo json_encode(['provider' => $this->paystackSecretKey() ? 'paystack' : 'mock']);
            return;
        }

        if ($route === 'api/cart' && $method === 'GET') {
            echo json_encode(['items' => $_SESSION['cart'] ?? []]);
            return;
        }

        if ($route === 'api/cart/add' && $method === 'POST') {
            $_SESSION['cart'] ??= [];
            $_SESSION['cart'][] = [
                'line_id' => bin2hex(random_bytes(8)),
                'product_id' => (int) ($body['product_id'] ?? 0),
                'name' => (string) ($body['name'] ?? ''),
                'quantity' => max(1, (int) ($body['quantity'] ?? 1)),
                'unit_price_cents' => (int) ($body['unit_price_cents'] ?? 0),
                'custom_config' => $body['custom_config'] ?? null,
            ];
            echo json_encode(['items' => $_SESSION['cart']]);
            return;
        }

        if ($route === 'api/cart/update' && $method === 'POST') {
            $line = (string) ($body['line_id'] ?? '');
            $qty = (int) ($body['quantity'] ?? 1);
            $_SESSION['cart'] ??= [];
            foreach ($_SESSION['cart'] as $i => $item) {
                if (($item['line_id'] ?? '') === $line) {
                    if ($qty <= 0) {
                        array_splice($_SESSION['cart'], $i, 1);
                    } else {
                        $_SESSION['cart'][$i]['quantity'] = $qty;
                    }
                    break;
                }
            }
            echo json_encode(['items' => $_SESSION['cart']]);
            return;
        }

        if ($route === 'api/cart/remove' && $method === 'POST') {
            $line = (string) ($body['line_id'] ?? '');
            $_SESSION['cart'] ??= [];
            $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($it) => ($it['line_id'] ?? '') !== $line));
            echo json_encode(['items' => $_SESSION['cart']]);
            return;
        }

        if ($route === 'api/cart/clear' && $method === 'POST') {
            $_SESSION['cart'] = [];
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/orders/checkout' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $cart = $_SESSION['cart'] ?? [];
            if (!$cart) {
                http_response_code(400);
                echo json_encode(['error' => 'Cart is empty']);
                return;
            }
            $total = 0;
            foreach ($cart as $it) {
                $total += ((int) $it['unit_price_cents']) * ((int) $it['quantity']);
            }
            $paystack = $this->initializePaystack(
                $total,
                (string) ($body['email'] ?? $user['email']),
                '/payment/callback'
            );
            $paymentRef = $paystack['reference'] ?? ('py_mock_' . time() . '_' . bin2hex(random_bytes(3)));
            $status = $paystack ? 'pending_payment' : 'paid';
            $order = $orderModel->createOrder([
                'customer_name' => (string) ($body['customer_name'] ?? $user['name']),
                'email' => (string) ($body['email'] ?? $user['email']),
                'phone' => (string) ($body['phone'] ?? ($user['phone'] ?? '')),
                'address' => (string) ($body['address'] ?? ''),
                'delivery_date' => $body['delivery_date'] ?? null,
                'delivery_window' => (string) ($body['delivery_window'] ?? ''),
                'order_type' => (string) ($body['order_type'] ?? 'on_demand'),
                'gift_note' => (string) ($body['gift_note'] ?? ''),
                'payment_ref' => $paymentRef,
                'status' => $status,
                'items' => $cart,
                'total_cents' => $total,
            ], (int) $user['id']);
            if ($paystack !== null) {
                $order['authorization_url'] = $paystack['authorization_url'];
                $order['payment_ref'] = $paystack['reference'];
            }
            $_SESSION['cart'] = [];
            echo json_encode($order);
            return;
        }

        if ($route === 'api/me/orders' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            echo json_encode($orderModel->userOrders((int) $user['id']));
            return;
        }

        if ($route === 'api/me/notifications' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            echo json_encode($userModel->notifications((int) $user['id']));
            return;
        }

        if ($route === 'api/admin/orders' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode($orderModel->allOrders());
            return;
        }

        if ($route === 'api/admin/stats' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $orders = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
            $revenue = (int) $pdo->query("SELECT COALESCE(SUM(total_cents),0) FROM orders")->fetchColumn();
            $active = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE status='paid'")->fetchColumn();
            $products = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
            $customers = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
            echo json_encode([
                'total_orders' => $orders,
                'revenue_cents' => $revenue,
                'active_orders' => $active,
                'products' => $products,
                'customers' => $customers,
            ]);
            return;
        }

        if ($route === 'api/admin/metrics' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode([
                'revenue_by_day' => $orderModel->revenueByDay(),
                'orders_by_status' => $orderModel->statusCounts(),
                'top_products' => $productModel->topProducts(),
            ]);
            return;
        }

        if ($route === 'api/admin/reviews' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $rows = $pdo->query("
                SELECT r.id, r.rating, r.comment, r.created_at, u.name AS author, p.name AS product_name
                FROM reviews r JOIN users u ON u.id=r.user_id JOIN products p ON p.id=r.product_id
                ORDER BY r.created_at DESC LIMIT 200
            ")->fetchAll();
            echo json_encode($rows);
            return;
        }

        if (preg_match('#^api/admin/reviews/(\d+)$#', $route, $m) && $method === 'DELETE') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM reviews WHERE id = ?");
            $stmt->execute([(int) $m[1]]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/admin/messages' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode($pdo->query("SELECT * FROM messages ORDER BY created_at DESC LIMIT 200")->fetchAll());
            return;
        }

        if (preg_match('#^api/admin/messages/(\d+)$#', $route, $m) && $method === 'DELETE') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM messages WHERE id = ?");
            $stmt->execute([(int) $m[1]]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/admin/activity' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode($pdo->query("SELECT reference, status, customer_name AS note, created_at FROM orders ORDER BY created_at DESC LIMIT 100")->fetchAll());
            return;
        }

        if ($route === 'api/admin/upload' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'No image uploaded']);
                return;
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $name = 'upload_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $target = __DIR__ . '/../public/images/' . $name;
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
            echo json_encode(['url' => '/images/' . $name]);
            return;
        }

        if (preg_match('#^api/products/([^/]+)$#', $route, $m) && $method === 'GET') {
            $one = $productModel->oneBySlug(urldecode($m[1]));
            if (!$one) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
                return;
            }
            echo json_encode($one);
            return;
        }

        if (preg_match('#^api/products/([^/]+)/reviews$#', $route, $m) && $method === 'GET') {
            $slug = urldecode($m[1]);
            $stmt = $pdo->prepare("
              SELECT r.id, r.rating, r.comment, r.created_at, u.name AS author, u.avatar_url
              FROM reviews r JOIN products p ON p.id=r.product_id JOIN users u ON u.id=r.user_id
              WHERE p.slug = ? ORDER BY r.created_at DESC
            ");
            $stmt->execute([$slug]);
            echo json_encode($stmt->fetchAll());
            return;
        }

        if (preg_match('#^api/products/([^/]+)/reviews$#', $route, $m) && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $slug = urldecode($m[1]);
            $pidStmt = $pdo->prepare("SELECT id FROM products WHERE slug = ?");
            $pidStmt->execute([$slug]);
            $pid = $pidStmt->fetchColumn();
            if (!$pid) {
                http_response_code(404);
                echo json_encode(['error' => 'Product not found']);
                return;
            }
            $rating = max(1, min(5, (int) ($body['rating'] ?? 0)));
            $comment = (string) ($body['comment'] ?? '');
            $stmt = $pdo->prepare("
                INSERT INTO reviews (user_id, product_id, rating, comment) VALUES (?,?,?,?)
                ON DUPLICATE KEY UPDATE rating=VALUES(rating), comment=VALUES(comment), created_at=CURRENT_TIMESTAMP
            ");
            $stmt->execute([(int) $user['id'], (int) $pid, $rating, $comment]);
            echo json_encode(['ok' => true]);
            return;
        }

        if (preg_match('#^api/orders/([^/]+)/deliver$#', $route, $m) && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $reference = urldecode($m[1]);
            $q = $pdo->prepare("SELECT id, user_id, status FROM orders WHERE reference = ?");
            $q->execute([$reference]);
            $order = $q->fetch();
            if (!$order) {
                http_response_code(404);
                echo json_encode(['error' => 'Order not found']);
                return;
            }
            if ($user['role'] !== 'admin' && (int) $order['user_id'] !== (int) $user['id']) {
                http_response_code(403);
                echo json_encode(['error' => 'Forbidden']);
                return;
            }
            if ($order['status'] === 'delivered') {
                echo json_encode(['ok' => true, 'status' => 'delivered']);
                return;
            }
            $u = $pdo->prepare("UPDATE orders SET status='delivered' WHERE id = ?");
            $u->execute([(int) $order['id']]);
            echo json_encode(['ok' => true, 'status' => 'delivered']);
            return;
        }

        if ($route === 'api/me/notifications/read' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $stmt = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?");
            $stmt->execute([(int) $user['id']]);
            echo json_encode(['ok' => true]);
            return;
        }

        if (preg_match('#^api/me/notifications/(\d+)/read$#', $route, $m) && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $stmt = $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?");
            $stmt->execute([(int) $m[1], (int) $user['id']]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/me/profile' && $method === 'PUT') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $stmt = $pdo->prepare("UPDATE users SET name=?, phone=?, address=?, city=? WHERE id=?");
            $stmt->execute([
                (string) ($body['name'] ?? $user['name']),
                (string) ($body['phone'] ?? ''),
                (string) ($body['address'] ?? ''),
                (string) ($body['city'] ?? ''),
                (int) $user['id']
            ]);
            echo json_encode(['user' => $userModel->find((int) $user['id'])]);
            return;
        }

        if ($route === 'api/me/password' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $cur = (string) ($body['current_password'] ?? '');
            $new = (string) ($body['new_password'] ?? '');
            if (strlen($new) < 8) {
                http_response_code(400);
                echo json_encode(['error' => 'New password must be at least 8 characters']);
                return;
            }
            $auth = $pdo->prepare("SELECT password_hash FROM users WHERE id=?");
            $auth->execute([(int) $user['id']]);
            $hash = (string) $auth->fetchColumn();
            if (!$hash || !password_verify($cur, $hash)) {
                http_response_code(401);
                echo json_encode(['error' => 'Current password is incorrect']);
                return;
            }
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $u = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
            $u->execute([$newHash, (int) $user['id']]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/me/avatar' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
                http_response_code(400);
                echo json_encode(['error' => 'No image uploaded']);
                return;
            }
            $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION) ?: 'jpg';
            $name = 'avatar_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
            $target = __DIR__ . '/../public/images/' . $name;
            move_uploaded_file($_FILES['image']['tmp_name'], $target);
            $url = '/images/' . $name;
            $u = $pdo->prepare("UPDATE users SET avatar_url=? WHERE id=?");
            $u->execute([$url, (int) $user['id']]);
            echo json_encode(['url' => $url]);
            return;
        }

        if ($route === 'api/me/images' && $method === 'GET') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $stmt = $pdo->prepare("SELECT id, url, created_at FROM user_images WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->execute([(int) $user['id']]);
            echo json_encode($stmt->fetchAll());
            return;
        }

        if ($route === 'api/me/images' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user) {
                http_response_code(401);
                echo json_encode(['error' => 'Login required']);
                return;
            }
            $files = $_FILES['images'] ?? $_FILES['images'] ?? null;
            if (!$files || !isset($files['tmp_name']) || !is_array($files['tmp_name'])) {
                http_response_code(400);
                echo json_encode(['error' => 'No images uploaded']);
                return;
            }
            $uploaded = 0;
            $insert = $pdo->prepare("INSERT INTO user_images (user_id, url) VALUES (?, ?)");
            foreach ($files['tmp_name'] as $idx => $tmpName) {
                if (($files['error'][$idx] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !$tmpName) {
                    continue;
                }
                $ext = pathinfo((string) ($files['name'][$idx] ?? ''), PATHINFO_EXTENSION) ?: 'jpg';
                $name = 'user_' . (int) $user['id'] . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $target = __DIR__ . '/../public/images/' . $name;
                if (@move_uploaded_file($tmpName, $target)) {
                    $insert->execute([(int) $user['id'], '/images/' . $name]);
                    $uploaded++;
                }
            }
            if ($uploaded === 0) {
                http_response_code(400);
                echo json_encode(['error' => 'No valid images were uploaded']);
                return;
            }
            echo json_encode(['ok' => true, 'uploaded' => $uploaded]);
            return;
        }

        if ($route === 'api/contact' && $method === 'POST') {
            $name = trim((string) ($body['name'] ?? ''));
            $email = trim((string) ($body['email'] ?? ''));
            $subject = trim((string) ($body['subject'] ?? ''));
            $msg = trim((string) ($body['body'] ?? ''));
            if ($name === '' || $email === '' || $msg === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Name, email and message are required']);
                return;
            }
            $stmt = $pdo->prepare("INSERT INTO messages (name,email,subject,body) VALUES (?,?,?,?)");
            $stmt->execute([$name, $email, $subject, $msg]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/admin/products' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode($productModel->create($body));
            return;
        }

        if ($route === 'api/admin/categories' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $name = trim((string) ($body['name'] ?? ''));
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Name is required']);
                return;
            }
            $slug = $this->slugify($name);
            $stmt = $pdo->prepare("INSERT INTO categories (slug, name) VALUES (?, ?)");
            $stmt->execute([$slug, $name]);
            echo json_encode(['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'name' => $name]);
            return;
        }

        if (preg_match('#^api/admin/categories/(\d+)$#', $route, $m) && $method === 'DELETE') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([(int) $m[1]]);
            echo json_encode(['ok' => true]);
            return;
        }

        if ($route === 'api/admin/collections' && $method === 'POST') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $name = trim((string) ($body['name'] ?? ''));
            $description = trim((string) ($body['description'] ?? ''));
            if ($name === '') {
                http_response_code(400);
                echo json_encode(['error' => 'Name is required']);
                return;
            }
            $slug = $this->slugify($name);
            $stmt = $pdo->prepare("INSERT INTO collections (slug, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$slug, $name, $description]);
            echo json_encode(['id' => (int) $pdo->lastInsertId(), 'slug' => $slug, 'name' => $name, 'description' => $description]);
            return;
        }

        if (preg_match('#^api/admin/collections/(\d+)$#', $route, $m) && $method === 'DELETE') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $stmt = $pdo->prepare("DELETE FROM collections WHERE id = ?");
            $stmt->execute([(int) $m[1]]);
            echo json_encode(['ok' => true]);
            return;
        }

        if (preg_match('#^api/admin/products/(\d+)$#', $route, $m) && $method === 'PUT') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            $updated = $productModel->update((int) $m[1], $body);
            if (!$updated) {
                http_response_code(404);
                echo json_encode(['error' => 'Not found']);
                return;
            }
            echo json_encode($updated);
            return;
        }

        if (preg_match('#^api/admin/products/(\d+)$#', $route, $m) && $method === 'DELETE') {
            $user = $this->currentUser($userModel);
            if (!$user || $user['role'] !== 'admin') {
                http_response_code(403);
                echo json_encode(['error' => 'Admin only']);
                return;
            }
            echo json_encode(['ok' => $productModel->delete((int) $m[1])]);
            return;
        }

        http_response_code(404);
        echo json_encode(['error' => 'Not found']);
    }

    private function currentUser(UserModel $users): ?array
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return null;
        }
        return $users->find($userId);
    }

    private function paystackSecretKey(): ?string
    {
        $fromEnv = trim((string) (getenv('PAYSTACK_SECRET_KEY') ?: ''));
        if ($fromEnv !== '') {
            return $fromEnv;
        }
        $envFile = __DIR__ . '/../../backend/.env';
        if (!is_file($envFile)) {
            return null;
        }
        $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }
            [$k, $v] = explode('=', $line, 2);
            if (trim($k) === 'PAYSTACK_SECRET_KEY') {
                $value = trim($v, " \t\n\r\0\x0B\"'");
                return $value !== '' ? $value : null;
            }
        }
        return null;
    }

    private function initializePaystack(int $amountCents, string $email, string $callbackPath): ?array
    {
        $secret = $this->paystackSecretKey();
        if (!$secret) {
            return null;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost:8080';
        $payload = [
            'email' => $email,
            'amount' => $amountCents,
            'callback_url' => $scheme . '://' . $host . $callbackPath,
        ];
        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Authorization: Bearer {$secret}\r\nContent-Type: application/json\r\n",
                'content' => json_encode($payload),
                'timeout' => 20,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents('https://api.paystack.co/transaction/initialize', false, $context);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded) || !($decoded['status'] ?? false) || !isset($decoded['data']['authorization_url'])) {
            return null;
        }
        return [
            'authorization_url' => (string) $decoded['data']['authorization_url'],
            'reference' => (string) ($decoded['data']['reference'] ?? ''),
        ];
    }

    private function slugify(string $name): string
    {
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');
        if ($slug === '') {
            $slug = 'item-' . bin2hex(random_bytes(3));
        }
        return $slug;
    }
}

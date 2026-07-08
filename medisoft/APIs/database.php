<?php
declare(strict_types=1);

class Database
{
    private string $host = '127.0.0.1';
    private string $port = '3306';
    private string $name = 'peonify_php';
    private string $user = 'root';
    private string $pass = 'root';

    public function getConnection(): PDO
    {
        $host = getenv('MYSQL_HOST') ?: $this->host;
        $port = getenv('MYSQL_PORT') ?: $this->port;
        $name = getenv('MYSQL_DATABASE') ?: $this->name;
        $user = getenv('MYSQL_USER') ?: $this->user;
        $pass = getenv('MYSQL_PASSWORD') ?: $this->pass;

        $bootstrapDsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        $bootstrap = new PDO($bootstrapDsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $bootstrap->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $dsn = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        return $pdo;
    }

    public function migrate(): void
    {
        $pdo = $this->getConnection();
        $schema = "
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(120) UNIQUE NOT NULL,
            name VARCHAR(160) NOT NULL,
            description TEXT NOT NULL,
            category VARCHAR(60) NOT NULL,
            collection VARCHAR(60) NOT NULL,
            price_cents INT NOT NULL,
            image_url VARCHAR(255) NOT NULL DEFAULT '',
            discount_percent INT NOT NULL DEFAULT 0,
            in_stock TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reference VARCHAR(40) UNIQUE NOT NULL,
            customer_name VARCHAR(160) NOT NULL,
            email VARCHAR(180) NOT NULL,
            user_id INT NULL,
            total_cents INT NOT NULL,
            status VARCHAR(40) NOT NULL DEFAULT 'paid',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            delivery_date DATE NULL,
            delivery_window VARCHAR(40) NOT NULL DEFAULT '',
            order_type VARCHAR(30) NOT NULL DEFAULT 'on_demand',
            gift_note TEXT NULL,
            payment_ref VARCHAR(120) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT NULL,
            name VARCHAR(180) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price_cents INT NOT NULL,
            FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            email VARCHAR(180) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            role VARCHAR(20) NOT NULL DEFAULT 'customer',
            avatar_url VARCHAR(255) NOT NULL DEFAULT '',
            phone VARCHAR(40) NOT NULL DEFAULT '',
            address VARCHAR(255) NOT NULL DEFAULT '',
            city VARCHAR(120) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) UNIQUE NOT NULL,
            name VARCHAR(120) NOT NULL
        );

        CREATE TABLE IF NOT EXISTS collections (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(80) UNIQUE NOT NULL,
            name VARCHAR(120) NOT NULL,
            description VARCHAR(255) NOT NULL DEFAULT ''
        );

        CREATE TABLE IF NOT EXISTS reviews (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            product_id INT NOT NULL,
            rating INT NOT NULL,
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_user_product (user_id, product_id),
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY(product_id) REFERENCES products(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(180) NOT NULL,
            body TEXT NOT NULL,
            link VARCHAR(255) NOT NULL DEFAULT '',
            is_read TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS order_events (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            status VARCHAR(40) NOT NULL,
            note VARCHAR(255) NOT NULL DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
        );

        CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(140) NOT NULL,
            email VARCHAR(180) NOT NULL,
            subject VARCHAR(180) NOT NULL DEFAULT '',
            body TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS user_images (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            url VARCHAR(255) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE
        );
        ";
        $pdo->exec($schema);
        $colCheck = $pdo->prepare("
            SELECT COUNT(*) FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = 'user_id'
        ");
        $colCheck->execute();
        if ((int) $colCheck->fetchColumn() === 0) {
            $pdo->exec("ALTER TABLE orders ADD COLUMN user_id INT NULL");
        }
        $requiredOrderCols = [
            'phone' => "ALTER TABLE orders ADD COLUMN phone VARCHAR(40) NOT NULL DEFAULT ''",
            'address' => "ALTER TABLE orders ADD COLUMN address VARCHAR(255) NOT NULL DEFAULT ''",
            'delivery_date' => "ALTER TABLE orders ADD COLUMN delivery_date DATE NULL",
            'delivery_window' => "ALTER TABLE orders ADD COLUMN delivery_window VARCHAR(40) NOT NULL DEFAULT ''",
            'order_type' => "ALTER TABLE orders ADD COLUMN order_type VARCHAR(30) NOT NULL DEFAULT 'on_demand'",
            'gift_note' => "ALTER TABLE orders ADD COLUMN gift_note TEXT NULL",
            'payment_ref' => "ALTER TABLE orders ADD COLUMN payment_ref VARCHAR(120) NOT NULL DEFAULT ''",
        ];
        foreach ($requiredOrderCols as $col => $alterSql) {
            $chk = $pdo->prepare("
                SELECT COUNT(*) FROM information_schema.columns
                WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = ?
            ");
            $chk->execute([$col]);
            if ((int) $chk->fetchColumn() === 0) {
                $pdo->exec($alterSql);
            }
        }
        $eventCountStmt = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'order_events'");
        if ((int) $eventCountStmt->fetchColumn() === 0) {
            $pdo->exec("
                CREATE TABLE order_events (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    order_id INT NOT NULL,
                    status VARCHAR(40) NOT NULL,
                    note VARCHAR(255) NOT NULL DEFAULT '',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY(order_id) REFERENCES orders(id) ON DELETE CASCADE
                )
            ");
        }

        $count = (int) $pdo->query("SELECT COUNT(*) FROM products")->fetchColumn();
        if ($count === 0) {
            $pdo->exec("
                INSERT INTO products (slug,name,description,category,collection,price_cents,image_url,discount_percent,in_stock) VALUES
                ('blush-peony-dream','Blush Peony Dream','Hand-selected blush peonies at peak bloom.','bouquet','signature',12900,'/images/blush-peony-dream.jpg',0,1),
                ('velvet-crush','Velvet Crush','Deep magenta peonies with plum dahlias.','bouquet','summer',13400,'/images/velvet-crush.jpg',20,1),
                ('golden-hour','Golden Hour','Amber roses and butterscotch ranunculus.','arrangement','summer',11200,'/images/golden-hour.jpg',15,1),
                ('petite-poeme','Petite Poeme','Small crafted posy for elegant gifting.','bouquet','spring',5400,'/images/petite-poeme.jpg',10,1)
            ");
        }
        $pdo->exec("UPDATE products SET image_url = REPLACE(image_url, '/frontend/public/images/', '/images/')");

        $userCount = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($userCount === 0) {
            $hash = password_hash('peonify-admin', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,?,?)");
            $stmt->execute(['Atelier Admin', 'admin@peonify.com', $hash, 'admin']);
        }

        $categoryCount = (int) $pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn();
        if ($categoryCount === 0) {
            $pdo->exec("
                INSERT INTO categories (slug,name) VALUES
                ('bouquet','Bouquets'),
                ('arrangement','Arrangements'),
                ('stems','Premium Stems')
            ");
        }

        $collectionCount = (int) $pdo->query("SELECT COUNT(*) FROM collections")->fetchColumn();
        if ($collectionCount === 0) {
            $pdo->exec("
                INSERT INTO collections (slug,name,description) VALUES
                ('signature','Signature','Our house icons'),
                ('spring','Spring','Fresh seasonal stems'),
                ('summer','Summer','Sun-drenched tones')
            ");
        }

        $this->seedEverything(false);
    }

    public function seedEverything(bool $forceReset = false): void
    {
        $pdo = $this->getConnection();
        if ($forceReset) {
            $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
            $pdo->exec("TRUNCATE TABLE notifications");
            $pdo->exec("TRUNCATE TABLE reviews");
            $pdo->exec("TRUNCATE TABLE order_items");
            $pdo->exec("TRUNCATE TABLE orders");
            $pdo->exec("TRUNCATE TABLE messages");
            $pdo->exec("DELETE FROM users WHERE role = 'customer'");
            $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
        }

        $customerCount = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn();
        if ($customerCount === 0) {
            $hash = password_hash('customer123', PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,phone,address,city,avatar_url) VALUES (?,?,?,?,?,?,?,?)");
            $stmt->execute(['Clara Bloom', 'clara@peonify.com', $hash, 'customer', '+250788000101', 'KG 19 Ave 3', 'Kigali', '/images/hero.jpg']);
            $stmt->execute(['Noah Reed', 'noah@peonify.com', $hash, 'customer', '+250788000102', 'KN 5 Rd 12', 'Kigali', '/images/golden-hour.jpg']);
        }

        $orderCount = (int) $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
        if ($orderCount === 0) {
            $customers = $pdo->query("SELECT id,name,email FROM users WHERE role='customer' ORDER BY id ASC")->fetchAll();
            $products = $pdo->query("SELECT id,name,price_cents FROM products ORDER BY id ASC")->fetchAll();
            if ($customers && $products) {
                $oStmt = $pdo->prepare("INSERT INTO orders (reference, customer_name, email, user_id, total_cents, status, created_at) VALUES (?,?,?,?,?,?,?)");
                $iStmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, name, quantity, unit_price_cents) VALUES (?,?,?,?,?)");
                $nStmt = $pdo->prepare("INSERT INTO notifications (user_id,title,body,link,is_read,created_at) VALUES (?,?,?,?,?,?)");
                for ($i = 0; $i < 8; $i++) {
                    $c = $customers[$i % count($customers)];
                    $p = $products[$i % count($products)];
                    $q = ($i % 2) + 1;
                    $total = (int) $p['price_cents'] * $q;
                    $status = $i < 5 ? 'delivered' : 'paid';
                    $ref = 'PNY-SEED' . str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT);
                    $created = date('Y-m-d H:i:s', strtotime("-" . (9 - $i) . " days"));
                    $oStmt->execute([$ref, $c['name'], $c['email'], (int) $c['id'], $total, $status, $created]);
                    $orderId = (int) $pdo->lastInsertId();
                    $iStmt->execute([$orderId, (int) $p['id'], $p['name'], $q, (int) $p['price_cents']]);
                    $nStmt->execute([(int) $c['id'], "Order {$ref} {$status}", "Your order {$ref} is now {$status}.", '/account', 0, $created]);
                }
            }
        }

        $messageCount = (int) $pdo->query("SELECT COUNT(*) FROM messages")->fetchColumn();
        if ($messageCount === 0) {
            $pdo->exec("
                INSERT INTO messages (name,email,subject,body) VALUES
                ('Alice Event Planner','alice@events.co','Wedding florals','Need 120 premium stems for August wedding.'),
                ('Mark Office Admin','mark@office.rw','Corporate weekly flowers','Can we schedule recurring lobby arrangements?')
            ");
        }

        $reviewCount = (int) $pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn();
        if ($reviewCount === 0) {
            $customer = $pdo->query("SELECT id FROM users WHERE role='customer' ORDER BY id LIMIT 1")->fetchColumn();
            $product = $pdo->query("SELECT id FROM products ORDER BY id LIMIT 1")->fetchColumn();
            if ($customer && $product) {
                $stmt = $pdo->prepare("INSERT INTO reviews (user_id,product_id,rating,comment) VALUES (?,?,?,?)");
                $stmt->execute([(int) $customer, (int) $product, 5, 'Perfect bouquet and right-on-time delivery.']);
            }
        }
    }
}

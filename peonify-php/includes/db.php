<?php
/**
 * Database bootstrap: connects with PDO, creates the database and all
 * tables if they don't exist, and seeds demo data on first run.
 * Everything is idempotent, so XAMPP users never run SQL by hand.
 */
require_once __DIR__ . '/../config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo !== null) return $pdo;

    $opts = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    // Create the database itself if missing (works with XAMPP root user).
    try {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    } catch (PDOException $e) {
        $server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
        $server->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, $opts);
    }
    migrate($pdo);
    return $pdo;
}

function migrate(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL UNIQUE,
        password_hash VARCHAR(255) NOT NULL,
        role ENUM('customer','admin') NOT NULL DEFAULT 'customer',
        avatar_url VARCHAR(255) NOT NULL DEFAULT '',
        phone VARCHAR(40) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL DEFAULT '',
        city VARCHAR(120) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS collections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(120) NOT NULL UNIQUE,
        name VARCHAR(120) NOT NULL,
        description VARCHAR(255) NOT NULL DEFAULT ''
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        slug VARCHAR(150) NOT NULL UNIQUE,
        name VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        category VARCHAR(120) NOT NULL,
        collection VARCHAR(120) NOT NULL,
        price_cents INT NOT NULL,
        discount_percent INT NOT NULL DEFAULT 0,
        image_url VARCHAR(255) NOT NULL DEFAULT '',
        in_stock TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS builder_options (
        id INT AUTO_INCREMENT PRIMARY KEY,
        step ENUM('size','focal','foliage','packaging') NOT NULL,
        name VARCHAR(120) NOT NULL,
        detail VARCHAR(190) NOT NULL DEFAULT '',
        price_cents INT NOT NULL DEFAULT 0
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference VARCHAR(20) NOT NULL UNIQUE,
        user_id INT NULL,
        customer_name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        phone VARCHAR(40) NOT NULL DEFAULT '',
        address VARCHAR(255) NOT NULL,
        delivery_date DATE NOT NULL,
        delivery_window VARCHAR(40) NOT NULL,
        order_type VARCHAR(20) NOT NULL DEFAULT 'on_demand',
        gift_note TEXT NULL,
        total_cents INT NOT NULL,
        payment_ref VARCHAR(120) NOT NULL DEFAULT '',
        status ENUM('pending_payment','paid','delivered') NOT NULL DEFAULT 'paid',
        reminded_1d TINYINT(1) NOT NULL DEFAULT 0,
        reminded_5h TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        name VARCHAR(255) NOT NULL,
        custom_config TEXT NULL,
        quantity INT NOT NULL DEFAULT 1,
        unit_price_cents INT NOT NULL,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        status VARCHAR(30) NOT NULL,
        note VARCHAR(255) NOT NULL DEFAULT '',
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(190) NOT NULL,
        body VARCHAR(500) NOT NULL DEFAULT '',
        link VARCHAR(255) NOT NULL DEFAULT '',
        is_read TINYINT(1) NOT NULL DEFAULT 0,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        product_id INT NOT NULL,
        rating TINYINT NOT NULL,
        comment TEXT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_product (user_id, product_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        email VARCHAR(190) NOT NULL,
        subject VARCHAR(190) NOT NULL DEFAULT '',
        body TEXT NOT NULL,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");

    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        k VARCHAR(60) PRIMARY KEY,
        v VARCHAR(255) NOT NULL
    ) ENGINE=InnoDB");

    seed($pdo);
}

function seed(PDO $pdo): void {
    // Admin account
    $has = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $has->execute([ADMIN_EMAIL]);
    if (!$has->fetch()) {
        $pdo->prepare("INSERT INTO users (name, email, password_hash, role) VALUES ('Atelier Admin', ?, ?, 'admin')")
            ->execute([ADMIN_EMAIL, password_hash(ADMIN_PASSWORD, PASSWORD_BCRYPT, ['cost' => 12])]);
    }

    if ((int)$pdo->query('SELECT COUNT(*) c FROM categories')->fetch()['c'] === 0) {
        $pdo->exec("INSERT INTO categories (slug, name) VALUES
            ('bouquet','Bouquets'),('arrangement','Arrangements'),('stems','Premium Stems')");
        $pdo->exec("INSERT INTO collections (slug, name, description) VALUES
            ('signature','Signature','Our house icons, perfected over seasons.'),
            ('spring','Spring','Peonies at their fleeting, ruffled best.'),
            ('summer','Summer','Sun-drenched tones for golden evenings.')");
    }

    if ((int)$pdo->query('SELECT COUNT(*) c FROM products')->fetch()['c'] === 0) {
        $products = [
            ['blush-peony-dream','Blush Peony Dream','A cloud of hand-selected blush peonies at peak bloom, wrapped in silk-touch ivory paper.','bouquet','signature',12900,0],
            ['coral-charm-cascade','Coral Charm Cascade','Coral Charm peonies fading from vivid coral to soft apricot, with trailing eucalyptus.','bouquet','signature',14500,0],
            ['ivory-noir','Ivory Noir','Pure white peonies against deep burgundy foliage — dramatic, architectural, unforgettable.','arrangement','signature',18900,0],
            ['garden-whisper','Garden Whisper','A loose, garden-gathered arrangement of peonies, garden roses and sweet pea.','arrangement','spring',9800,0],
            ['sarah-bernhardt-stems','Sarah Bernhardt Stems','Ten stems of the queen of peonies — ruffled, fragrant, rose-pink. Sold by the bunch.','stems','spring',6500,0],
            ['duchesse-blanche-stems','Duchesse Blanche Stems','Ten stems of pristine white Duchesse de Nemours, lightly lemon-scented.','stems','spring',7200,0],
            ['golden-hour','Golden Hour','Amber garden roses, butterscotch ranunculus and golden solidago in a low ceramic vessel.','arrangement','summer',11200,15],
            ['provence-morning','Provence Morning','Lavender, white peonies and olive branches — the south of France in a bouquet.','bouquet','summer',8900,0],
            ['velvet-crush','Velvet Crush','Deep magenta peonies with plum dahlias and smoke bush, tied with velvet ribbon.','bouquet','summer',13400,20],
            ['petite-poeme','Petite Poème','A small but perfectly formed posy of peonies and lisianthus. Ideal for desks and thank-yous.','bouquet','spring',5400,10],
        ];
        $ins = $pdo->prepare("INSERT INTO products (slug,name,description,category,collection,price_cents,discount_percent,image_url)
                              VALUES (?,?,?,?,?,?,?,?)");
        foreach ($products as $p) {
            $ins->execute([$p[0],$p[1],$p[2],$p[3],$p[4],$p[5],$p[6],'assets/images/'.$p[0].'.jpg']);
        }

        $pdo->exec("INSERT INTO builder_options (step,name,detail,price_cents) VALUES
            ('size','Petite','8 stems',4500),('size','Classic','14 stems',7500),
            ('size','Grand','22 stems',11500),('size','Opulent','34 stems',16900),
            ('focal','Blush Peony','Soft pink, ruffled',0),('focal','Coral Charm Peony','Vivid coral fading to apricot',800),
            ('focal','White Peony','Pure ivory Duchesse',600),('focal','Garden Rose','Fragrant, old-world',400),
            ('foliage','Silver Eucalyptus','Cool grey-green',0),('foliage','Olive Branch','Mediterranean, wild',500),
            ('foliage','Ruscus & Fern','Deep green, structured',300),('foliage','None','Blooms only',0),
            ('packaging','Ivory Silk Wrap','Signature hand-tied wrap',0),('packaging','Black Boutique Box','Rigid keepsake box',1500),
            ('packaging','Ceramic Vessel','Reusable artisan vase',3500)");
    }
}

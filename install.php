<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $server = db(false);
        $server->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $conn = db(true);

        $conn->query(
            'CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                sku VARCHAR(60) NULL,
                name VARCHAR(180) NOT NULL,
                unit VARCHAR(30) NOT NULL DEFAULT "un",
                stock_qty DECIMAL(10,2) NOT NULL DEFAULT 0,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                seller_name VARCHAR(120) NOT NULL,
                notes TEXT NULL,
                priority VARCHAR(20) NOT NULL DEFAULT "normal",
                internal_note TEXT NULL,
                status VARCHAR(30) NOT NULL DEFAULT "novo",
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS order_items (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                product_id INT NOT NULL,
                quantity DECIMAL(10,2) NOT NULL,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
                FOREIGN KEY (product_id) REFERENCES products(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS sellers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(120) NOT NULL,
                active TINYINT(1) NOT NULL DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $conn->query(
            'CREATE TABLE IF NOT EXISTS order_events (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                event_type VARCHAR(40) NOT NULL,
                description VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $count = (int) $conn->query('SELECT COUNT(*) AS total FROM products')->fetch_assoc()['total'];
        if ($count === 0) {
            $products = [
                ['REF001', 'Produto exemplo 1', 'un', 20],
                ['REF002', 'Produto exemplo 2', 'cx', 8],
                ['REF003', 'Produto exemplo 3', 'pct', 15],
            ];
            $stmt = $conn->prepare('INSERT INTO products (sku, name, unit, stock_qty) VALUES (?, ?, ?, ?)');
            foreach ($products as $product) {
                [$sku, $name, $unit, $qty] = $product;
                $stmt->bind_param('sssd', $sku, $name, $unit, $qty);
                $stmt->execute();
            }
        }

        $count = (int) $conn->query('SELECT COUNT(*) AS total FROM sellers')->fetch_assoc()['total'];
        if ($count === 0) {
            $sellers = ['Vendedora 1', 'Vendedora 2'];
            $stmt = $conn->prepare('INSERT INTO sellers (name) VALUES (?)');
            foreach ($sellers as $seller) {
                $stmt->bind_param('s', $seller);
                $stmt->execute();
            }
        }

        $message = 'Sistema instalado com sucesso.';
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

app_header('Instalacao');
?>
<section class="panel narrow">
    <h1>Instalar sistema</h1>
    <p class="muted">Use esta tela uma vez depois de colocar a pasta no XAMPP. Ela cria o banco, as tabelas e produtos de exemplo.</p>

    <?php if ($message): ?>
        <div class="alert success"><?= h($message) ?> <a href="login.php">Abrir login</a></div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="alert danger">Nao foi possivel instalar: <?= h($error) ?></div>
    <?php endif; ?>

    <form method="post">
        <button class="btn primary" type="submit">Instalar agora</button>
    </form>
</section>
<?php app_footer(); ?>

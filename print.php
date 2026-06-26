<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$conn = db();
$id = (int) ($_GET['id'] ?? 0);
$order = fetch_order($conn, $id);

if (!$order) {
    http_response_code(404);
    exit('Pedido nao encontrado');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pedido #<?= (int) $order['id'] ?></title>
    <link rel="stylesheet" href="assets/print.css">
</head>
<body>
    <main class="receipt">
        <header>
            <strong><?= h(STORE_NAME) ?></strong>
            <span>Pedido #<?= (int) $order['id'] ?></span>
            <span><?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></span>
        </header>

        <section>
            <p><strong>Vendedora:</strong> <?= h($order['seller_name']) ?></p>
            <p><strong>Status:</strong> <?= h(status_label($order['status'])) ?></p>
            <?php if ($order['notes']): ?>
                <p><strong>Obs:</strong> <?= h($order['notes']) ?></p>
            <?php endif; ?>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Qtd</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td>
                            <?= h($item['name']) ?>
                            <?php if ($item['sku']): ?><small><?= h($item['sku']) ?></small><?php endif; ?>
                        </td>
                        <td><?= h((string) $item['quantity']) ?> <?= h($item['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <footer>
            <span>Conferido por: __________________</span>
        </footer>
    </main>
    <div class="screen-actions">
        <button onclick="window.print()">Imprimir</button>
        <form method="post" action="confirmar_impressao.php">
            <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
            <button type="submit">Confirmar impressao</button>
        </form>
        <a href="pedido.php?id=<?= (int) $order['id'] ?>">Voltar</a>
    </div>
    <script>
        window.addEventListener('load', () => setTimeout(() => window.print(), 300));
    </script>
</body>
</html>

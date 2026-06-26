<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['vendedora']);

try {
    $conn = db();
    $products = fetch_popular_products($conn, 20);
    $myOrders = fetch_orders_by_seller($conn, current_seller_name());
    $missingNotices = [];
    if ($myOrders) {
        $ids = array_map(static fn (array $order): int => (int) $order['id'], $myOrders);
        $idList = implode(',', $ids);
        $result = $conn->query(
            'SELECT oe.order_id, oe.description
             FROM order_events oe
             JOIN (
                SELECT order_id, MAX(id) AS latest_id
                FROM order_events
                WHERE event_type = "missing" AND order_id IN (' . $idList . ')
                GROUP BY order_id
             ) latest ON latest.latest_id = oe.id'
        );

        while ($row = $result->fetch_assoc()) {
            $missingNotices[(int) $row['order_id']] = $row['description'];
        }
    }
} catch (Throwable) {
    redirect('install.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $seller = current_seller_name();
    $notes = trim($_POST['notes'] ?? '');
    $priority = $_POST['priority'] === 'urgente' ? 'urgente' : 'normal';
    $quantities = $_POST['quantity'] ?? [];

    $items = [];
    foreach ($quantities as $productId => $qty) {
        $quantity = (float) str_replace(',', '.', (string) $qty);
        if ($quantity > 0) {
            $items[(int) $productId] = $quantity;
        }
    }

    if ($seller === '') {
        $error = 'Informe o nome da vendedora.';
    } elseif (!$items) {
        $error = 'Informe a quantidade de pelo menos um produto.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare('INSERT INTO orders (seller_name, notes, priority) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $seller, $notes, $priority);
            $stmt->execute();
            $orderId = $conn->insert_id;

            $stmt = $conn->prepare('INSERT INTO order_items (order_id, product_id, quantity) VALUES (?, ?, ?)');
            foreach ($items as $productId => $quantity) {
                $stmt->bind_param('iid', $orderId, $productId, $quantity);
                $stmt->execute();
            }

            add_order_event($conn, $orderId, 'created', 'Pedido enviado pela vendedora ' . $seller);
            $conn->commit();
            $printResult = direct_print_order($conn, $orderId, 'Impressao imediata confirmada ao enviar pedido');
            redirect('index.php?created=' . $orderId . '&print=' . ($printResult['ok'] ? 'ok' : 'fail'));
        } catch (Throwable $e) {
            $conn->rollback();
            $error = 'Nao foi possivel salvar o pedido.';
        }
    }
}

seller_header('Novo pedido');
?>
<?php if (isset($_GET['created'])): ?>
    <div class="alert success">Pedido #<?= (int) $_GET['created'] ?> enviado para o estoque.</div>
<?php endif; ?>

<?php if (($_GET['print'] ?? '') === 'fail'): ?>
    <div class="alert danger">Pedido enviado, mas a impressao imediata falhou. Avise o estoque para reimprimir.</div>
<?php endif; ?>

<?php if (isset($_GET['created'])): ?>
    <div class="modal-backdrop" data-order-created>
        <div class="modal">
            <h2>Pedido Enviado</h2>
            <p>Pedido #<?= (int) $_GET['created'] ?> enviado para o estoque.</p>
            <button class="btn primary" type="button" data-close-modal>OK</button>
        </div>
    </div>
<?php endif; ?>

<section class="hero-form">
    <div>
        <h1>Novo pedido</h1>
        <p class="muted">Selecione os produtos que precisam sair do estoque.</p>
    </div>
</section>

<?php if ($error): ?>
    <div class="alert danger"><?= h($error) ?></div>
<?php endif; ?>

<form method="post" class="order-form">
    <section class="panel">
        <div class="form-grid">
            <label>
                <span>Vendedora</span>
                <input type="text" value="<?= h(current_seller_name()) ?>" disabled>
            </label>
            <label>
                <span>Observacao</span>
                <input name="notes" type="text" placeholder="Opcional">
            </label>
            <label>
                <span>Prioridade</span>
                <select name="priority">
                    <option value="normal">Normal</option>
                    <option value="urgente">Urgente</option>
                </select>
            </label>
        </div>
    </section>

    <section class="panel">
        <div class="section-title">
            <div>
                <h2>Produtos</h2>
                <p class="muted">Mostrando os 20 mais pedidos. Use a busca para encontrar outros produtos.</p>
            </div>
            <input class="search" data-filter-products type="search" placeholder="Buscar por codigo ou produto">
        </div>

        <div class="product-list" data-product-list>
            <?php foreach ($products as $product): ?>
                <div
                    class="product-row"
                    data-product-row
                    data-product-id="<?= (int) $product['id'] ?>"
                    data-product-name="<?= h($product['name']) ?>"
                    data-product-sku="<?= h($product['sku'] ?? '') ?>"
                    data-product-unit="<?= h($product['unit']) ?>"
                >
                    <div>
                        <strong><?= h($product['name']) ?></strong>
                        <span><?= h($product['sku'] ?: 'Sem codigo') ?> Â· Unidade: <?= h($product['unit']) ?></span>
                    </div>
                    <input
                        name="quantity[<?= (int) $product['id'] ?>]"
                        type="number"
                        min="0"
                        step="0.01"
                        inputmode="decimal"
                        placeholder="Qtd"
                    >
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <div class="sticky-actions">
        <button class="btn primary" type="submit">Enviar pedido</button>
    </div>
</form>

<section class="panel">
    <div class="section-title">
        <h2>Meus pedidos</h2>
        <div class="actions">
            <button class="btn small secondary" type="button" data-install-app hidden>Instalar app</button>
            <span class="muted">Atualiza automaticamente</span>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Impressao</th>
                    <th>Aviso</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-seller-orders data-seller-name="<?= h(current_seller_name()) ?>">
                <?php foreach ($myOrders as $order): ?>
                    <tr>
                        <td>#<?= (int) $order['id'] ?></td>
                        <td><span class="badge <?= h(status_class($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></td>
                        <td><span class="badge <?= h(priority_class($order['priority'] ?? 'normal')) ?>"><?= h(priority_label($order['priority'] ?? 'normal')) ?></span></td>
                        <td><?= $order['printed_at'] ? 'Impresso' : 'Pendente' ?></td>
                        <td>
                            <?php if (!empty($missingNotices[(int) $order['id']])): ?>
                                <span class="missing-notice"><?= h($missingNotices[(int) $order['id']]) ?></span>
                            <?php else: ?>
                                <span class="muted">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                        <td class="actions">
                            <?php if (can_seller_cancel($order)): ?>
                                <form method="post" action="cancelar_pedido.php" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                    <button class="btn small secondary" type="submit">Cancelar</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$myOrders): ?>
                    <tr><td colspan="7" class="empty">Nenhum pedido enviado ainda.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>

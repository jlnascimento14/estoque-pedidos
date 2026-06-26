<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$conn = db();
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$fromDate = $from . ' 00:00:00';
$toDate = $to . ' 23:59:59';

$stmt = $conn->prepare(
    'SELECT
        seller_name,
        COUNT(*) AS total,
        SUM(priority = "urgente") AS urgentes,
        SUM(status IN ("novo", "separando")) AS pendentes,
        SUM(status = "concluido") AS concluidos,
        SUM(status = "cancelado") AS cancelados,
        MAX(created_at) AS ultimo_pedido
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY seller_name
     ORDER BY total DESC, seller_name'
);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$bySeller = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare(
    'SELECT p.name, p.sku, SUM(oi.quantity) AS total_quantity, COUNT(DISTINCT oi.order_id) AS orders_count
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     JOIN orders o ON o.id = oi.order_id
     WHERE o.created_at BETWEEN ? AND ?
     GROUP BY p.id, p.name, p.sku
     ORDER BY total_quantity DESC, p.name
     LIMIT 50'
);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$byProduct = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt = $conn->prepare(
    'SELECT status, COUNT(*) AS total
     FROM orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status'
);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$byStatus = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

app_header('Relatorios');
?>
<section class="dashboard-head">
    <div>
        <h1>Relatorios</h1>
        <p class="muted">Pedidos por periodo, vendedora e produto.</p>
    </div>
    <a class="btn primary" href="exportar_relatorio.php?from=<?= h($from) ?>&to=<?= h($to) ?>">Exportar Excel</a>
</section>

<section class="panel">
    <form method="get" class="report-form">
        <label>
            <span>De</span>
            <input type="date" name="from" value="<?= h($from) ?>">
        </label>
        <label>
            <span>Ate</span>
            <input type="date" name="to" value="<?= h($to) ?>">
        </label>
        <button class="btn secondary" type="submit">Filtrar</button>
    </form>
</section>

<section class="panel">
    <h2>Historico por vendedora</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Vendedora</th>
                    <th>Total</th>
                    <th>Urgentes</th>
                    <th>Pendentes</th>
                    <th>Concluidos</th>
                    <th>Cancelados</th>
                    <th>Ultimo pedido</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bySeller as $row): ?>
                    <tr>
                        <td><?= h($row['seller_name']) ?></td>
                        <td><?= (int) $row['total'] ?></td>
                        <td><?= (int) $row['urgentes'] ?></td>
                        <td><?= (int) $row['pendentes'] ?></td>
                        <td><?= (int) $row['concluidos'] ?></td>
                        <td><?= (int) $row['cancelados'] ?></td>
                        <td><?= h(date('d/m/Y H:i', strtotime($row['ultimo_pedido']))) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$bySeller): ?><tr><td colspan="7" class="empty">Sem dados no periodo.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Produtos mais pedidos</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Produto</th><th>Codigo</th><th>Quantidade</th><th>Pedidos</th></tr></thead>
            <tbody>
                <?php foreach ($byProduct as $row): ?>
                    <tr>
                        <td><?= h($row['name']) ?></td>
                        <td><?= h($row['sku']) ?></td>
                        <td><?= h((string) $row['total_quantity']) ?></td>
                        <td><?= (int) $row['orders_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$byProduct): ?><tr><td colspan="4" class="empty">Sem dados no periodo.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Por status</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Status</th><th>Total</th></tr></thead>
            <tbody>
                <?php foreach ($byStatus as $row): ?>
                    <tr><td><?= h(status_label($row['status'])) ?></td><td><?= (int) $row['total'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$byStatus): ?><tr><td colspan="2" class="empty">Sem dados no periodo.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>

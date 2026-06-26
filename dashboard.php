<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

try {
    $conn = db();
} catch (Throwable) {
    redirect('install.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    $status = $_POST['status'] ?? 'novo';
    if ($id > 0 && in_array($status, ['novo', 'separando', 'concluido', 'cancelado'], true)) {
        update_order_status($conn, $id, $status);
    }
    redirect('dashboard.php');
}

$tab = $_GET['tab'] ?? 'abertos';
$status = $_GET['status'] ?? '';
$openStatuses = ['novo', 'separando'];

if ($tab === 'concluidos') {
    $stmt = $conn->prepare(
        'SELECT o.*, COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.status = "concluido"
         GROUP BY o.id
         ORDER BY FIELD(o.priority, "urgente", "normal"), o.created_at DESC'
    );
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif ($tab === 'cancelados') {
    $stmt = $conn->prepare(
        'SELECT o.*, COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.status = "cancelado"
         GROUP BY o.id
         ORDER BY o.created_at DESC'
    );
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} elseif (in_array($status, $openStatuses, true)) {
    $stmt = $conn->prepare(
        'SELECT o.*, COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.status = ?
         GROUP BY o.id
         ORDER BY FIELD(o.priority, "urgente", "normal"), o.created_at DESC'
    );
    $stmt->bind_param('s', $status);
    $stmt->execute();
    $orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
} else {
    $orders = $conn->query(
        'SELECT o.*, COUNT(oi.id) AS item_count
         FROM orders o
         LEFT JOIN order_items oi ON oi.order_id = o.id
         WHERE o.status IN ("novo", "separando")
         GROUP BY o.id
         ORDER BY FIELD(o.priority, "urgente", "normal"), FIELD(o.status, "novo", "separando"), o.created_at DESC'
    )->fetch_all(MYSQLI_ASSOC);
}

$counts = [];
$result = $conn->query('SELECT status, COUNT(*) AS total FROM orders GROUP BY status');
while ($row = $result->fetch_assoc()) {
    $counts[$row['status']] = (int) $row['total'];
}

$today = date('Y-m-d');
$todayCounts = [
    'total' => 0,
    'novo' => 0,
    'separando' => 0,
    'concluido' => 0,
    'cancelado' => 0,
    'urgente' => 0,
];
$stmt = $conn->prepare(
    'SELECT
        COUNT(*) AS total,
        SUM(status = "novo") AS novo,
        SUM(status = "separando") AS separando,
        SUM(status = "concluido") AS concluido,
        SUM(status = "cancelado") AS cancelado,
        SUM(priority = "urgente" AND status IN ("novo", "separando")) AS urgente
     FROM orders
     WHERE DATE(created_at) = ?'
);
$stmt->bind_param('s', $today);
$stmt->execute();
$todayRow = $stmt->get_result()->fetch_assoc();
foreach ($todayCounts as $key => $value) {
    $todayCounts[$key] = (int) ($todayRow[$key] ?? 0);
}

$orders = attach_print_flags($conn, $orders);
$urgentOpenCount = (int) $conn
    ->query('SELECT COUNT(*) AS total FROM orders WHERE status IN ("novo", "separando") AND priority = "urgente"')
    ->fetch_assoc()['total'];

app_header('Dashboard');
?>
<?php if ($urgentOpenCount > 0): ?>
    <script>document.body.classList.add('has-urgent-orders');</script>
<?php endif; ?>

<section class="dashboard-head">
    <div>
        <h1>Dashboard do estoque</h1>
        <p class="muted">Pedidos recebidos das vendedoras.</p>
    </div>
    <div class="actions">
        <button class="btn secondary" type="button" data-enable-auto-print>Ativar som de aviso</button>
    </div>
</section>

<div class="auto-print-status" data-auto-print-status>
    Impressao direta ativa em <?= h(active_printer_label()) ?>. O som do navegador fica ativo depois de clicar no botao.
</div>

<?php if ($urgentOpenCount > 0): ?>
    <div class="urgent-alert">
        <?= $urgentOpenCount ?> pedido(s) urgente(s) em aberto
    </div>
<?php endif; ?>

<section class="today-summary">
    <div class="summary-card">
        <span>Hoje</span>
        <strong><?= $todayCounts['total'] ?></strong>
    </div>
    <div class="summary-card urgent">
        <span>Urgentes abertos</span>
        <strong><?= $todayCounts['urgente'] ?></strong>
    </div>
    <div class="summary-card">
        <span>Pendentes</span>
        <strong><?= $todayCounts['novo'] + $todayCounts['separando'] ?></strong>
    </div>
    <div class="summary-card success">
        <span>Concluidos</span>
        <strong><?= $todayCounts['concluido'] ?></strong>
    </div>
    <div class="summary-card muted-card">
        <span>Cancelados</span>
        <strong><?= $todayCounts['cancelado'] ?></strong>
    </div>
</section>

<section class="stats">
    <a class="stat" href="dashboard.php?status=novo"><span>Novo</span><strong><?= $counts['novo'] ?? 0 ?></strong></a>
    <a class="stat" href="dashboard.php?status=separando"><span>Separando</span><strong><?= $counts['separando'] ?? 0 ?></strong></a>
    <a class="stat" href="dashboard.php?tab=concluidos"><span>Concluido</span><strong><?= $counts['concluido'] ?? 0 ?></strong></a>
    <a class="stat" href="dashboard.php"><span>Em aberto</span><strong><?= ($counts['novo'] ?? 0) + ($counts['separando'] ?? 0) ?></strong></a>
</section>

<nav class="tabs">
    <a class="<?= !in_array($tab, ['concluidos', 'cancelados'], true) ? 'active' : '' ?>" href="dashboard.php">Em aberto</a>
    <a class="<?= $tab === 'concluidos' ? 'active' : '' ?>" href="dashboard.php?tab=concluidos">Concluidos</a>
    <a class="<?= $tab === 'cancelados' ? 'active' : '' ?>" href="dashboard.php?tab=cancelados">Cancelados</a>
</nav>

<section class="panel">
    <div class="section-title">
        <h2><?= $tab === 'concluidos' ? 'Pedidos concluidos' : ($tab === 'cancelados' ? 'Pedidos cancelados' : (in_array($status, $openStatuses, true) ? status_label($status) : 'Pedidos em aberto')) ?></h2>
        <a class="link" href="dashboard.php">Ver em aberto</a>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Vendedora</th>
                    <th>Itens</th>
                    <th>Prioridade</th>
                    <th>Status</th>
                    <th>Impressao</th>
                    <th>Data</th>
                    <th></th>
                </tr>
            </thead>
            <tbody data-auto-refresh>
                <?php foreach ($orders as $order): ?>
                    <tr class="<?= ($order['priority'] ?? 'normal') === 'urgente' ? 'row-urgent' : '' ?>">
                        <td><a class="link" href="pedido.php?id=<?= (int) $order['id'] ?>">#<?= (int) $order['id'] ?></a></td>
                        <td><?= h($order['seller_name']) ?></td>
                        <td><?= (int) $order['item_count'] ?></td>
                        <td><span class="badge <?= h(priority_class($order['priority'] ?? 'normal')) ?>"><?= h(priority_label($order['priority'] ?? 'normal')) ?></span></td>
                        <td><span class="badge <?= h(status_class($order['status'])) ?>"><?= h(status_label($order['status'])) ?></span></td>
                        <td>
                            <?php if ($order['printed_at']): ?>
                                <span class="badge status-done">Impresso</span>
                            <?php else: ?>
                                <span class="badge status-new">Pendente</span>
                            <?php endif; ?>
                        </td>
                        <td><?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></td>
                        <td class="actions">
                            <?php if (!in_array($tab, ['concluidos', 'cancelados'], true)): ?>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="status" value="separando">
                                    <button class="btn small progress" type="submit">Conferindo</button>
                                </form>
                                <form method="post" class="inline-form">
                                    <input type="hidden" name="id" value="<?= (int) $order['id'] ?>">
                                    <input type="hidden" name="status" value="concluido">
                                    <button class="btn small success" type="submit">Concluido</button>
                                </form>
                            <?php endif; ?>
                            <a class="btn small secondary" href="print.php?id=<?= (int) $order['id'] ?>" target="_blank"><?= $tab === 'concluidos' ? 'Reimprimir' : 'Imprimir' ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$orders): ?>
                    <tr><td colspan="8" class="empty">Nenhum pedido encontrado.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>

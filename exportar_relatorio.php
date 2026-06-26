<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$conn = db();
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$fromDate = $from . ' 00:00:00';
$toDate = $to . ' 23:59:59';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="relatorio-pedidos-' . $from . '-a-' . $to . '.csv"');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, ['Pedido', 'Data', 'Vendedora', 'Prioridade', 'Status', 'Produto', 'Codigo', 'Quantidade'], ';');

$stmt = $conn->prepare(
    'SELECT o.id, o.created_at, o.seller_name, o.priority, o.status, p.name, p.sku, oi.quantity
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     JOIN products p ON p.id = oi.product_id
     WHERE o.created_at BETWEEN ? AND ?
     ORDER BY o.created_at DESC, o.id DESC, p.name'
);
$stmt->bind_param('ss', $fromDate, $toDate);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    fputcsv($out, [
        $row['id'],
        date('d/m/Y H:i', strtotime($row['created_at'])),
        $row['seller_name'],
        priority_label($row['priority']),
        status_label($row['status']),
        $row['name'],
        $row['sku'],
        $row['quantity'],
    ], ';');
}

fclose($out);


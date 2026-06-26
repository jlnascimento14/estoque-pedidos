<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

require_role(['estoque', 'admin']);

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = db();
    $latest = $conn->query(
        'SELECT
            MAX(updated_at) AS updated_at,
            COUNT(*) AS total,
            COALESCE(MAX(id), 0) AS latest_order_id
         FROM orders'
    )->fetch_assoc();

    $hasAfter = isset($_GET['after']);
    $after = (int) ($_GET['after'] ?? 0);
    $newOrders = [];

    if ($hasAfter) {
        $stmt = $conn->prepare(
            'SELECT id
             FROM orders
             WHERE id > ?
               AND status = "novo"
               AND NOT EXISTS (
                   SELECT 1
                   FROM order_events oe
                   WHERE oe.order_id = orders.id
                     AND oe.event_type = "print"
               )
             ORDER BY id ASC'
        );
        $stmt->bind_param('i', $after);
        $stmt->execute();
        $newOrders = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id'));
    }

    $latest['latest_order_id'] = (int) $latest['latest_order_id'];
    $latest['total'] = (int) $latest['total'];
    $latest['new_order_ids'] = $newOrders;

    echo json_encode($latest, JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'database']);
}

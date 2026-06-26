<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

require_role(['vendedora']);

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = db();
    $orders = fetch_orders_by_seller($conn, current_seller_name());
    $statusEvents = [];
    $missingEvents = [];

    if ($orders) {
        $ids = array_map(static fn (array $order): int => (int) $order['id'], $orders);
        $idList = implode(',', $ids);
        $result = $conn->query(
            'SELECT order_id, MAX(id) AS status_event_id
             FROM order_events
             WHERE event_type = "status" AND order_id IN (' . $idList . ')
             GROUP BY order_id'
        );

        while ($row = $result->fetch_assoc()) {
            $statusEvents[(int) $row['order_id']] = (int) $row['status_event_id'];
        }

        $result = $conn->query(
            'SELECT oe.order_id, oe.id, oe.description
             FROM order_events oe
             JOIN (
                SELECT order_id, MAX(id) AS latest_id
                FROM order_events
                WHERE event_type = "missing" AND order_id IN (' . $idList . ')
                GROUP BY order_id
             ) latest ON latest.latest_id = oe.id'
        );

        while ($row = $result->fetch_assoc()) {
            $missingEvents[(int) $row['order_id']] = [
                'id' => (int) $row['id'],
                'description' => $row['description'],
            ];
        }
    }

    $payload = array_map(static function (array $order) use ($statusEvents, $missingEvents): array {
        $id = (int) $order['id'];
        $missing = $missingEvents[$id] ?? null;
        return [
            'id' => $id,
            'status' => $order['status'],
            'status_label' => status_label($order['status']),
            'status_class' => status_class($order['status']),
            'priority' => $order['priority'] ?? 'normal',
            'priority_label' => priority_label($order['priority'] ?? 'normal'),
            'priority_class' => priority_class($order['priority'] ?? 'normal'),
            'printed' => (bool) $order['printed_at'],
            'created_at' => date('d/m/Y H:i', strtotime($order['created_at'])),
            'status_event_id' => $statusEvents[$id] ?? 0,
            'missing_notice' => $missing['description'] ?? '',
            'missing_event_id' => $missing['id'] ?? 0,
            'can_cancel' => can_seller_cancel($order),
        ];
    }, $orders);

    echo json_encode(['orders' => $payload], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'database']);
}

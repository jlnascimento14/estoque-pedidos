<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['vendedora']);

$conn = db();
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    $stmt = $conn->prepare('SELECT * FROM orders WHERE id = ? AND seller_name = ?');
    $seller = current_seller_name();
    $stmt->bind_param('is', $id, $seller);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if ($order && can_seller_cancel($order)) {
        update_order_status($conn, $id, 'cancelado');
    }
}

redirect('index.php');


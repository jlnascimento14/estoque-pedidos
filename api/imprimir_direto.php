<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

require_role(['estoque', 'admin']);

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = db();
    $id = (int) ($_GET['id'] ?? 0);

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'Pedido invalido.']);
        exit;
    }

    echo json_encode(direct_print_order($conn, $id, 'Impressao automatica confirmada'), JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
}

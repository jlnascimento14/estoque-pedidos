<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$conn = db();
$id = (int) ($_POST['id'] ?? 0);

if ($id > 0) {
    add_order_event($conn, $id, 'print', 'Impressao manual confirmada');
}

redirect('pedido.php?id=' . $id);


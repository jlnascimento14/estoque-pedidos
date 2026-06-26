<?php
declare(strict_types=1);

require_once __DIR__ . '/../lib.php';

require_role(['vendedora']);

header('Content-Type: application/json; charset=utf-8');

try {
    $conn = db();
    $query = trim($_GET['q'] ?? '');
    $products = search_products($conn, $query, 20);

    $payload = array_map(static fn (array $product): array => [
        'id' => (int) $product['id'],
        'sku' => $product['sku'] ?? '',
        'name' => $product['name'],
        'unit' => $product['unit'],
    ], $products);

    echo json_encode(['products' => $payload], JSON_THROW_ON_ERROR);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['error' => 'database']);
}

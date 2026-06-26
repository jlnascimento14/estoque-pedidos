<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['admin']);

$conn = db();
$error = '';
$success = '';

function import_csv_delimiter(string $line): string
{
    $delimiters = [';' => substr_count($line, ';'), ',' => substr_count($line, ','), "\t" => substr_count($line, "\t")];
    arsort($delimiters);
    $delimiter = (string) array_key_first($delimiters);
    return ($delimiters[$delimiter] ?? 0) > 0 ? $delimiter : ';';
}

function import_csv_value(?string $value): string
{
    $value = trim((string) $value);
    $value = preg_replace('/^\xEF\xBB\xBF/', '', $value) ?? $value;
    if (function_exists('mb_convert_encoding')) {
        $value = mb_convert_encoding($value, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
    }
    return trim($value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $sku = trim($_POST['sku'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $unit = trim($_POST['unit'] ?? 'un') ?: 'un';

        if ($name === '') {
            $error = 'Informe o nome do produto.';
        } else {
            $stmt = $conn->prepare('INSERT INTO products (sku, name, unit) VALUES (?, ?, ?)');
            $stmt->bind_param('sss', $sku, $name, $unit);
            $stmt->execute();
            redirect('produtos.php');
        }
    }

    if ($action === 'import_csv') {
        $file = $_FILES['products_csv'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Selecione um arquivo CSV para importar.';
        } else {
            $path = (string) $file['tmp_name'];
            $handle = fopen($path, 'rb');
            if (!$handle) {
                $error = 'Nao foi possivel abrir o CSV.';
            } else {
                $firstLine = fgets($handle);
                $delimiter = import_csv_delimiter((string) $firstLine);
                rewind($handle);

                $inserted = 0;
                $updated = 0;
                $skipped = 0;
                $findStmt = $conn->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
                $insertStmt = $conn->prepare('INSERT INTO products (sku, name, unit, active) VALUES (?, ?, "un", 1)');
                $updateStmt = $conn->prepare('UPDATE products SET name = ?, active = 1 WHERE id = ?');

                while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
                    $sku = import_csv_value($row[0] ?? '');
                    $name = import_csv_value($row[1] ?? '');
                    $firstColumn = strtolower($sku);
                    $secondColumn = strtolower($name);

                    if ($sku === '' || $name === '' || str_contains($firstColumn, 'codigo') || str_contains($secondColumn, 'produto')) {
                        $skipped++;
                        continue;
                    }

                    $findStmt->bind_param('s', $sku);
                    $findStmt->execute();
                    $existing = $findStmt->get_result()->fetch_assoc();

                    if ($existing) {
                        $productId = (int) $existing['id'];
                        $updateStmt->bind_param('si', $name, $productId);
                        $updateStmt->execute();
                        $updated++;
                    } else {
                        $insertStmt->bind_param('ss', $sku, $name);
                        $insertStmt->execute();
                        $inserted++;
                    }
                }

                fclose($handle);
                $success = 'Importacao concluida: ' . $inserted . ' novo(s), ' . $updated . ' atualizado(s), ' . $skipped . ' ignorado(s).';
            }
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0);
        $stmt = $conn->prepare('UPDATE products SET active = ? WHERE id = ?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        redirect('produtos.php');
    }
}

$products = fetch_products($conn, false);

app_header('Produtos');
?>
<section class="dashboard-head">
    <div>
        <h1>Produtos</h1>
        <p class="muted">Itens disponiveis para as vendedoras solicitarem.</p>
    </div>
    <a class="btn secondary" href="dashboard.php">Dashboard</a>
</section>

<?php if ($error): ?>
    <div class="alert danger"><?= h($error) ?></div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert success"><?= h($success) ?></div>
<?php endif; ?>

<section class="panel">
    <h2>Importar CSV</h2>
    <form method="post" enctype="multipart/form-data" class="import-form">
        <input type="hidden" name="action" value="import_csv">
        <label>
            <span>Arquivo CSV</span>
            <input name="products_csv" type="file" accept=".csv,text/csv" required>
        </label>
        <button class="btn primary" type="submit">Importar produtos</button>
    </form>
    <p class="muted">Coluna 1: codigo. Coluna 2: nome do produto.</p>
</section>

<section class="panel">
    <h2>Novo produto</h2>
    <form method="post" class="product-form">
        <input type="hidden" name="action" value="create">
        <label>
            <span>Codigo</span>
            <input name="sku" type="text">
        </label>
        <label>
            <span>Produto</span>
            <input name="name" type="text" required>
        </label>
        <label>
            <span>Unidade</span>
            <input name="unit" type="text" value="un" required>
        </label>
        <button class="btn primary" type="submit">Adicionar</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Codigo</th>
                    <th>Produto</th>
                    <th>Unidade</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><?= h($product['sku']) ?></td>
                        <td><?= h($product['name']) ?></td>
                        <td><?= h($product['unit']) ?></td>
                        <td><?= $product['active'] ? 'Ativo' : 'Inativo' ?></td>
                        <td class="actions">
                            <form method="post">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $product['id'] ?>">
                                <input type="hidden" name="active" value="<?= $product['active'] ? 0 : 1 ?>">
                                <button class="btn small secondary" type="submit"><?= $product['active'] ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>

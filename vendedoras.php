<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['admin']);

$conn = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $error = 'Informe o nome da vendedora.';
        } else {
            $stmt = $conn->prepare('INSERT INTO sellers (name) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            redirect('vendedoras.php');
        }
    }

    if ($action === 'toggle') {
        $id = (int) ($_POST['id'] ?? 0);
        $active = (int) ($_POST['active'] ?? 0);
        $stmt = $conn->prepare('UPDATE sellers SET active = ? WHERE id = ?');
        $stmt->bind_param('ii', $active, $id);
        $stmt->execute();
        redirect('vendedoras.php');
    }
}

$sellers = fetch_sellers($conn, false);

app_header('Vendedoras');
?>
<section class="dashboard-head">
    <div>
        <h1>Vendedoras</h1>
        <p class="muted">Nomes disponiveis para fazer pedidos.</p>
    </div>
    <a class="btn secondary" href="dashboard.php">Dashboard</a>
</section>

<?php if ($error): ?>
    <div class="alert danger"><?= h($error) ?></div>
<?php endif; ?>

<section class="panel">
    <h2>Nova vendedora</h2>
    <form method="post" class="seller-form">
        <input type="hidden" name="action" value="create">
        <label>
            <span>Nome</span>
            <input name="name" type="text" required>
        </label>
        <button class="btn primary" type="submit">Adicionar</button>
    </form>
</section>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Nome</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td><?= h($seller['name']) ?></td>
                        <td><?= $seller['active'] ? 'Ativa' : 'Inativa' ?></td>
                        <td class="actions">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $seller['id'] ?>">
                                <input type="hidden" name="active" value="<?= $seller['active'] ? 0 : 1 ?>">
                                <button class="btn small secondary" type="submit"><?= $seller['active'] ? 'Desativar' : 'Ativar' ?></button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php app_footer(); ?>


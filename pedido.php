<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$conn = db();
$id = (int) ($_GET['id'] ?? 0);
$order = fetch_order($conn, $id);

if (!$order) {
    http_response_code(404);
    app_header('Pedido nao encontrado');
    echo '<section class="panel"><h1>Pedido nao encontrado</h1><a class="btn secondary" href="dashboard.php">Voltar</a></section>';
    app_footer();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'status';
    if ($action === 'note') {
        update_internal_note($conn, $id, trim($_POST['internal_note'] ?? ''));
    } elseif ($action === 'missing') {
        $missingProductId = (int) ($_POST['missing_product_id'] ?? 0);
        $missingProductName = '';
        foreach ($order['items'] as $item) {
            if ((int) $item['product_id'] === $missingProductId) {
                $missingProductName = $item['name'];
                break;
            }
        }

        if ($missingProductName !== '') {
            $currentNote = trim((string) ($order['internal_note'] ?? ''));
            $missingNote = 'Faltou produto: ' . $missingProductName;
            $note = $currentNote === '' ? $missingNote : $currentNote . "\n" . $missingNote;
            update_internal_note($conn, $id, $note);
            add_order_event($conn, $id, 'missing', $missingNote);
        }
    } else {
        $status = $_POST['status'] ?? 'novo';
        if (in_array($status, ['novo', 'separando', 'concluido', 'cancelado'], true)) {
            update_order_status($conn, $id, $status);
        }
    }
    redirect('pedido.php?id=' . $id);
}

app_header('Pedido #' . $id);
?>
<?php if (isset($_GET['created'])): ?>
    <div class="alert success">Pedido enviado para o estoque.</div>
<?php endif; ?>

<section class="detail-head">
    <div>
        <h1>Pedido #<?= (int) $order['id'] ?></h1>
        <p class="muted"><?= h($order['seller_name']) ?> Â· <?= h(date('d/m/Y H:i', strtotime($order['created_at']))) ?></p>
        <p><span class="badge <?= h(priority_class($order['priority'] ?? 'normal')) ?>"><?= h(priority_label($order['priority'] ?? 'normal')) ?></span></p>
    </div>
    <div class="actions">
        <a class="btn secondary" href="dashboard.php">Dashboard</a>
        <a class="btn primary" href="print.php?id=<?= (int) $order['id'] ?>" target="_blank">Imprimir</a>
    </div>
</section>

<section class="panel">
    <div class="order-meta">
        <div>
            <span>Status atual</span>
            <strong class="badge <?= h(status_class($order['status'])) ?>"><?= h(status_label($order['status'])) ?></strong>
        </div>
        <div class="quick-status-actions">
            <form method="post">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="status" value="separando">
                <button class="btn progress big-action" type="submit">Conferindo</button>
            </form>
            <form method="post" class="missing-product-form">
                <input type="hidden" name="action" value="missing">
                <select name="missing_product_id" required>
                    <option value="">Produto que faltou</option>
                    <?php foreach ($order['items'] as $item): ?>
                        <option value="<?= (int) $item['product_id'] ?>"><?= h($item['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn warning big-action" type="submit">Faltou produto</button>
            </form>
            <form method="post">
                <input type="hidden" name="action" value="status">
                <input type="hidden" name="status" value="concluido">
                <button class="btn success big-action" type="submit">Concluido</button>
            </form>
        </div>
    </div>

    <?php if ($order['notes']): ?>
        <div class="note"><strong>Observacao:</strong> <?= h($order['notes']) ?></div>
    <?php endif; ?>

    <form method="post" class="internal-note-form">
        <input type="hidden" name="action" value="note">
        <label>
            <span>Observacao interna do estoque</span>
            <textarea name="internal_note" rows="3" placeholder="Visivel apenas para estoque/admin"><?= h($order['internal_note'] ?? '') ?></textarea>
        </label>
        <button class="btn secondary" type="submit">Salvar observacao</button>
    </form>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Produto</th>
                    <th>Codigo</th>
                    <th>Quantidade</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($order['items'] as $item): ?>
                    <tr>
                        <td><?= h($item['name']) ?></td>
                        <td><?= h($item['sku']) ?></td>
                        <td><?= h((string) $item['quantity']) ?> <?= h($item['unit']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Historico</h2>
    <div class="timeline">
        <?php foreach ($order['events'] as $event): ?>
            <div class="timeline-item">
                <strong><?= h(date('d/m/Y H:i', strtotime($event['created_at']))) ?></strong>
                <span><?= h($event['description']) ?></span>
            </div>
        <?php endforeach; ?>
        <?php if (!$order['events']): ?>
            <div class="empty">Sem historico registrado para este pedido.</div>
        <?php endif; ?>
    </div>
</section>
<?php app_footer(); ?>

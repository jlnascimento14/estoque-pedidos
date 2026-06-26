<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

require_role(['estoque', 'admin']);

$error = '';
$success = '';
$printers = available_windows_printers();
$activePrinter = active_printer_config();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $printerName = trim($_POST['printer_name'] ?? '');
    if (save_active_printer($printerName)) {
        redirect('dashboard.php?printer=ok');
    }

    $error = 'Nao foi possivel selecionar essa impressora.';
}

app_header('Selecionar impressora');
?>
<section class="panel narrow">
    <h1>Selecionar impressora</h1>
    <p class="muted">Escolha a impressora que vai receber todas as impressoes do sistema.</p>

    <?php if ($error): ?>
        <div class="alert danger"><?= h($error) ?></div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert success"><?= h($success) ?></div>
    <?php endif; ?>

    <form method="post" class="printer-form">
        <label class="printer-option">
            <input
                type="radio"
                name="printer_name"
                value="<?= h(PRINTER_PORT) ?>"
                <?= ($activePrinter['name'] ?? '') === PRINTER_PORT ? 'checked' : '' ?>
            >
            <span>
                <strong>Porta direta <?= h(PRINTER_PORT) ?></strong>
                <small>Usa a configuracao atual da Bematech/MP-4200.</small>
            </span>
        </label>

        <?php foreach ($printers as $printer): ?>
            <label class="printer-option">
                <input
                    type="radio"
                    name="printer_name"
                    value="<?= h($printer['name']) ?>"
                    <?= ($activePrinter['name'] ?? '') === $printer['name'] ? 'checked' : '' ?>
                >
                <span>
                    <strong><?= h($printer['name']) ?><?= $printer['is_default'] ? ' - Padrao do Windows' : '' ?></strong>
                    <small>Porta: <?= h($printer['port'] ?: 'Nao informada') ?></small>
                </span>
            </label>
        <?php endforeach; ?>

        <?php if (!$printers): ?>
            <div class="empty printer-empty">Nenhuma impressora do Windows encontrada. A porta <?= h(PRINTER_PORT) ?> continua disponivel.</div>
        <?php endif; ?>

        <button class="btn primary" type="submit">OK, usar esta impressora</button>
    </form>
</section>
<?php app_footer(); ?>

<?php
declare(strict_types=1);

require_once __DIR__ . '/lib.php';

start_app_session();

if (current_role()) {
    redirect(current_role() === 'vendedora' ? 'index.php' : 'selecionar_impressora.php');
}

$error = '';
$sellers = [];

try {
    $conn = db();
    $sellers = fetch_sellers($conn);
} catch (Throwable) {
    $sellers = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $profile = $_POST['profile'] ?? '';
    $password = $_POST['password'] ?? '';

    if (str_starts_with($profile, 'seller:') && hash_equals(SELLER_PASSWORD, $password)) {
        $sellerName = substr($profile, 7);
        $_SESSION['role'] = 'vendedora';
        $_SESSION['seller_name'] = $sellerName;
        redirect('index.php');
    }

    if ($profile === 'estoque' && hash_equals(STOCK_PASSWORD, $password)) {
        $_SESSION['role'] = 'estoque';
        redirect('selecionar_impressora.php');
    }

    if ($profile === 'admin' && hash_equals(ADMIN_PASSWORD, $password)) {
        $_SESSION['role'] = 'admin';
        redirect('selecionar_impressora.php');
    }

    $error = 'Senha ou perfil invalido.';
}

seller_header('Login');
?>
<section class="panel narrow">
    <h1>Entrar no sistema</h1>
    <p class="muted">Vendedora faz pedidos. Estoque e admin acessam o painel.</p>

    <?php if ($error): ?>
        <div class="alert danger"><?= h($error) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
        <label>
            <span>Perfil</span>
            <select name="profile" required>
                <?php foreach ($sellers as $seller): ?>
                    <option value="seller:<?= h($seller['name']) ?>"><?= h($seller['name']) ?></option>
                <?php endforeach; ?>
                <option value="estoque">Estoque</option>
                <option value="admin">Admin</option>
            </select>
        </label>
        <label>
            <span>Senha</span>
            <input name="password" type="password" required>
        </label>
        <button class="btn primary" type="submit">Entrar</button>
    </form>
</section>
<?php app_footer(); ?>

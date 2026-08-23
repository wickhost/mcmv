<?php
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim($_POST['usuario'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($usuario === '' || $senha === '') {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("
            SELECT *
            FROM usuarios
            WHERE usuario = ?
              AND tipo = 'admin'
            LIMIT 1
        ");

        $stmt->execute([$usuario]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($admin && password_verify($senha, $admin['senha'])) {
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = $admin['id'];
            $_SESSION['usuario_nome'] = $admin['nome'];
            $_SESSION['usuario_tipo'] = $admin['tipo'];

            header('Location: /dashboard');
            exit;
        }

        $erro = 'Usuário ou senha incorretos.';
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container d-flex flex-grow-1 align-items-center justify-content-center py-4 py-md-5">
    <div class="card card-login p-3 p-sm-4 w-100">

        <div class="text-center mb-4">
            <i class="bi bi-shield-lock-fill text-primary display-4"></i>

            <h4 class="fw-bold mt-2">
                Acesso Restrito
            </h4>

            <p class="text-muted small">
                Painel do Administrador
            </p>
        </div>

        <?php if ($erro !== ''): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="mb-3">
                <label class="form-label fw-bold">
                    Usuário
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-person"></i>
                    </span>

                    <input
                        type="text"
                        name="usuario"
                        class="form-control form-control-lg fs-6"
                        placeholder="admin"
                        value="<?= htmlspecialchars($_POST['usuario'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        autofocus
                        autocomplete="username"
                    >
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">
                    Senha
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-key"></i>
                    </span>

                    <input
                        type="password"
                        name="senha"
                        class="form-control form-control-lg fs-6"
                        placeholder="••••••"
                        required
                        autocomplete="current-password"
                    >
                </div>
            </div>

            <button
                type="submit"
                class="btn btn-primary w-100 fw-bold py-2 shadow-sm"
            >
                <i class="bi bi-box-arrow-in-right me-1"></i>
                Entrar no Painel
            </button>

        </form>

        <div class="text-center mt-4 pt-2 border-top">
            <a
                href="/login"
                class="text-decoration-none small text-muted"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Ir para Login de Clientes
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
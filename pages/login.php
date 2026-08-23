<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cpf   = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if (empty($cpf) || empty($senha)) {
        $erro = 'Por favor, preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? AND tipo = 'cliente' LIMIT 1");
        $stmt->execute([$cpf]);
        $cliente = $stmt->fetch();

        if ($cliente && password_verify($senha, $cliente['senha'])) {
            $_SESSION['usuario_id']   = $cliente['id'];
            $_SESSION['usuario_nome'] = $cliente['nome'];
            $_SESSION['usuario_tipo'] = $cliente['tipo'];

            header('Location: /portal');
            exit;
        } else {
            $erro = 'CPF ou senha incorretos.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container d-flex flex-grow-1 align-items-center justify-content-center py-4 py-md-5">
    <div class="card card-login p-3 p-sm-4 w-100">
        <div class="text-center mb-4">
            <i class="bi bi-house-heart-fill text-primary display-4"></i>
            <h4 class="fw-bold mt-2">Portal do Cliente</h4>
            <p class="text-muted small">Acompanhe a evolução da sua obra</p>
        </div>

        <?php if ($erro): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($erro) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold">CPF</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                    <input type="text" name="cpf" class="form-control form-control-lg fs-6 mask-cpf" placeholder="000.000.000-00" inputmode="numeric" required autofocus>
                </div>
            </div>

            <div class="mb-4">
                <label class="form-label fw-bold">Senha</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-key"></i></span>
                    <input type="password" name="senha" class="form-control form-control-lg fs-6" placeholder="••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary w-100 fw-bold py-2 shadow-sm">
                <i class="bi bi-box-arrow-in-right me-1"></i> Acessar Minha Obra
            </button>
        </form>

        <div class="text-center mt-4 pt-2 border-top">
            <a href="/hlogin" class="text-decoration-none small text-muted">
                <i class="bi bi-shield-lock me-1"></i> Área do Administrador
            </a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
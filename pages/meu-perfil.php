<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

// Garante que a sessão está ativa e chama a função correta do auth.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (function_exists('verificarLogin')) {
    verificarLogin();
} elseif (function_exists('verificarAdmin')) {
    verificarAdmin();
} else {
    if (!isset($_SESSION['usuario_id'])) {
        header('Location: /login');
        exit;
    }
}

$usuario_id = $_SESSION['usuario_id'];
$erro = '';
$sucesso = '';

// Buscar dados do usuário logado
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$usuario_id]);
$usuario = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senha_atual = $_POST['senha_atual'] ?? '';
    $nova_senha = $_POST['nova_senha'] ?? '';

    if (empty($nome)) {
        $erro = 'O campo nome é obrigatório.';
    } else {
        if (!empty($nova_senha)) {
            if (!password_verify($senha_atual, $usuario['senha'])) {
                $erro = 'A senha atual está incorreta.';
            } else {
                $hash = password_hash($nova_senha, PASSWORD_DEFAULT);
                $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ?, senha = ? WHERE id = ?");
                $stmtUp->execute([$nome, $email ?: null, $telefone, $hash, $usuario_id]);
                $sucesso = 'Perfil e senha atualizados com sucesso!';
            }
        } else {
            $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, email = ?, telefone = ? WHERE id = ?");
            $stmtUp->execute([$nome, $email ?: null, $telefone, $usuario_id]);
            $sucesso = 'Perfil atualizado com sucesso!';
        }

        $stmt->execute([$usuario_id]);
        $usuario = $stmt->fetch();
        $_SESSION['usuario_nome'] = $usuario['nome'];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4" style="max-width: 600px;">
    <h3 class="fw-bold mb-3"><i class="bi bi-person-circle me-2 text-primary"></i>Meu Perfil</h3>

    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($sucesso) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card border-0 shadow-sm p-4">
        <form method="POST" action="">
            <div class="mb-3">
                <label class="form-label fw-bold">CPF (Não alterável)</label>
                <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($usuario['cpf'] ?? '') ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Nome Completo *</label>
                <input type="text" name="nome" class="form-control bg-light" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" readonly>
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">E-mail</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Telefone / WhatsApp</label>
                <input type="text" name="telefone" class="form-control mask-phone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>">
            </div>

            <hr class="my-4">
            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-key me-1"></i> Alterar Senha</h6>

            <div class="mb-3">
                <label class="form-label fw-bold">Senha Atual</label>
                <input type="password" name="senha_atual" class="form-control" placeholder="Digite apenas se for alterar a senha">
            </div>

            <div class="mb-3">
                <label class="form-label fw-bold">Nova Senha</label>
                <input type="password" name="nova_senha" class="form-control" placeholder="Mínimo 6 caracteres">
            </div>

            <button type="submit" class="btn btn-primary fw-bold w-100 py-2 mt-3">
                <i class="bi bi-check-lg me-1"></i> Salvar Alterações
            </button>
        </form>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php';
?>
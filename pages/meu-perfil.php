<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

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

$usuarioId = (int)($_SESSION['usuario_id'] ?? 0);

if (!$usuarioId) {
    header('Location: /login');
    exit;
}

$erro = '';
$sucesso = '';

$stmt = $pdo->prepare("
    SELECT *
    FROM usuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$usuarioId]);
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$usuario) {
    session_unset();
    session_destroy();

    header('Location: /login');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $senhaAtual = $_POST['senha_atual'] ?? '';
    $novaSenha = $_POST['nova_senha'] ?? '';

    if ($nome === '') {
        $erro = 'O campo nome é obrigatório.';
    } elseif ($novaSenha !== '' && strlen($novaSenha) < 6) {
        $erro = 'A nova senha deve ter no mínimo 6 caracteres.';
    } elseif ($novaSenha !== '' && $senhaAtual === '') {
        $erro = 'Informe a senha atual para alterar sua senha.';
    } elseif ($novaSenha !== '' && !password_verify($senhaAtual, $usuario['senha'])) {
        $erro = 'A senha atual está incorreta.';
    } else {
        try {
            if ($novaSenha !== '') {
                $senhaHash = password_hash(
                    $novaSenha,
                    PASSWORD_DEFAULT
                );

                $stmtUp = $pdo->prepare("
                    UPDATE usuarios
                    SET
                        nome = ?,
                        email = ?,
                        telefone = ?,
                        senha = ?
                    WHERE id = ?
                ");

                $stmtUp->execute([
                    $nome,
                    $email !== '' ? $email : null,
                    $telefone !== '' ? $telefone : null,
                    $senhaHash,
                    $usuarioId
                ]);

                $sucesso = 'Perfil e senha atualizados com sucesso!';
            } else {
                $stmtUp = $pdo->prepare("
                    UPDATE usuarios
                    SET
                        nome = ?,
                        email = ?,
                        telefone = ?
                    WHERE id = ?
                ");

                $stmtUp->execute([
                    $nome,
                    $email !== '' ? $email : null,
                    $telefone !== '' ? $telefone : null,
                    $usuarioId
                ]);

                $sucesso = 'Perfil atualizado com sucesso!';
            }

            $stmt->execute([$usuarioId]);
            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($usuario) {
                $_SESSION['usuario_nome'] = $usuario['nome'];
            }
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar os dados do perfil.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="row justify-content-center">

        <div class="col-12 col-md-10 col-lg-8">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">

                <div>
                    <h3 class="fw-bold m-0">
                        <i class="bi bi-person-circle me-2 text-primary"></i>
                        Meu Perfil
                    </h3>

                    <p class="text-muted small m-0">
                        Gerencie seus dados pessoais e sua senha de acesso
                    </p>
                </div>

            </div>

            <?php if ($sucesso !== ''): ?>
                <div
                    class="alert alert-success alert-dismissible fade show border-0 shadow-sm"
                    role="alert"
                >
                    <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Fechar"
                    ></button>
                </div>
            <?php endif; ?>

            <?php if ($erro !== ''): ?>
                <div
                    class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"
                    role="alert"
                >
                    <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="alert"
                        aria-label="Fechar"
                    ></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-3 p-md-4">

                <form method="POST" action="">

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            CPF
                        </label>

                        <input
                            type="text"
                            class="form-control bg-light"
                            value="<?= htmlspecialchars($usuario['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            readonly
                        >

                        <small class="text-muted">
                            O CPF não pode ser alterado pelo usuário.
                        </small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Nome Completo *
                        </label>

                        <input
                            type="text"
                            name="nome"
                            class="form-control"
                            value="<?= htmlspecialchars($usuario['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="255"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            E-mail
                        </label>

                        <input
                            type="email"
                            name="email"
                            class="form-control"
                            value="<?= htmlspecialchars($usuario['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="255"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Telefone / WhatsApp
                        </label>

                        <input
                            type="text"
                            name="telefone"
                            class="form-control mask-phone"
                            inputmode="numeric"
                            value="<?= htmlspecialchars($usuario['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            maxlength="20"
                        >
                    </div>

                    <hr class="my-4">

                    <h6 class="fw-bold text-dark mb-1">
                        <i class="bi bi-key me-1"></i>
                        Alterar Senha
                    </h6>

                    <p class="text-muted small mb-3">
                        Deixe os campos abaixo em branco caso não queira alterar sua senha.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Senha Atual
                        </label>

                        <input
                            type="password"
                            name="senha_atual"
                            class="form-control"
                            placeholder="Digite sua senha atual"
                            autocomplete="current-password"
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">
                            Nova Senha
                        </label>

                        <input
                            type="password"
                            name="nova_senha"
                            class="form-control"
                            placeholder="Mínimo 6 caracteres"
                            minlength="6"
                            autocomplete="new-password"
                        >
                    </div>

                    <div class="mt-4">
                        <button
                            type="submit"
                            class="btn btn-primary fw-bold w-100 py-2"
                        >
                            <i class="bi bi-check-lg me-1"></i>
                            Salvar Alterações
                        </button>
                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
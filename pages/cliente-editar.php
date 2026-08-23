<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /clientes');
    exit;
}

$erro = '';
$sucesso = '';

// Buscar dados do usuário
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: /clientes');
    exit;
}

// Atualizar dados
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';

    if ($nome === '' || $cpf === '') {
        $erro = 'Nome e CPF são obrigatórios.';
    } else {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpfLimpo) !== 11) {
            $erro = 'Informe um CPF válido.';
        } else {
            // Verificar duplicidade de CPF em outro usuário
            $stmtCheck = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE REPLACE(
                    REPLACE(
                        REPLACE(cpf, '.', ''),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ) = ?
                AND id != ?
                LIMIT 1
            ");

            $stmtCheck->execute([
                $cpfLimpo,
                $id
            ]);

            if ($stmtCheck->fetch()) {
                $erro = 'Este CPF já pertence a outro usuário cadastrado.';
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
                                cpf = ?,
                                email = ?,
                                telefone = ?,
                                endereco = ?,
                                senha = ?
                            WHERE id = ?
                        ");

                        $stmtUp->execute([
                            $nome,
                            $cpf,
                            $email !== '' ? $email : null,
                            $telefone !== '' ? $telefone : null,
                            $endereco !== '' ? $endereco : null,
                            $senhaHash,
                            $id
                        ]);
                    } else {
                        $stmtUp = $pdo->prepare("
                            UPDATE usuarios
                            SET
                                nome = ?,
                                cpf = ?,
                                email = ?,
                                telefone = ?,
                                endereco = ?
                            WHERE id = ?
                        ");

                        $stmtUp->execute([
                            $nome,
                            $cpf,
                            $email !== '' ? $email : null,
                            $telefone !== '' ? $telefone : null,
                            $endereco !== '' ? $endereco : null,
                            $id
                        ]);
                    }

                    $sucesso = 'Dados atualizados com sucesso!';

                    // Recarregar dados atualizados
                    $stmt->execute([$id]);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                } catch (PDOException $e) {
                    $erro = 'Erro ao atualizar dados.';
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">

                <div class="flex-grow-1">
                    <h3 class="fw-bold m-0 text-nowrap">
                        <i class="bi bi-pencil-square me-2 text-primary"></i>
                        Editar Usuário
                    </h3>

                    <p class="text-muted m-0 small">
                        Atualize as informações cadastrais
                    </p>
                </div>

                <div>
                    <a
                        href="/clientes"
                        class="btn btn-outline-secondary btn-sm fw-bold text-nowrap"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar para Clientes
                    </a>
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
                    ></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-3 p-md-4">

                <form method="POST" action="">

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Nome Completo *
                            </label>

                            <input
                                type="text"
                                name="nome"
                                class="form-control"
                                value="<?= htmlspecialchars($cliente['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                CPF (Login) *
                            </label>

                            <input
                                type="text"
                                name="cpf"
                                class="form-control mask-cpf"
                                inputmode="numeric"
                                value="<?= htmlspecialchars($cliente['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                E-mail
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= htmlspecialchars($cliente['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Telefone / WhatsApp
                            </label>

                            <input
                                type="text"
                                name="telefone"
                                class="form-control mask-phone"
                                inputmode="numeric"
                                value="<?= htmlspecialchars($cliente['telefone'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">
                                Endereço Residencial
                            </label>

                            <input
                                type="text"
                                name="endereco"
                                class="form-control"
                                value="<?= htmlspecialchars($cliente['endereco'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                            >
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold text-muted m-0">
                            <i class="bi bi-key me-1"></i>
                            Redefinir Senha de Acesso
                        </h6>

                        <small class="text-muted d-block mb-2">
                            Preencha apenas se desejar alterar a senha do usuário.
                        </small>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">
                                Nova Senha
                            </label>

                            <input
                                type="password"
                                name="nova_senha"
                                class="form-control"
                                placeholder="Deixe em branco para manter a atual"
                                autocomplete="new-password"
                            >
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button
                                type="submit"
                                class="btn btn-primary fw-bold px-4 w-100 w-sm-auto"
                            >
                                <i class="bi bi-check-lg me-1"></i>
                                Salvar Alterações
                            </button>
                        </div>

                    </div>

                </form>

            </div>

        </div>
    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
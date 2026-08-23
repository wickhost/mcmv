<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: /clientes');
    exit;
}

$erro = '';
$sucesso = '';

// Buscar Dados do Usuário (Permite visualizar tanto clientes quanto admins)
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = ?");
$stmt->execute([$id]);
$cliente = $stmt->fetch();

if (!$cliente) {
    header('Location: /clientes');
    exit;
}

// Atualizar Dados do Cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';

    if (empty($nome) || empty($cpf)) {
        $erro = 'Nome e CPF são obrigatórios.';
    } else {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        // Verificar duplicidade de CPF em outro usuário
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ? AND id != ?");
        $stmtCheck->execute([$cpfLimpo, $id]);

        if ($stmtCheck->fetch()) {
            $erro = 'Este CPF já pertence a outro usuário cadastrado.';
        } else {
            try {
                if (!empty($novaSenha)) {
                    $senhaHash = password_hash($novaSenha, PASSWORD_DEFAULT);
                    $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, cpf = ?, email = ?, telefone = ?, endereco = ?, senha = ? WHERE id = ?");
                    $stmtUp->execute([$nome, $cpf, $email ?: null, $telefone ?: null, $endereco ?: null, $senhaHash, $id]);
                } else {
                    $stmtUp = $pdo->prepare("UPDATE usuarios SET nome = ?, cpf = ?, email = ?, telefone = ?, endereco = ? WHERE id = ?");
                    $stmtUp->execute([$nome, $cpf, $email ?: null, $telefone ?: null, $endereco ?: null, $id]);
                }

                $sucesso = 'Dados atualizados com sucesso!';
                
                // Recarregar dados atualizados
                $stmt->execute([$id]);
                $cliente = $stmt->fetch();
            } catch (PDOException $e) {
                $erro = 'Erro ao atualizar dados: ' . $e->getMessage();
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
                    <h3 class="fw-bold m-0 text-nowrap"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Usuário</h3>
                    <p class="text-muted m-0 small">Atualize as informações cadastrais</p>
                </div>
                <div>
                    <a href="/clientes" class="btn btn-outline-secondary btn-sm fw-bold text-nowrap">
                        <i class="bi bi-arrow-left me-1"></i> Voltar para Clientes
                    </a>
                </div>
            </div>

            <?php if ($sucesso): ?><div class="alert alert-success alert-dismissible fade show border-0 shadow-sm"><?= htmlspecialchars($sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
            <?php if ($erro): ?><div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"><?= htmlspecialchars($erro) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($cliente['nome']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">CPF (Login) *</label>
                            <input type="text" name="cpf" class="form-control mask-cpf" inputmode="numeric" value="<?= htmlspecialchars($cliente['cpf']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">E-mail</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($cliente['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" class="form-control mask-phone" inputmode="numeric" value="<?= htmlspecialchars($cliente['telefone'] ?? '') ?>">
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-bold">Endereço Residencial</label>
                            <input type="text" name="endereco" class="form-control" value="<?= htmlspecialchars($cliente['endereco'] ?? '') ?>">
                        </div>

                        <hr class="my-4">
                        <h6 class="fw-bold text-muted m-0"><i class="bi bi-key me-1"></i>Redefinir Senha de Acesso</h6>
                        <small class="text-muted d-block mb-2">Preencha apenas se desejar alterar a senha do usuário.</small>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nova Senha</label>
                            <input type="password" name="nova_senha" class="form-control" placeholder="Deixe em branco para manter a atual">
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary fw-bold px-4 w-100 w-sm-auto">
                                <i class="bi bi-check-lg me-1"></i> Salvar Alterações
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
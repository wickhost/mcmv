<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome     = trim($_POST['nome'] ?? '');
    $cpf      = trim($_POST['cpf'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $senha    = $_POST['senha'] ?? '';
    $tipo     = $_POST['tipo'] ?? 'cliente';

    if (empty($nome) || empty($cpf) || empty($senha)) {
        $erro = 'Nome, CPF e Senha são campos obrigatórios.';
    } else {
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        // Verificar duplicidade de CPF
        $stmtCheck = $pdo->prepare("SELECT id FROM usuarios WHERE REPLACE(REPLACE(REPLACE(cpf, '.', ''), '-', ''), ' ', '') = ?");
        $stmtCheck->execute([$cpfLimpo]);

        if ($stmtCheck->fetch()) {
            $erro = 'Este CPF já está cadastrado para outro usuário.';
        } else {
            try {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                
                $stmtIns = $pdo->prepare("INSERT INTO usuarios (nome, cpf, email, telefone, endereco, senha, tipo) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmtIns->execute([
                    $nome,
                    $cpf,
                    $email ?: null,
                    $telefone ?: null,
                    $endereco ?: null,
                    $senhaHash,
                    $tipo
                ]);

                header('Location: /clientes?sucesso=1');
                exit;
            } catch (PDOException $e) {
                $erro = 'Erro ao cadastrar cliente: ' . $e->getMessage();
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
                    <h3 class="fw-bold m-0 text-nowrap"><i class="bi bi-person-plus-fill me-2 text-primary"></i>Novo Cliente</h3>
                    <p class="text-muted m-0 small">Cadastre um novo cliente ou usuário no sistema</p>
                </div>
                <div>
                    <a href="/clientes" class="btn btn-outline-secondary btn-sm fw-bold text-nowrap">
                        <i class="bi bi-arrow-left me-1"></i> Voltar para Clientes
                    </a>
                </div>
            </div>

            <?php if ($erro): ?>
                <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm mb-3">
                    <?= htmlspecialchars($erro) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <form method="POST" action="">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" placeholder="Ex: João da Silva" value="<?= htmlspecialchars($_POST['nome'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">CPF (Login do Cliente) *</label>
                            <input type="text" name="cpf" class="form-control mask-cpf" inputmode="numeric" placeholder="000.000.000-00" value="<?= htmlspecialchars($_POST['cpf'] ?? '') ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">E-mail</label>
                            <input type="email" name="email" class="form-control" placeholder="exemplo@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Telefone / WhatsApp</label>
                            <input type="text" name="telefone" class="form-control mask-phone" inputmode="numeric" placeholder="(00) 00000-0000" value="<?= htmlspecialchars($_POST['telefone'] ?? '') ?>">
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-bold">Endereço Residencial</label>
                            <input type="text" name="endereco" class="form-control" placeholder="Rua, Número, Bairro" value="<?= htmlspecialchars($_POST['endereco'] ?? '') ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-bold">Tipo de Perfil *</label>
                            <select name="tipo" class="form-select">
                                <option value="cliente" <?= (($_POST['tipo'] ?? '') === 'cliente') ? 'selected' : '' ?>>Cliente</option>
                                <option value="admin" <?= (($_POST['tipo'] ?? '') === 'admin') ? 'selected' : '' ?>>Administrador</option>
                            </select>
                        </div>

                        <hr class="my-4">
                        <h6 class="fw-bold text-muted m-0"><i class="bi bi-key me-1"></i>Senha de Acesso *</h6>
                        <small class="text-muted d-block mb-1">Defina a senha inicial para o primeiro acesso do usuário no sistema.</small>

                        <div class="col-md-6">
                            <label class="form-label fw-bold">Senha *</label>
                            <input type="password" name="senha" class="form-control" placeholder="Digite uma senha segura" required>
                        </div>

                        <div class="col-12 mt-4 text-end">
                            <button type="submit" class="btn btn-primary fw-bold px-4 w-100 w-sm-auto">
                                <i class="bi bi-check-lg me-1"></i> Cadastrar Usuário
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
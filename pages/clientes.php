<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

// Buscar lista de clientes/usuários
$stmt = $pdo->query("SELECT * FROM usuarios ORDER BY id DESC");
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0"><i class="bi bi-people-fill text-primary me-2"></i>Clientes</h3>
            <p class="text-muted small m-0">Gerenciamento de clientes e usuários do sistema</p>
        </div>
        <div>
            <a href="/cliente-novo" class="btn btn-primary fw-bold w-100 w-md-auto">
                <i class="bi bi-person-plus-fill me-1"></i> Novo Cliente
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm p-3 p-md-4">
        <?php if (empty($usuarios)): ?>
            <div class="text-center py-5 my-2">
                <div class="mb-3">
                    <span class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle" style="width: 70px; height: 70px;">
                        <i class="bi bi-person-add fs-2"></i>
                    </span>
                </div>
                <h5 class="fw-bold text-dark mb-1">Nenhum cliente cadastrado</h5>
                <p class="text-muted small mb-3" style="max-width: 420px; margin: 0 auto;">
                    Cadastre os proprietários para vincular às obras e liberar o acesso ao portal do cliente.
                </p>
                <a href="/cliente-novo" class="btn btn-primary btn-sm fw-bold px-3 py-2">
                    <i class="bi bi-plus-lg me-1"></i> Cadastrar Primeiro Cliente
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th class="text-center">Tipo</th>
                            <th class="text-center" style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($u['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($u['email'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-muted small">
                                    <?= htmlspecialchars($u['telefone'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= ($u['tipo'] ?? '') === 'admin' ? 'bg-danger' : 'bg-primary' ?>">
                                        <?= htmlspecialchars(strtoupper($u['tipo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <a href="/cliente-editar?id=<?= (int) $u['id'] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
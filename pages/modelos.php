<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$erro = '';

// Processar exclusão de modelo
if (isset($_GET['excluir'])) {
    $idExcluir = filter_input(INPUT_GET, 'excluir', FILTER_VALIDATE_INT);

    if (!$idExcluir) {
        $erro = 'Modelo inválido.';
    } else {
        try {
            $stmtDel = $pdo->prepare("DELETE FROM modelos_casas WHERE id = ?");
            $stmtDel->execute([$idExcluir]);

            header('Location: /modelos?msg=deletado');
            exit;
        } catch (PDOException $e) {
            $erro = 'Não foi possível excluir o modelo. Verifique se existem obras vinculadas a ele.';
        }
    }
}

// Buscar Modelos e resumir etapas
$stmtModelos = $pdo->query("
    SELECT 
        m.*,
        COUNT(e.id) AS total_etapas,
        COALESCE(SUM(e.valor_estimado), 0) AS valor_total_estimado
    FROM modelos_casas m
    LEFT JOIN modelos_etapas e ON m.id = e.modelo_id
    GROUP BY m.id
    ORDER BY m.id DESC
");

$modelos = $stmtModelos->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <!-- Cabeçalho Limpo: Título Ocupa Tudo + Botão Menor no Canto -->
    <div class="page-header">
        <div class="page-header-content">
            <h3 class="page-title">
                <i class="bi bi-house-door text-primary me-2"></i>Modelos de Casas
            </h3>
            <p class="page-subtitle">Modelos padrões de cronograma físico-financeiro MCMV</p>
        </div>
        <a href="/modelo-novo" class="btn btn-primary btn-action-top">
            <i class="bi bi-plus-lg me-1"></i> Criar Novo Modelo
        </a>
    </div>

    <!-- Mensagens de Alerta -->
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'deletado'): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>Modelo excluído com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_GET['sucesso'])): ?>
        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-check-circle-fill me-2"></i>Modelo salvo com sucesso!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Conteúdo da Página -->
    <?php if (empty($modelos)): ?>
        <div class="card border-0 shadow-sm p-4 text-center">
            <div class="py-4">
                <i class="bi bi-card-checklist text-primary display-4 d-block mb-3 opacity-75"></i>
                <h5 class="fw-bold text-dark">Nenhum modelo cadastrado até o momento</h5>
                <p class="text-muted small mb-3">Crie modelos de etapas para agilizar o cadastro de novas obras de financiamento.</p>
                <a href="/modelo-novo" class="btn btn-primary btn-sm fw-bold px-3">
                    <i class="bi bi-plus-lg me-1"></i> Criar Novo Modelo
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm p-3 p-md-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Nome do Modelo</th>
                            <th>Descrição</th>
                            <th class="text-center">Total de Etapas</th>
                            <th class="text-end">Valor Ref. Total (R$)</th>
                            <th class="text-center" style="width: 120px;">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($modelos as $m): ?>
                            <tr>
                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars($m['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="text-muted small">
                                    <?= htmlspecialchars($m['descricao'] ?? 'Sem descrição', ENT_QUOTES, 'UTF-8') ?>
                                </td>

                                <td class="text-center">
                                    <span class="badge bg-secondary">
                                        <?= (int) $m['total_etapas'] ?> Etapas
                                    </span>
                                </td>

                                <td class="text-end fw-bold text-success">
                                    R$ <?= number_format((float) ($m['valor_total_estimado'] ?? 0), 2, ',', '.') ?>
                                </td>

                                <td class="text-center">
                                    <div class="d-inline-flex gap-1">
                                        <a
                                            href="/modelo-editar?id=<?= (int) $m['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Editar Modelo"
                                        >
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <a
                                            href="/modelos?excluir=<?= (int) $m['id'] ?>"
                                            class="btn btn-sm btn-outline-danger"
                                            title="Excluir Modelo"
                                            onclick="return confirm('Tem certeza que deseja excluir este modelo?')"
                                        >
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
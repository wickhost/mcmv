<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Cliente deve acessar somente o portal
if (($_SESSION['usuario_tipo'] ?? '') === 'cliente') {
    header('Location: /portal');
    exit;
}

verificarAdmin();

$totalClientes = (int)$pdo
    ->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'")
    ->fetchColumn();

$totalObras = (int)$pdo
    ->query("SELECT COUNT(*) FROM obras")
    ->fetchColumn();

$totalModelos = (int)$pdo
    ->query("SELECT COUNT(*) FROM modelos_casas")
    ->fetchColumn();

$stmtObras = $pdo->query("
    SELECT
        o.id,
        o.endereco_obra,
        COALESCE(o.progresso_total, 0) AS progresso_total,
        u.nome AS nome_cliente
    FROM obras o
    INNER JOIN usuarios u ON u.id = o.cliente_id
    ORDER BY o.id DESC
    LIMIT 10
");

$obrasRecentes = $stmtObras->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0">
                <i class="bi bi-speedometer2 text-primary me-2"></i>
                Painel Administrativo
            </h3>

            <p class="text-muted small m-0">
                Visão geral do sistema de obras e acompanhamento de clientes
            </p>
        </div>

        <div>
            <a href="/nova-obra" class="btn btn-primary fw-bold w-100 w-md-auto">
                <i class="bi bi-plus-lg me-1"></i>
                Nova Obra
            </a>
        </div>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold">TOTAL DE CLIENTES</small>

                <h3 class="fw-bold text-dark m-0 mt-1">
                    <?= $totalClientes ?>
                </h3>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-success h-100">
                <small class="text-muted fw-bold">OBRAS EM ANDAMENTO</small>

                <h3 class="fw-bold text-dark m-0 mt-1">
                    <?= $totalObras ?>
                </h3>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-lg-4">
            <div class="card border-0 shadow-sm p-3 border-start border-4 border-warning h-100">
                <small class="text-muted fw-bold">MODELOS CADASTRADOS</small>

                <h3 class="fw-bold text-dark m-0 mt-1">
                    <?= $totalModelos ?>
                </h3>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm p-3 p-md-4">

        <h5 class="fw-bold text-dark mb-3">
            <i class="bi bi-building me-2 text-primary"></i>
            Obras Cadastradas
        </h5>

        <?php if (empty($obrasRecentes)): ?>

            <div class="text-center py-5 my-2">

                <div class="mb-3">
                    <span
                        class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle"
                        style="width: 70px; height: 70px;"
                    >
                        <i class="bi bi-journal-plus fs-2"></i>
                    </span>
                </div>

                <h5 class="fw-bold text-dark mb-1">
                    Nenhuma obra cadastrada ainda
                </h5>

                <p
                    class="text-muted small mb-3"
                    style="max-width: 420px; margin: 0 auto;"
                >
                    Comece cadastrando a primeira obra para acompanhar o progresso físico-financeiro e disponibilizar o portal do cliente.
                </p>

                <a
                    href="/nova-obra"
                    class="btn btn-primary btn-sm fw-bold px-3 py-2"
                >
                    <i class="bi bi-plus-lg me-1"></i>
                    Cadastrar Primeira Obra
                </a>

            </div>

        <?php else: ?>

            <div class="table-responsive">

                <table class="table align-middle table-hover mb-0">

                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Cliente</th>
                            <th>Endereço da Obra</th>
                            <th style="min-width: 150px;">Progresso</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($obrasRecentes as $obra): ?>

                            <?php
                            $progresso = (float)$obra['progresso_total'];
                            $progresso = max(0, min(100, $progresso));
                            ?>

                            <tr>

                                <td class="fw-bold">
                                    #<?= (int)$obra['id'] ?>
                                </td>

                                <td class="fw-bold text-dark">
                                    <?= htmlspecialchars(
                                        $obra['nome_cliente'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>
                                    <i class="bi bi-geo-alt me-1 text-danger"></i>

                                    <?= htmlspecialchars(
                                        $obra['endereco_obra'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>
                                </td>

                                <td>

                                    <div class="d-flex align-items-center gap-2">

                                        <div
                                            class="progress flex-grow-1"
                                            style="height: 10px;"
                                        >
                                            <div
                                                class="progress-bar bg-success"
                                                role="progressbar"
                                                style="width: <?= $progresso ?>%;"
                                                aria-valuenow="<?= $progresso ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                            ></div>
                                        </div>

                                        <span class="small fw-bold">
                                            <?= number_format(
                                                $progresso,
                                                0,
                                                ',',
                                                '.'
                                            ) ?>%
                                        </span>

                                    </div>

                                </td>

                                <td class="text-end">

                                    <a
                                        href="/gerenciar-obra?id=<?= (int)$obra['id'] ?>"
                                        class="btn btn-sm btn-outline-primary fw-bold text-nowrap"
                                    >
                                        <i class="bi bi-gear-fill me-1"></i>
                                        Gerenciar
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
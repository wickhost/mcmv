<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/header.php';

$stmtClientes = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE tipo = 'cliente'");
$totalClientes = (int) $stmtClientes->fetchColumn();

$stmtObras = $pdo->query("SELECT COUNT(*) FROM obras");
$totalObras = (int) $stmtObras->fetchColumn();

$stmtObrasAndamento = $pdo->query("
    SELECT COUNT(*)
    FROM obras
    WHERE status != 'Concluída'
");
$totalObrasAndamento = (int) $stmtObrasAndamento->fetchColumn();

$stmtObrasConcluidas = $pdo->query("
    SELECT COUNT(*)
    FROM obras
    WHERE status = 'Concluída'
");
$totalObrasConcluidas = (int) $stmtObrasConcluidas->fetchColumn();

$stmtObrasRecentes = $pdo->query("
    SELECT
        o.id,
        o.nome_obra,
        o.status,
        o.progresso,
        u.nome AS cliente_nome
    FROM obras o
    LEFT JOIN usuarios u ON u.id = o.cliente_id
    ORDER BY o.id DESC
    LIMIT 5
");
$obrasRecentes = $stmtObrasRecentes->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="container">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="bi bi-speedometer2 text-primary me-2"></i>
                Dashboard
            </h2>
            <p class="text-muted mb-0">
                Visão geral da gestão de obras
            </p>
        </div>

        <a href="/nova-obra" class="btn btn-primary mt-3 mt-md-0">
            <i class="bi bi-plus-circle me-1"></i>
            Nova Obra
        </a>
    </div>

    <div class="row g-3 mb-4">

        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Clientes</div>
                            <div class="fs-3 fw-bold"><?= $totalClientes ?></div>
                        </div>
                        <div class="text-primary fs-2">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <a href="/clientes" class="small text-decoration-none">
                        Ver clientes
                    </a>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Total de Obras</div>
                            <div class="fs-3 fw-bold"><?= $totalObras ?></div>
                        </div>
                        <div class="text-primary fs-2">
                            <i class="bi bi-building-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Em Andamento</div>
                            <div class="fs-3 fw-bold"><?= $totalObrasAndamento ?></div>
                        </div>
                        <div class="text-warning fs-2">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-6 col-lg-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="text-muted small">Concluídas</div>
                            <div class="fs-3 fw-bold"><?= $totalObrasConcluidas ?></div>
                        </div>
                        <div class="text-success fs-2">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3">
            <h5 class="mb-0 fw-bold">
                <i class="bi bi-clock-history me-2"></i>
                Obras Recentes
            </h5>
        </div>

        <div class="card-body p-0">

            <?php if (empty($obrasRecentes)): ?>

                <div class="text-center py-5 text-muted">
                    <i class="bi bi-building fs-1 d-block mb-2"></i>
                    Nenhuma obra cadastrada.
                </div>

            <?php else: ?>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Obra</th>
                                <th>Cliente</th>
                                <th>Status</th>
                                <th>Progresso</th>
                                <th></th>
                            </tr>
                        </thead>

                        <tbody>
                            <?php foreach ($obrasRecentes as $obra): ?>

                                <?php
                                $progresso = max(
                                    0,
                                    min(
                                        100,
                                        (float) ($obra['progresso'] ?? 0)
                                    )
                                );
                                ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $obra['nome_obra'] ?? 'Obra',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $obra['cliente_nome'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $obra['status'] ?? '-',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </td>

                                    <td style="min-width: 150px;">
                                        <div class="d-flex justify-content-between small mb-1">
                                            <span><?= $progresso ?>%</span>
                                        </div>

                                        <div class="progress" style="height: 6px;">
                                            <div
                                                class="progress-bar"
                                                role="progressbar"
                                                style="width: <?= $progresso ?>%;"
                                                aria-valuenow="<?= $progresso ?>"
                                                aria-valuemin="0"
                                                aria-valuemax="100"
                                            ></div>
                                        </div>
                                    </td>

                                    <td class="text-end">
                                        <a
                                            href="/gerenciar-obra?id=<?= (int) $obra['id'] ?>"
                                            class="btn btn-sm btn-outline-primary"
                                        >
                                            <i class="bi bi-eye"></i>
                                            <span class="d-none d-md-inline">Ver</span>
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

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
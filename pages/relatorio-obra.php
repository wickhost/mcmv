<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAutenticado();

$obra_id = $_GET['id'] ?? null;
if (!$obra_id) {
    header('Location: /dashboard');
    exit;
}

// Se o usuário logado for cliente, garante que ele só veja a própria obra
if ($_SESSION['usuario_tipo'] === 'cliente') {
    $stmtCheck = $pdo->prepare("SELECT id FROM obras WHERE id = ? AND cliente_id = ?");
    $stmtCheck->execute([$obra_id, $_SESSION['usuario_id']]);
    if (!$stmtCheck->fetch()) {
        header('Location: /portal-cliente');
        exit;
    }
}

// Buscar Informações Completas da Obra
$stmtObra = $pdo->prepare("
    SELECT o.*, u.nome AS nome_cliente, u.cpf AS cpf_cliente, u.telefone AS fone_cliente 
    FROM obras o 
    JOIN usuarios u ON o.cliente_id = u.id 
    WHERE o.id = ?
");
$stmtObra->execute([$obra_id]);
$obra = $stmtObra->fetch();

if (!$obra) {
    header('Location: /dashboard');
    exit;
}

// Buscar Etapas
$stmtEtapas = $pdo->prepare("SELECT * FROM obra_etapas WHERE obra_id = ? ORDER BY ordem ASC");
$stmtEtapas->execute([$obra_id]);
$etapas = $stmtEtapas->fetchAll();

// Buscar Fotos
$stmtFotos = $pdo->prepare("SELECT * FROM obra_fotos WHERE obra_id = ? ORDER BY id DESC");
$stmtFotos->execute([$obra_id]);
$fotos = $stmtFotos->fetchAll();

// Buscar Documentos
$stmtDocs = $pdo->prepare("SELECT * FROM obra_documentos WHERE obra_id = ? ORDER BY id DESC");
$stmtDocs->execute([$obra_id]);
$documentos = $stmtDocs->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Acompanhamento - Obra #<?= $obra['id'] ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }
        .relatorio-container {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,0,0,0.05);
        }
        .header-logo {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .table-custom th {
            background-color: #f1f5f9;
            color: #334155;
            font-weight: 600;
        }
        @media print {
            body {
                background: #fff;
            }
            .relatorio-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
            }
            .no-print {
                display: none !important;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>

<div class="container no-print text-center my-3">
    <button onclick="window.print()" class="btn btn-primary fw-bold px-4 me-2">
        <i class="bi bi-printer me-1"></i> Imprimir / Salvar PDF
    </button>
    <a href="javascript:history.back()" class="btn btn-outline-secondary fw-bold">
        <i class="bi bi-arrow-left me-1"></i> Voltar
    </a>
</div>

<div class="relatorio-container">
    <!-- Cabeçalho -->
    <div class="header-logo d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fw-bold text-primary m-0"><i class="bi bi-building-gear me-2"></i>OBRA FÁCIL</h3>
            <small class="text-muted fw-bold">Relatório Físico-Financeiro de Acompanhamento</small>
        </div>
        <div class="text-end">
            <small class="d-block text-muted">Data do Relatório</small>
            <strong class="text-dark"><?= date('d/m/Y H:i') ?></strong>
        </div>
    </div>

    <!-- Informações Principais -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6">
            <div class="p-3 bg-light rounded border">
                <small class="text-muted fw-bold d-block mb-1">PROPRIETÁRIO / CLIENTE</small>
                <h5 class="fw-bold m-0 text-dark"><?= htmlspecialchars($obra['nome_cliente']) ?></h5>
                <small class="text-muted">CPF: <?= htmlspecialchars($obra['cpf_cliente']) ?> | Tel: <?= htmlspecialchars($obra['fone_cliente']) ?></small>
            </div>
        </div>
        <div class="col-12 col-sm-6">
            <div class="p-3 bg-light rounded border">
                <small class="text-muted fw-bold d-block mb-1">ENDEREÇO DA OBRA</small>
                <h6 class="fw-bold m-0 text-dark"><?= htmlspecialchars($obra['endereco_obra']) ?></h6>
                <small class="text-muted">Código da Obra: #<?= $obra['id'] ?></small>
            </div>
        </div>
    </div>

    <!-- Resumo do Financiamento -->
    <div class="card border-0 bg-light p-3 mb-4 rounded">
        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-cash-coin me-1"></i> Resumo Financeiro</h6>
        <div class="row text-center g-2">
            <div class="col-6 col-sm-3">
                <small class="text-muted d-block fw-bold">VALOR TOTAL</small>
                <span class="fw-bold text-dark">R$ <?= number_format($obra['valor_total'], 2, ',', '.') ?></span>
            </div>
            <div class="col-6 col-sm-3">
                <small class="text-muted d-block fw-bold">TERRENO</small>
                <span class="fw-bold text-dark">R$ <?= number_format($obra['valor_terreno'], 2, ',', '.') ?></span>
            </div>
            <div class="col-6 col-sm-3">
                <small class="text-muted d-block fw-bold">SUBSÍDIO / ENTRADA</small>
                <span class="fw-bold text-dark">R$ <?= number_format($obra['valor_subsidio'] + $obra['valor_entrada'], 2, ',', '.') ?></span>
            </div>
            <div class="col-6 col-sm-3">
                <small class="text-muted d-block fw-bold">DESTINADO À OBRA</small>
                <span class="fw-bold text-success">R$ <?= number_format($obra['sobra_construcao'], 2, ',', '.') ?></span>
            </div>
        </div>
    </div>

    <!-- Evolução Física Geral -->
    <div class="mb-4">
        <div class="d-flex justify-content-between align-items-center mb-1">
            <h6 class="fw-bold text-dark m-0"><i class="bi bi-bar-chart-line me-1 text-primary"></i> Evolução Física Global</h6>
            <strong class="text-success fs-5"><?= number_format((float)$obra['progresso_total'], 1, ',', '.') ?>%</strong>
        </div>
        <div class="progress" style="height: 14px;">
            <div class="progress-bar bg-success" style="width: <?= (float)$obra['progresso_total'] ?>%;"></div>
        </div>
    </div>

    <!-- Tabela de Cronograma / Etapas -->
    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-list-check me-1 text-primary"></i> Detalhamento das Etapas Físicas</h6>
    <table class="table table-bordered align-middle table-custom mb-4">
        <thead>
            <tr>
                <th class="text-center" style="width: 40px;">#</th>
                <th>Etapa Executada</th>
                <th class="text-center" style="width: 80px;">Peso</th>
                <th class="text-end" style="width: 120px;">Valor (R$)</th>
                <th class="text-center" style="width: 100px;">Progresso</th>
                <th class="text-center" style="width: 110px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($etapas as $e): ?>
                <tr>
                    <td class="text-center fw-bold"><?= $e['ordem'] ?></td>
                    <td><?= htmlspecialchars($e['nome_etapa']) ?></td>
                    <td class="text-center"><?= number_format($e['peso_percentual'], 2, ',', '.') ?>%</td>
                    <td class="text-end">R$ <?= number_format($e['valor_etapa'], 2, ',', '.') ?></td>
                    <td class="text-center fw-bold"><?= number_format((float)$e['progresso'], 0) ?>%</td>
                    <td class="text-center">
                        <?php if ($e['concluido']): ?>
                            <span class="badge bg-success">Concluída</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Em Aberto</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Galeria de Fotos no Relatório -->
    <?php if (!empty($fotos)): ?>
        <div class="page-break"></div>
        <h6 class="fw-bold text-dark mb-3"><i class="bi bi-camera me-1 text-primary"></i> Registros Fotográficos da Obra</h6>
        <div class="row g-3 mb-4">
            <?php foreach ($fotos as $f): ?>
                <div class="col-4">
                    <div class="border rounded p-1 text-center bg-light">
                        <img src="/uploads/fotos/<?= $f['caminho_foto'] ?>" class="img-fluid rounded" style="max-height: 150px; object-fit: cover; width: 100%;" alt="Foto da Obra">
                        <small class="d-block text-muted mt-1 text-truncate" style="font-size: 11px;"><?= htmlspecialchars($f['descricao'] ?? 'Acompanhamento') ?></small>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Assinaturas -->
    <div class="mt-5 pt-4 border-top">
        <div class="row text-center g-4">
            <div class="col-6">
                <div class="border-bottom mx-auto" style="width: 80%;"></div>
                <small class="fw-bold d-block mt-1">Responsável Técnico / Engenheiro</small>
            </div>
            <div class="col-6">
                <div class="border-bottom mx-auto" style="width: 80%;"></div>
                <small class="fw-bold d-block mt-1"><?= htmlspecialchars($obra['nome_cliente']) ?> (Proprietário)</small>
            </div>
        </div>
    </div>
</div>

</body>
</html>
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

verificarAutenticado();

$obra_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$obra_id) {
    header('Location: /dashboard');
    exit;
}

/*
 * Cliente só pode visualizar a própria obra.
 * Administrador pode visualizar qualquer obra.
 */
if (($_SESSION['usuario_tipo'] ?? '') === 'cliente') {
    $stmtCheck = $pdo->prepare("
        SELECT id
        FROM obras
        WHERE id = ?
          AND cliente_id = ?
        LIMIT 1
    ");

    $stmtCheck->execute([
        $obra_id,
        $_SESSION['usuario_id']
    ]);

    if (!$stmtCheck->fetchColumn()) {
        header('Location: /portal-cliente');
        exit;
    }
}

/*
 * Buscar informações completas da obra
 */
$stmtObra = $pdo->prepare("
    SELECT
        o.*,
        u.nome AS nome_cliente,
        u.cpf AS cpf_cliente,
        u.telefone AS fone_cliente
    FROM obras o
    INNER JOIN usuarios u ON u.id = o.cliente_id
    WHERE o.id = ?
    LIMIT 1
");

$stmtObra->execute([$obra_id]);
$obra = $stmtObra->fetch(PDO::FETCH_ASSOC);

if (!$obra) {
    header('Location: /dashboard');
    exit;
}

/*
 * Garantir que o progresso geral nunca fique fora de 0-100.
 */
$progressoTotal = (float)($obra['progresso_total'] ?? 0);
$progressoTotal = max(0, min(100, $progressoTotal));

/*
 * Valores financeiros
 */
$valorTotal = (float)($obra['valor_total'] ?? 0);
$valorTerreno = (float)($obra['valor_terreno'] ?? 0);
$valorSubsidio = (float)($obra['valor_subsidio'] ?? 0);
$valorEntrada = (float)($obra['valor_entrada'] ?? 0);
$sobraConstrucao = (float)($obra['sobra_construcao'] ?? 0);

$subsidioEntrada = $valorSubsidio + $valorEntrada;

/*
 * Buscar etapas
 */
$stmtEtapas = $pdo->prepare("
    SELECT *
    FROM obra_etapas
    WHERE obra_id = ?
    ORDER BY ordem ASC, id ASC
");

$stmtEtapas->execute([$obra_id]);
$etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

/*
 * Buscar fotos
 */
$stmtFotos = $pdo->prepare("
    SELECT *
    FROM obra_fotos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtFotos->execute([$obra_id]);
$fotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);

/*
 * Buscar documentos
 */
$stmtDocs = $pdo->prepare("
    SELECT *
    FROM obra_documentos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtDocs->execute([$obra_id]);
$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);

/*
 * Função para escapar textos com segurança.
 */
function e($valor): string
{
    return htmlspecialchars(
        (string)($valor ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Relatório de Acompanhamento - Obra #<?= (int)$obra['id'] ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <style>
        body {
            background-color: #f8f9fa;
            font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
            color: #333;
        }

        .relatorio-container {
            max-width: 900px;
            margin: 20px auto;
            background: #fff;
            padding: 40px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.05);
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

        .foto-relatorio {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        @media print {

            @page {
                size: A4;
                margin: 12mm;
            }

            body {
                background: #fff;
            }

            .relatorio-container {
                box-shadow: none;
                padding: 0;
                margin: 0;
                max-width: 100%;
                width: 100%;
            }

            .no-print {
                display: none !important;
            }

            .page-break {
                page-break-before: always;
            }

            .avoid-break {
                page-break-inside: avoid;
            }

            .table {
                font-size: 11px;
            }

            .table-custom th,
            .table-custom td {
                padding: 6px;
            }

            .foto-relatorio {
                height: 145px;
            }

            a {
                color: inherit !important;
                text-decoration: none !important;
            }
        }
    </style>
</head>

<body>

<!-- Botões fora do relatório e que não aparecem na impressão -->
<div class="container no-print text-center my-3">

    <button
        type="button"
        onclick="window.print()"
        class="btn btn-primary fw-bold px-4 me-2"
    >
        <i class="bi bi-printer me-1"></i>
        Imprimir / Salvar PDF
    </button>

    <a
        href="javascript:history.back()"
        class="btn btn-outline-secondary fw-bold"
    >
        <i class="bi bi-arrow-left me-1"></i>
        Voltar
    </a>

</div>

<div class="relatorio-container">

    <!-- CABEÇALHO -->
    <div class="header-logo d-flex justify-content-between align-items-center gap-3">

        <div>
            <h3 class="fw-bold text-primary m-0">
                <i class="bi bi-building-gear me-2"></i>
                OBRA FÁCIL
            </h3>

            <small class="text-muted fw-bold">
                Relatório Físico-Financeiro de Acompanhamento
            </small>
        </div>

        <div class="text-end">
            <small class="d-block text-muted">
                Data do Relatório
            </small>

            <strong class="text-dark">
                <?= date('d/m/Y H:i') ?>
            </strong>
        </div>

    </div>

    <!-- INFORMAÇÕES PRINCIPAIS -->
    <div class="row g-3 mb-4">

        <div class="col-12 col-sm-6">
            <div class="p-3 bg-light rounded border h-100">

                <small class="text-muted fw-bold d-block mb-1">
                    PROPRIETÁRIO / CLIENTE
                </small>

                <h5 class="fw-bold m-0 text-dark">
                    <?= e($obra['nome_cliente']) ?>
                </h5>

                <small class="text-muted">
                    CPF:
                    <?= e($obra['cpf_cliente']) ?>

                    <?php if (!empty($obra['fone_cliente'])): ?>
                        | Tel:
                        <?= e($obra['fone_cliente']) ?>
                    <?php endif; ?>
                </small>

            </div>
        </div>

        <div class="col-12 col-sm-6">
            <div class="p-3 bg-light rounded border h-100">

                <small class="text-muted fw-bold d-block mb-1">
                    ENDEREÇO DA OBRA
                </small>

                <h6 class="fw-bold m-0 text-dark">
                    <?= e($obra['endereco_obra']) ?>
                </h6>

                <small class="text-muted">
                    Código da Obra:
                    #<?= (int)$obra['id'] ?>
                </small>

            </div>
        </div>

    </div>

    <!-- RESUMO FINANCEIRO -->
    <div class="card border-0 bg-light p-3 mb-4 rounded avoid-break">

        <h6 class="fw-bold text-primary mb-3">
            <i class="bi bi-cash-coin me-1"></i>
            Resumo Financeiro
        </h6>

        <div class="row text-center g-3">

            <div class="col-6 col-sm-3">

                <small class="text-muted d-block fw-bold">
                    VALOR TOTAL
                </small>

                <span class="fw-bold text-dark">
                    R$
                    <?= number_format(
                        $valorTotal,
                        2,
                        ',',
                        '.'
                    ) ?>
                </span>

            </div>

            <div class="col-6 col-sm-3">

                <small class="text-muted d-block fw-bold">
                    TERRENO
                </small>

                <span class="fw-bold text-dark">
                    R$
                    <?= number_format(
                        $valorTerreno,
                        2,
                        ',',
                        '.'
                    ) ?>
                </span>

            </div>

            <div class="col-6 col-sm-3">

                <small class="text-muted d-block fw-bold">
                    SUBSÍDIO / ENTRADA
                </small>

                <span class="fw-bold text-dark">
                    R$
                    <?= number_format(
                        $subsidioEntrada,
                        2,
                        ',',
                        '.'
                    ) ?>
                </span>

            </div>

            <div class="col-6 col-sm-3">

                <small class="text-muted d-block fw-bold">
                    DESTINADO À OBRA
                </small>

                <span class="fw-bold text-success">
                    R$
                    <?= number_format(
                        $sobraConstrucao,
                        2,
                        ',',
                        '.'
                    ) ?>
                </span>

            </div>

        </div>

    </div>

    <!-- EVOLUÇÃO FÍSICA -->
    <div class="mb-4 avoid-break">

        <div class="d-flex justify-content-between align-items-center mb-1">

            <h6 class="fw-bold text-dark m-0">
                <i class="bi bi-bar-chart-line me-1 text-primary"></i>
                Evolução Física Global
            </h6>

            <strong class="text-success fs-5">
                <?= number_format(
                    $progressoTotal,
                    1,
                    ',',
                    '.'
                ) ?>%
            </strong>

        </div>

        <div
            class="progress"
            style="height: 14px;"
        >
            <div
                class="progress-bar bg-success"
                role="progressbar"
                style="width: <?= $progressoTotal ?>%;"
                aria-valuenow="<?= $progressoTotal ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>
        </div>

    </div>

    <!-- ETAPAS -->
    <h6 class="fw-bold text-dark mb-2">
        <i class="bi bi-list-check me-1 text-primary"></i>
        Detalhamento das Etapas Físicas
    </h6>

    <?php if (empty($etapas)): ?>

        <div class="alert alert-secondary text-center">
            Nenhuma etapa cadastrada para esta obra.
        </div>

    <?php else: ?>

        <div class="table-responsive">

            <table class="table table-bordered align-middle table-custom mb-4">

                <thead>
                    <tr>
                        <th
                            class="text-center"
                            style="width: 40px;"
                        >
                            #
                        </th>

                        <th>
                            Etapa Executada
                        </th>

                        <th
                            class="text-center"
                            style="width: 80px;"
                        >
                            Peso
                        </th>

                        <th
                            class="text-end"
                            style="width: 120px;"
                        >
                            Valor (R$)
                        </th>

                        <th
                            class="text-center"
                            style="width: 100px;"
                        >
                            Progresso
                        </th>

                        <th
                            class="text-center"
                            style="width: 110px;"
                        >
                            Status
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($etapas as $e): ?>

                        <?php
                        $progressoEtapa = (float)($e['progresso'] ?? 0);
                        $progressoEtapa = max(
                            0,
                            min(100, $progressoEtapa)
                        );

                        $pesoEtapa = (float)($e['peso_percentual'] ?? 0);
                        $valorEtapa = (float)($e['valor_etapa'] ?? 0);
                        $concluida = !empty($e['concluido']);
                        ?>

                        <tr>

                            <td class="text-center fw-bold">
                                <?= (int)$e['ordem'] ?>
                            </td>

                            <td>
                                <?= e($e['nome_etapa']) ?>
                            </td>

                            <td class="text-center">
                                <?= number_format(
                                    $pesoEtapa,
                                    2,
                                    ',',
                                    '.'
                                ) ?>%
                            </td>

                            <td class="text-end">
                                R$
                                <?= number_format(
                                    $valorEtapa,
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </td>

                            <td class="text-center fw-bold">
                                <?= number_format(
                                    $progressoEtapa,
                                    0,
                                    ',',
                                    '.'
                                ) ?>%
                            </td>

                            <td class="text-center">

                                <?php if ($concluida): ?>

                                    <span class="badge bg-success">
                                        Concluída
                                    </span>

                                <?php else: ?>

                                    <span class="badge bg-secondary">
                                        Em Aberto
                                    </span>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

    <!-- FOTOS -->
    <?php if (!empty($fotos)): ?>

        <div class="page-break"></div>

        <h6 class="fw-bold text-dark mb-3">
            <i class="bi bi-camera me-1 text-primary"></i>
            Registros Fotográficos da Obra
        </h6>

        <div class="row g-3 mb-4">

            <?php foreach ($fotos as $f): ?>

                <?php
                $nomeFoto = basename(
                    (string)($f['caminho_foto'] ?? '')
                );

                if ($nomeFoto === '') {
                    continue;
                }

                $urlFoto = '/uploads/fotos/' . rawurlencode($nomeFoto);
                ?>

                <div class="col-4">

                    <div class="border rounded p-1 text-center bg-light avoid-break">

                        <img
                            src="<?= e($urlFoto) ?>"
                            class="img-fluid rounded foto-relatorio"
                            alt="Foto da Obra"
                        >

                        <small
                            class="d-block text-muted mt-1 text-truncate"
                            style="font-size: 11px;"
                        >
                            <?= e(
                                !empty($f['descricao'])
                                    ? $f['descricao']
                                    : 'Acompanhamento'
                            ) ?>
                        </small>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

    <!-- DOCUMENTOS -->
    <?php if (!empty($documentos)): ?>

        <div class="mb-4 avoid-break">

            <h6 class="fw-bold text-dark mb-3">
                <i class="bi bi-files me-1 text-primary"></i>
                Documentos e Comprovantes
            </h6>

            <table class="table table-bordered align-middle">

                <thead class="table-light">
                    <tr>
                        <th>
                            Documento
                        </th>

                        <th
                            class="text-center"
                            style="width: 120px;"
                        >
                            Disponível
                        </th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($documentos as $doc): ?>

                        <tr>

                            <td>
                                <i class="bi bi-file-earmark-text text-primary me-2"></i>

                                <?= e($doc['nome_documento']) ?>
                            </td>

                            <td class="text-center">
                                <span class="badge bg-success">
                                    Anexado
                                </span>
                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

    <!-- ASSINATURAS -->
    <div class="mt-5 pt-4 border-top">

        <div class="row text-center g-4">

            <div class="col-6">

                <div
                    class="border-bottom mx-auto"
                    style="width: 80%;"
                ></div>

                <small class="fw-bold d-block mt-1">
                    Responsável Técnico / Engenheiro
                </small>

            </div>

            <div class="col-6">

                <div
                    class="border-bottom mx-auto"
                    style="width: 80%;"
                ></div>

                <small class="fw-bold d-block mt-1">
                    <?= e($obra['nome_cliente']) ?>
                    (Proprietário)
                </small>

            </div>

        </div>

    </div>

</div>

</body>
</html>
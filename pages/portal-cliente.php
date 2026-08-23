<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Autenticação
|--------------------------------------------------------------------------
*/
if (!isset($_SESSION['usuario_id'])) {
    header('Location: /login');
    exit;
}

$usuarioId = (int)$_SESSION['usuario_id'];
$usuarioTipo = $_SESSION['usuario_tipo'] ?? '';

/*
|--------------------------------------------------------------------------
| Apenas clientes acessam o portal
|--------------------------------------------------------------------------
*/
if ($usuarioTipo !== 'cliente') {
    header('Location: /dashboard');
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar obra vinculada ao cliente logado
|--------------------------------------------------------------------------
*/
$stmtObra = $pdo->prepare("
    SELECT
        o.*,
        u.nome AS nome_cliente,
        u.telefone AS fone_cliente
    FROM obras o
    INNER JOIN usuarios u
        ON u.id = o.cliente_id
    WHERE o.cliente_id = ?
    ORDER BY o.id DESC
    LIMIT 1
");

$stmtObra->execute([$usuarioId]);

$obra = $stmtObra->fetch(PDO::FETCH_ASSOC);

if (!$obra) {
    require_once __DIR__ . '/../includes/header.php';
    ?>

    <div class="container my-5 text-center py-5">

        <i class="bi bi-house-exclamation text-warning display-1"></i>

        <h3 class="fw-bold mt-3">
            Nenhuma obra vinculada
        </h3>

        <p class="text-muted">
            No momento não encontramos nenhuma obra vinculada ao seu cadastro.
        </p>

    </div>

    <?php
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$obraId = (int)$obra['id'];


/*
|--------------------------------------------------------------------------
| Progresso geral
|--------------------------------------------------------------------------
*/
$progressoTotal = (float)($obra['progresso_total'] ?? 0);
$progressoTotal = max(0, min(100, $progressoTotal));


/*
|--------------------------------------------------------------------------
| Buscar etapas
|--------------------------------------------------------------------------
*/
$stmtEtapas = $pdo->prepare("
    SELECT *
    FROM obra_etapas
    WHERE obra_id = ?
    ORDER BY ordem ASC, id ASC
");

$stmtEtapas->execute([$obraId]);

$etapas = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Buscar fotos
|--------------------------------------------------------------------------
*/
$stmtFotos = $pdo->prepare("
    SELECT *
    FROM obra_fotos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtFotos->execute([$obraId]);

$fotos = $stmtFotos->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Buscar documentos
|--------------------------------------------------------------------------
*/
$stmtDocs = $pdo->prepare("
    SELECT *
    FROM obra_documentos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtDocs->execute([$obraId]);

$documentos = $stmtDocs->fetchAll(PDO::FETCH_ASSOC);


require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <!-- CABEÇALHO -->

    <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-primary text-white rounded-3">

        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">

            <div>

                <span class="badge bg-white text-primary fw-bold mb-2">
                    Acompanhamento em Tempo Real
                </span>

                <h3 class="fw-bold m-0">
                    Olá,
                    <?= htmlspecialchars(
                        $obra['nome_cliente'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>!
                </h3>

                <p class="m-0 small opacity-75">

                    <i class="bi bi-geo-alt-fill me-1"></i>

                    <?= htmlspecialchars(
                        $obra['endereco_obra'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>

            </div>

            <div class="text-md-end">

                <small class="d-block opacity-75">
                    Progresso Geral da Obra
                </small>

                <h1 class="fw-bold m-0 display-5">
                    <?= number_format(
                        $progressoTotal,
                        1,
                        ',',
                        '.'
                    ) ?>%
                </h1>

            </div>

        </div>


        <div
            class="progress mt-3 bg-white bg-opacity-25"
            style="height: 12px;"
        >

            <div
                class="progress-bar bg-warning"
                role="progressbar"
                style="width: <?= $progressoTotal ?>%;"
                aria-valuenow="<?= $progressoTotal ?>"
                aria-valuemin="0"
                aria-valuemax="100"
            ></div>

        </div>

    </div>


    <!-- NAVEGAÇÃO -->

    <ul
        class="nav nav-pills nav-fill mb-4 bg-white p-2 rounded shadow-sm gap-2"
        id="portalTabs"
        role="tablist"
    >

        <li class="nav-item" role="presentation">

            <button
                class="nav-link active fw-bold py-2"
                id="crono-tab"
                data-bs-toggle="tab"
                data-bs-target="#crono-pane"
                type="button"
                role="tab"
                aria-controls="crono-pane"
                aria-selected="true"
            >

                <i class="bi bi-list-task me-1"></i>

                Cronograma

            </button>

        </li>


        <li class="nav-item" role="presentation">

            <button
                class="nav-link fw-bold py-2"
                id="fotos-tab"
                data-bs-toggle="tab"
                data-bs-target="#fotos-pane"
                type="button"
                role="tab"
                aria-controls="fotos-pane"
                aria-selected="false"
            >

                <i class="bi bi-camera me-1"></i>

                Fotos (<?= count($fotos) ?>)

            </button>

        </li>


        <li class="nav-item" role="presentation">

            <button
                class="nav-link fw-bold py-2"
                id="docs-tab"
                data-bs-toggle="tab"
                data-bs-target="#docs-pane"
                type="button"
                role="tab"
                aria-controls="docs-pane"
                aria-selected="false"
            >

                <i class="bi bi-paperclip me-1"></i>

                Documentos

            </button>

        </li>

    </ul>


    <div
        class="tab-content"
        id="portalTabsContent"
    >

        <!-- ==========================================================
             CRONOGRAMA
        =========================================================== -->

        <div
            class="tab-pane fade show active"
            id="crono-pane"
            role="tabpanel"
            aria-labelledby="crono-tab"
            tabindex="0"
        >

            <div class="card border-0 shadow-sm p-3 p-md-4">

                <h5 class="fw-bold text-dark mb-3">

                    <i class="bi bi-check-all me-2 text-primary"></i>

                    Etapas da Construção

                </h5>


                <?php if (empty($etapas)): ?>

                    <div class="alert alert-info text-center m-0">
                        Nenhuma etapa cadastrada para esta obra.
                    </div>

                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach ($etapas as $etapa): ?>

                            <?php
                            $progressoEtapa =
                                (float)($etapa['progresso'] ?? 0);

                            $progressoEtapa =
                                max(
                                    0,
                                    min(
                                        100,
                                        $progressoEtapa
                                    )
                                );

                            $concluida =
                                !empty($etapa['concluido']);
                            ?>

                            <div class="col-12 col-md-6">

                                <div
                                    class="card card-step p-3 h-100 border-0 shadow-sm bg-white"
                                >

                                    <div
                                        class="d-flex justify-content-between align-items-start mb-2 gap-2"
                                    >

                                        <span
                                            class="fw-bold text-dark fs-6"
                                        >

                                            #<?= (int)$etapa['ordem'] ?>.

                                            <?= htmlspecialchars(
                                                $etapa['nome_etapa'] ?? '',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>


                                        <?php if ($concluida): ?>

                                            <span
                                                class="badge bg-success-subtle text-success border border-success fw-bold text-nowrap"
                                            >

                                                <i class="bi bi-check-circle-fill me-1"></i>

                                                Concluída

                                            </span>

                                        <?php else: ?>

                                            <span
                                                class="badge bg-light text-muted border fw-bold text-nowrap"
                                            >

                                                <?= number_format(
                                                    $progressoEtapa,
                                                    0,
                                                    ',',
                                                    '.'
                                                ) ?>%

                                            </span>

                                        <?php endif; ?>

                                    </div>


                                    <div
                                        class="progress mt-auto"
                                        style="height: 8px;"
                                    >

                                        <div
                                            class="progress-bar <?= $concluida ? 'bg-success' : 'bg-primary' ?>"
                                            role="progressbar"
                                            style="width: <?= $progressoEtapa ?>%;"
                                            aria-valuenow="<?= $progressoEtapa ?>"
                                            aria-valuemin="0"
                                            aria-valuemax="100"
                                        ></div>

                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- ==========================================================
             FOTOS
        =========================================================== -->

        <div
            class="tab-pane fade"
            id="fotos-pane"
            role="tabpanel"
            aria-labelledby="fotos-tab"
            tabindex="0"
        >

            <div class="card border-0 shadow-sm p-3 p-md-4">

                <h5 class="fw-bold text-dark mb-3">

                    <i class="bi bi-images me-2 text-primary"></i>

                    Fotos de Acompanhamento

                </h5>


                <?php if (empty($fotos)): ?>

                    <div class="alert alert-info text-center m-0 py-4">
                        Nenhuma foto postada até o momento.
                    </div>

                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach ($fotos as $foto): ?>

                            <?php
                            $nomeFoto =
                                basename(
                                    $foto['caminho_foto'] ?? ''
                                );
                            ?>

                            <?php if ($nomeFoto !== ''): ?>

                                <div class="col-6 col-md-4 col-lg-3">

                                    <div
                                        class="card h-100 border-0 shadow-sm overflow-hidden"
                                    >

                                        <a
                                            href="/uploads/fotos/<?= rawurlencode($nomeFoto) ?>"
                                            target="_blank"
                                            rel="noopener noreferrer"
                                        >

                                            <img
                                                src="/uploads/fotos/<?= rawurlencode($nomeFoto) ?>"
                                                class="card-img-top"
                                                style="height: 180px; object-fit: cover;"
                                                alt="Foto da Obra"
                                                loading="lazy"
                                            >

                                        </a>


                                        <?php if (!empty($foto['descricao'])): ?>

                                            <div class="card-body p-2">

                                                <p class="card-text small text-muted m-0">

                                                    <?= htmlspecialchars(
                                                        $foto['descricao'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </p>

                                            </div>

                                        <?php endif; ?>

                                    </div>

                                </div>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>


        <!-- ==========================================================
             DOCUMENTOS
        =========================================================== -->

        <div
            class="tab-pane fade"
            id="docs-pane"
            role="tabpanel"
            aria-labelledby="docs-tab"
            tabindex="0"
        >

            <div class="card border-0 shadow-sm p-3 p-md-4">

                <h5 class="fw-bold text-dark mb-3">

                    <i class="bi bi-files me-2 text-primary"></i>

                    Arquivos e Comprovantes

                </h5>


                <?php if (!empty($obra['arquivo_projeto'])): ?>

                    <?php
                    $arquivoProjeto =
                        basename(
                            $obra['arquivo_projeto']
                        );
                    ?>

                    <div
                        class="p-3 mb-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2"
                    >

                        <div>

                            <strong class="d-block text-dark">

                                <i
                                    class="bi bi-file-earmark-pdf text-danger me-2 fs-5"
                                ></i>

                                Planta / Projeto Arquitetônico

                            </strong>

                            <small class="text-muted">
                                Projeto aprovado da obra
                            </small>

                        </div>


                        <a
                            href="/uploads/<?= rawurlencode($arquivoProjeto) ?>"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="btn btn-sm btn-primary fw-bold"
                        >

                            <i class="bi bi-download me-1"></i>

                            Baixar Projeto

                        </a>

                    </div>

                <?php endif; ?>


                <?php if (empty($documentos)): ?>

                    <div class="alert alert-info text-center m-0 py-3">

                        Nenhum comprovante ou nota fiscal anexada ainda.

                    </div>

                <?php else: ?>

                    <div class="list-group">

                        <?php foreach ($documentos as $documento): ?>

                            <?php
                            $nomeArquivo =
                                basename(
                                    $documento['caminho_arquivo'] ?? ''
                                );
                            ?>

                            <?php if ($nomeArquivo !== ''): ?>

                                <a
                                    href="/uploads/documentos/<?= rawurlencode($nomeArquivo) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="list-group-item list-group-item-action d-flex justify-content-between align-items-center"
                                >

                                    <div>

                                        <i
                                            class="bi bi-file-earmark-text text-primary me-2"
                                        ></i>

                                        <span class="fw-bold text-dark">

                                            <?= htmlspecialchars(
                                                $documento['nome_documento'] ?? 'Documento',
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </span>

                                    </div>

                                    <i
                                        class="bi bi-download text-muted"
                                    ></i>

                                </a>

                            <?php endif; ?>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
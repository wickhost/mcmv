<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$obraId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (!$obraId) {
    header('Location: /dashboard');
    exit;
}

$erro = '';
$sucesso = '';

$stmtObra = $pdo->prepare("
    SELECT
        o.*,
        u.nome AS nome_cliente,
        u.cpf AS cpf_cliente,
        u.telefone AS fone_cliente
    FROM obras o
    INNER JOIN usuarios u ON o.cliente_id = u.id
    WHERE o.id = ?
    LIMIT 1
");

$stmtObra->execute([$obraId]);
$obra = $stmtObra->fetch();

if (!$obra) {
    header('Location: /dashboard');
    exit;
}

function validarUploadImagem(array $arquivo): string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar a imagem.');
    }

    if (($arquivo['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException('A imagem não pode ultrapassar 10 MB.');
    }

    $permitidos = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'webp' => 'image/webp',
    ];

    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

    if (!isset($permitidos[$ext])) {
        throw new RuntimeException('Formato de imagem inválido. Use JPG, PNG ou WEBP.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    if ($mime !== $permitidos[$ext]) {
        throw new RuntimeException('O conteúdo da imagem não corresponde ao formato informado.');
    }

    return $ext;
}

function validarUploadDocumento(array $arquivo): string
{
    if (($arquivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar o documento.');
    }

    if (($arquivo['size'] ?? 0) > 20 * 1024 * 1024) {
        throw new RuntimeException('O documento não pode ultrapassar 20 MB.');
    }

    $permitidos = [
        'pdf',
        'jpg',
        'jpeg',
        'png',
        'webp',
        'doc',
        'docx',
        'xls',
        'xlsx',
        'zip',
    ];

    $ext = strtolower(pathinfo($arquivo['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $permitidos, true)) {
        throw new RuntimeException(
            'Formato de documento inválido.'
        );
    }

    return $ext;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao === 'atualizar_etapa') {
        $etapaId = filter_input(INPUT_POST, 'etapa_id', FILTER_VALIDATE_INT) ?: 0;
        $progresso = (float)str_replace(
            ',',
            '.',
            $_POST['progresso'] ?? '0'
        );

        $progresso = max(0, min(100, $progresso));

        $concluido = isset($_POST['concluido']) ? 1 : 0;

        if ($concluido) {
            $progresso = 100;
        }

        if (!$etapaId) {
            $erro = 'Etapa inválida.';
        } else {
            $stmtUpE = $pdo->prepare("
                UPDATE obra_etapas
                SET progresso = ?, concluido = ?
                WHERE id = ? AND obra_id = ?
            ");

            $stmtUpE->execute([
                $progresso,
                $concluido,
                $etapaId,
                $obraId
            ]);

            if ($stmtUpE->rowCount() === 0) {
                $stmtCheck = $pdo->prepare("
                    SELECT id
                    FROM obra_etapas
                    WHERE id = ? AND obra_id = ?
                    LIMIT 1
                ");
                $stmtCheck->execute([$etapaId, $obraId]);

                if (!$stmtCheck->fetchColumn()) {
                    $erro = 'Etapa não encontrada.';
                }
            }

            if ($erro === '') {
                $stmtSum = $pdo->prepare("
                    SELECT
                        COALESCE(
                            SUM((progresso * peso_percentual) / 100),
                            0
                        )
                    FROM obra_etapas
                    WHERE obra_id = ?
                ");

                $stmtSum->execute([$obraId]);

                $novoProgressoTotal = min(
                    100,
                    max(0, (float)$stmtSum->fetchColumn())
                );

                $stmtUpO = $pdo->prepare("
                    UPDATE obras
                    SET progresso_total = ?
                    WHERE id = ?
                ");

                $stmtUpO->execute([
                    $novoProgressoTotal,
                    $obraId
                ]);

                $sucesso = 'Etapa atualizada com sucesso!';
            }
        }
    }

    if ($acao === 'upload_foto') {
        $descricao = trim($_POST['descricao_foto'] ?? '');

        try {
            if (
                !isset($_FILES['foto']) ||
                ($_FILES['foto']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            ) {
                throw new RuntimeException('Selecione uma imagem.');
            }

            $ext = validarUploadImagem($_FILES['foto']);

            $diretorio = __DIR__ . '/../uploads/fotos/';

            if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
                throw new RuntimeException('Não foi possível criar o diretório de fotos.');
            }

            $nomeFoto = 'foto_' .
                $obraId . '_' .
                bin2hex(random_bytes(12)) .
                '.' .
                $ext;

            $destino = $diretorio . $nomeFoto;

            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                throw new RuntimeException('Erro ao salvar a foto.');
            }

            try {
                $stmtFoto = $pdo->prepare("
                    INSERT INTO obra_fotos (
                        obra_id,
                        caminho_foto,
                        descricao
                    )
                    VALUES (?, ?, ?)
                ");

                $stmtFoto->execute([
                    $obraId,
                    $nomeFoto,
                    $descricao !== '' ? $descricao : null
                ]);
            } catch (Throwable $e) {
                @unlink($destino);
                throw $e;
            }

            $sucesso = 'Foto adicionada à galeria!';
        } catch (Throwable $e) {
            $erro = $e->getMessage();
        }
    }

    if ($acao === 'upload_documento') {
        $nomeDocumento = trim($_POST['nome_documento'] ?? '');

        try {
            if ($nomeDocumento === '') {
                throw new RuntimeException(
                    'Informe o nome do documento.'
                );
            }

            if (
                !isset($_FILES['documento']) ||
                ($_FILES['documento']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
            ) {
                throw new RuntimeException(
                    'Selecione um arquivo.'
                );
            }

            $ext = validarUploadDocumento($_FILES['documento']);

            $diretorio = __DIR__ . '/../uploads/documentos/';

            if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
                throw new RuntimeException(
                    'Não foi possível criar o diretório de documentos.'
                );
            }

            $nomeArquivo = 'doc_' .
                $obraId . '_' .
                bin2hex(random_bytes(12)) .
                '.' .
                $ext;

            $destino = $diretorio . $nomeArquivo;

            if (!move_uploaded_file($_FILES['documento']['tmp_name'], $destino)) {
                throw new RuntimeException(
                    'Erro ao salvar o documento.'
                );
            }

            try {
                $stmtDoc = $pdo->prepare("
                    INSERT INTO obra_documentos (
                        obra_id,
                        nome_documento,
                        caminho_arquivo
                    )
                    VALUES (?, ?, ?)
                ");

                $stmtDoc->execute([
                    $obraId,
                    $nomeDocumento,
                    $nomeArquivo
                ]);
            } catch (Throwable $e) {
                @unlink($destino);
                throw $e;
            }

            $sucesso = 'Documento anexado com sucesso!';
        } catch (Throwable $e) {
            $erro = $e->getMessage();
        }
    }

    $stmtObra->execute([$obraId]);
    $obra = $stmtObra->fetch();
}

if (isset($_GET['del_foto'])) {
    $fotoId = filter_input(INPUT_GET, 'del_foto', FILTER_VALIDATE_INT) ?: 0;

    if ($fotoId) {
        $stmtF = $pdo->prepare("
            SELECT caminho_foto
            FROM obra_fotos
            WHERE id = ? AND obra_id = ?
            LIMIT 1
        ");

        $stmtF->execute([
            $fotoId,
            $obraId
        ]);

        $fotoDel = $stmtF->fetch();

        if ($fotoDel) {
            $arquivo = __DIR__ .
                '/../uploads/fotos/' .
                basename($fotoDel['caminho_foto']);

            if (is_file($arquivo)) {
                @unlink($arquivo);
            }

            $pdo->prepare("
                DELETE FROM obra_fotos
                WHERE id = ? AND obra_id = ?
            ")->execute([
                $fotoId,
                $obraId
            ]);
        }
    }

    header("Location: /gerenciar-obra?id={$obraId}&sucesso=foto_del");
    exit;
}

if (isset($_GET['del_doc'])) {
    $docId = filter_input(INPUT_GET, 'del_doc', FILTER_VALIDATE_INT) ?: 0;

    if ($docId) {
        $stmtD = $pdo->prepare("
            SELECT caminho_arquivo
            FROM obra_documentos
            WHERE id = ? AND obra_id = ?
            LIMIT 1
        ");

        $stmtD->execute([
            $docId,
            $obraId
        ]);

        $docDel = $stmtD->fetch();

        if ($docDel) {
            $arquivo = __DIR__ .
                '/../uploads/documentos/' .
                basename($docDel['caminho_arquivo']);

            if (is_file($arquivo)) {
                @unlink($arquivo);
            }

            $pdo->prepare("
                DELETE FROM obra_documentos
                WHERE id = ? AND obra_id = ?
            ")->execute([
                $docId,
                $obraId
            ]);
        }
    }

    header("Location: /gerenciar-obra?id={$obraId}&sucesso=doc_del");
    exit;
}

if (isset($_GET['sucesso'])) {
    if ($_GET['sucesso'] === 'foto_del') {
        $sucesso = 'Foto excluída com sucesso!';
    }

    if ($_GET['sucesso'] === 'doc_del') {
        $sucesso = 'Documento excluído com sucesso!';
    }
}

$stmtEtapas = $pdo->prepare("
    SELECT *
    FROM obra_etapas
    WHERE obra_id = ?
    ORDER BY ordem ASC
");

$stmtEtapas->execute([$obraId]);
$etapas = $stmtEtapas->fetchAll();

$stmtFotos = $pdo->prepare("
    SELECT *
    FROM obra_fotos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtFotos->execute([$obraId]);
$fotos = $stmtFotos->fetchAll();

$stmtDocs = $pdo->prepare("
    SELECT *
    FROM obra_documentos
    WHERE obra_id = ?
    ORDER BY id DESC
");

$stmtDocs->execute([$obraId]);
$documentos = $stmtDocs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0">
                <i class="bi bi-gear-fill me-2 text-primary"></i>
                Gestão da Obra #<?= (int)$obra['id'] ?>
            </h3>

            <p class="text-muted m-0 small">
                <i class="bi bi-geo-alt text-danger me-1"></i>
                <?= htmlspecialchars($obra['endereco_obra'], ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a
                href="/relatorio-obra?id=<?= (int)$obra['id'] ?>"
                target="_blank"
                rel="noopener noreferrer"
                class="btn btn-outline-danger btn-sm fw-bold"
            >
                <i class="bi bi-file-earmark-pdf me-1"></i>
                Relatório PDF
            </a>

            <a
                href="/obra-editar?id=<?= (int)$obra['id'] ?>"
                class="btn btn-outline-primary btn-sm fw-bold"
            >
                <i class="bi bi-pencil me-1"></i>
                Editar Dados
            </a>

            <a
                href="/dashboard"
                class="btn btn-outline-secondary btn-sm fw-bold"
            >
                <i class="bi bi-arrow-left me-1"></i>
                Voltar
            </a>
        </div>
    </div>

    <?php if ($sucesso): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100 text-center justify-content-center">
                <small class="text-muted fw-bold d-block mb-1">
                    EVOLUÇÃO GLOBAL DA OBRA
                </small>

                <h2 class="fw-bold text-success m-0">
                    <?= number_format((float)$obra['progresso_total'], 1, ',', '.') ?>%
                </h2>

                <div class="progress mt-2" style="height: 12px;">
                    <div
                        class="progress-bar bg-success"
                        style="width: <?= min(100, max(0, (float)$obra['progresso_total'])) ?>%;"
                    ></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="row g-2 text-center text-sm-start">
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">
                            PROPRIETÁRIO
                        </small>

                        <span class="fw-bold text-dark d-block text-truncate">
                            <?= htmlspecialchars($obra['nome_cliente'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">
                            VALOR TOTAL
                        </small>

                        <span class="fw-bold text-dark d-block">
                            R$ <?= number_format((float)$obra['valor_total'], 2, ',', '.') ?>
                        </span>
                    </div>

                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">
                            CONSTRUÇÃO
                        </small>

                        <span class="fw-bold text-success d-block">
                            R$ <?= number_format((float)$obra['sobra_construcao'], 2, ',', '.') ?>
                        </span>
                    </div>

                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">
                            TERRENO
                        </small>

                        <span class="fw-bold text-dark d-block">
                            R$ <?= number_format((float)$obra['valor_terreno'], 2, ',', '.') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <ul
        class="nav nav-tabs nav-fill mb-4 shadow-sm bg-white rounded p-1"
        id="obraTabs"
        role="tablist"
    >
        <li class="nav-item" role="presentation">
            <button
                class="nav-link active fw-bold py-2"
                id="etapas-tab"
                data-bs-toggle="tab"
                data-bs-target="#etapas-content"
                type="button"
            >
                <i class="bi bi-list-check me-1"></i>
                Cronograma / Etapas
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button
                class="nav-link fw-bold py-2"
                id="fotos-tab"
                data-bs-toggle="tab"
                data-bs-target="#fotos-content"
                type="button"
            >
                <i class="bi bi-camera me-1"></i>
                Galeria de Fotos (<?= count($fotos) ?>)
            </button>
        </li>

        <li class="nav-item" role="presentation">
            <button
                class="nav-link fw-bold py-2"
                id="docs-tab"
                data-bs-toggle="tab"
                data-bs-target="#docs-content"
                type="button"
            >
                <i class="bi bi-paperclip me-1"></i>
                Comprovantes / Docs (<?= count($documentos) ?>)
            </button>
        </li>
    </ul>

    <div class="tab-content" id="obraTabsContent">
        <div class="tab-pane fade show active" id="etapas-content">
            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-list-ol me-2 text-primary"></i>
                    Medição de Etapas Físicas
                </h5>

                <?php if (empty($etapas)): ?>
                    <div class="alert alert-info text-center m-0">
                        Nenhuma etapa cadastrada para esta obra.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;">#</th>
                                    <th>Etapa / Serviço</th>
                                    <th style="width: 100px;" class="text-center">Peso (%)</th>
                                    <th style="width: 130px;" class="text-end">Valor (R$)</th>
                                    <th style="width: 220px;">Progresso Executado</th>
                                    <th style="width: 120px;" class="text-center">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($etapas as $etapa): ?>
                                    <?php
                                    $progresso = min(
                                        100,
                                        max(0, (float)$etapa['progresso'])
                                    );
                                    ?>

                                    <tr class="<?= $etapa['concluido'] ? 'table-success bg-opacity-25' : '' ?>">
                                        <td class="fw-bold text-muted">
                                            <?= (int)$etapa['ordem'] ?>
                                        </td>

                                        <td class="fw-bold text-dark">
                                            <?= htmlspecialchars($etapa['nome_etapa'], ENT_QUOTES, 'UTF-8') ?>

                                            <?php if ($etapa['concluido']): ?>
                                                <span class="badge bg-success ms-1">
                                                    <i class="bi bi-check-lg me-1"></i>
                                                    Concluída
                                                </span>
                                            <?php endif; ?>
                                        </td>

                                        <td class="text-center">
                                            <span class="badge bg-light text-dark border">
                                                <?= number_format((float)$etapa['peso_percentual'], 2, ',', '.') ?>%
                                            </span>
                                        </td>

                                        <td class="text-end fw-bold">
                                            R$ <?= number_format((float)$etapa['valor_etapa'], 2, ',', '.') ?>
                                        </td>

                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div
                                                    class="progress flex-grow-1"
                                                    style="height: 8px;"
                                                >
                                                    <div
                                                        class="progress-bar <?= $etapa['concluido'] ? 'bg-success' : 'bg-primary' ?>"
                                                        style="width: <?= $progresso ?>%;"
                                                    ></div>
                                                </div>

                                                <span class="small fw-bold me-1">
                                                    <?= number_format($progresso, 0, ',', '.') ?>%
                                                </span>
                                            </div>
                                        </td>

                                        <td class="text-center">
                                            <button
                                                class="btn btn-sm btn-outline-primary fw-bold"
                                                data-bs-toggle="modal"
                                                data-bs-target="#modalEtapa<?= (int)$etapa['id'] ?>"
                                                type="button"
                                            >
                                                <i class="bi bi-pencil-square me-1"></i>
                                                Medir
                                            </button>
                                        </td>
                                    </tr>

                                    <div
                                        class="modal fade"
                                        id="modalEtapa<?= (int)$etapa['id'] ?>"
                                        tabindex="-1"
                                        aria-hidden="true"
                                    >
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">
                                                        Atualizar Medição:
                                                        Etapa #<?= (int)$etapa['ordem'] ?>
                                                    </h5>

                                                    <button
                                                        type="button"
                                                        class="btn-close"
                                                        data-bs-dismiss="modal"
                                                    ></button>
                                                </div>

                                                <form method="POST" action="">
                                                    <input
                                                        type="hidden"
                                                        name="acao"
                                                        value="atualizar_etapa"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="etapa_id"
                                                        value="<?= (int)$etapa['id'] ?>"
                                                    >

                                                    <div class="modal-body">
                                                        <p class="fw-bold text-primary mb-3">
                                                            <?= htmlspecialchars($etapa['nome_etapa'], ENT_QUOTES, 'UTF-8') ?>
                                                        </p>

                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">
                                                                Porcentagem Executada (0 a 100%)
                                                            </label>

                                                            <div class="input-group">
                                                                <input
                                                                    type="number"
                                                                    step="0.01"
                                                                    min="0"
                                                                    max="100"
                                                                    name="progresso"
                                                                    class="form-control form-control-lg"
                                                                    value="<?= htmlspecialchars((string)$progresso, ENT_QUOTES, 'UTF-8') ?>"
                                                                    required
                                                                >

                                                                <span class="input-group-text fw-bold">
                                                                    %
                                                                </span>
                                                            </div>
                                                        </div>

                                                        <div class="form-check form-switch mb-2">
                                                            <input
                                                                class="form-check-input"
                                                                type="checkbox"
                                                                name="concluido"
                                                                value="1"
                                                                id="chkConcluido<?= (int)$etapa['id'] ?>"
                                                                <?= $etapa['concluido'] ? 'checked' : '' ?>
                                                            >

                                                            <label
                                                                class="form-check-label fw-bold"
                                                                for="chkConcluido<?= (int)$etapa['id'] ?>"
                                                            >
                                                                Marcar Etapa como 100% Concluída
                                                            </label>
                                                        </div>
                                                    </div>

                                                    <div class="modal-footer">
                                                        <button
                                                            type="button"
                                                            class="btn btn-secondary"
                                                            data-bs-dismiss="modal"
                                                        >
                                                            Cancelar
                                                        </button>

                                                        <button
                                                            type="submit"
                                                            class="btn btn-primary fw-bold"
                                                        >
                                                            Salvar Medição
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="fotos-content">
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-cloud-upload me-2 text-primary"></i>
                    Adicionar Foto da Evolução
                </h5>

                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data"
                    class="row g-3 align-items-end"
                >
                    <input type="hidden" name="acao" value="upload_foto">

                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            Selecione a Imagem *
                        </label>

                        <input
                            type="file"
                            name="foto"
                            class="form-control"
                            accept=".jpg,.jpeg,.png,.webp"
                            required
                        >
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            Legenda / Observação
                        </label>

                        <input
                            type="text"
                            name="descricao_foto"
                            class="form-control"
                            maxlength="255"
                            placeholder="Ex: Concretagem das sapatas concluída"
                        >
                    </div>

                    <div class="col-md-2">
                        <button
                            type="submit"
                            class="btn btn-primary fw-bold w-100"
                        >
                            <i class="bi bi-upload me-1"></i>
                            Enviar
                        </button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-images me-2 text-primary"></i>
                    Fotos Anexadas
                </h5>

                <?php if (empty($fotos)): ?>
                    <div class="alert alert-info text-center m-0">
                        Nenhuma foto adicionada à galeria até o momento.
                    </div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($fotos as $foto): ?>
                            <?php $nomeFoto = basename($foto['caminho_foto']); ?>

                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm overflow-hidden">
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

                                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                                        <small class="text-muted d-block mb-1 text-truncate">
                                            <?= htmlspecialchars($foto['descricao'] ?? 'Sem legenda', ENT_QUOTES, 'UTF-8') ?>
                                        </small>

                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= date('d/m/Y', strtotime($foto['criado_em'])) ?>
                                            </small>

                                            <a
                                                href="/gerenciar-obra?id=<?= $obraId ?>&del_foto=<?= (int)$foto['id'] ?>"
                                                class="btn btn-sm btn-outline-danger p-1 line-height-1"
                                                onclick="return confirm('Deseja realmente excluir esta foto?')"
                                                title="Excluir Foto"
                                            >
                                                <i class="bi bi-trash"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="tab-pane fade" id="docs-content">
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-file-earmark-plus me-2 text-primary"></i>
                    Anexar Novo Comprovante / Nota Fiscal
                </h5>

                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data"
                    class="row g-3 align-items-end"
                >
                    <input type="hidden" name="acao" value="upload_documento">

                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            Nome / Descrição do Documento *
                        </label>

                        <input
                            type="text"
                            name="nome_documento"
                            class="form-control"
                            maxlength="255"
                            placeholder="Ex: Nota Fiscal de Cimento - Etapa 2"
                            required
                        >
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fw-bold">
                            Arquivo *
                        </label>

                        <input
                            type="file"
                            name="documento"
                            class="form-control"
                            accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx,.zip"
                            required
                        >

                        <small class="text-muted">
                            Máximo: 20 MB.
                        </small>
                    </div>

                    <div class="col-md-2">
                        <button
                            type="submit"
                            class="btn btn-primary fw-bold w-100"
                        >
                            <i class="bi bi-paperclip me-1"></i>
                            Anexar
                        </button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3">
                    <i class="bi bi-files me-2 text-primary"></i>
                    Documentos Anexados
                </h5>

                <?php if (empty($documentos)): ?>
                    <div class="alert alert-info text-center m-0">
                        Nenhum documento ou comprovante anexado.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th>Data de Envio</th>
                                    <th class="text-end" style="width: 150px;">
                                        Ações
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($documentos as $documento): ?>
                                    <?php $nomeArquivo = basename($documento['caminho_arquivo']); ?>

                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <i class="bi bi-file-earmark-text text-primary me-2 fs-5"></i>
                                            <?= htmlspecialchars($documento['nome_documento'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>

                                        <td class="small text-muted">
                                            <?= date('d/m/Y H:i', strtotime($documento['criado_em'])) ?>
                                        </td>

                                        <td class="text-end">
                                            <a
                                                href="/uploads/documentos/<?= rawurlencode($nomeArquivo) ?>"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="btn btn-sm btn-outline-primary me-1"
                                                title="Visualizar / Download"
                                            >
                                                <i class="bi bi-download"></i>
                                            </a>

                                            <a
                                                href="/gerenciar-obra?id=<?= $obraId ?>&del_doc=<?= (int)$documento['id'] ?>"
                                                class="btn btn-sm btn-outline-danger"
                                                onclick="return confirm('Deseja remover este documento?')"
                                                title="Excluir Documento"
                                            >
                                                <i class="bi bi-trash"></i>
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
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
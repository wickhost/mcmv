<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$obra_id = $_GET['id'] ?? null;
if (!$obra_id) {
    header('Location: /dashboard');
    exit;
}

$erro = '';
$sucesso = '';

// Buscar Informações da Obra e Cliente
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

// Processar Ações (Atualização de Etapa, Fotos e Documentos)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // 1. Atualizar Progresso / Status da Etapa
    if ($acao === 'atualizar_etapa') {
        $etapa_id  = (int)$_POST['etapa_id'];
        $progresso = (float)str_replace(',', '.', $_POST['progresso'] ?? '0');
        $concluido = isset($_POST['concluido']) ? 1 : ($progresso >= 100 ? 1 : 0);

        if ($progresso > 100) $progresso = 100;
        if ($progresso < 0) $progresso = 0;

        $stmtUpE = $pdo->prepare("UPDATE obra_etapas SET progresso = ?, concluido = ? WHERE id = ? AND obra_id = ?");
        $stmtUpE->execute([$progresso, $concluido, $etapa_id, $obra_id]);

        // Recalcular Progresso Total da Obra
        $stmtSum = $pdo->prepare("SELECT SUM((progresso * peso_percentual) / 100) FROM obra_etapas WHERE obra_id = ?");
        $stmtSum->execute([$obra_id]);
        $novoProgressoTotal = (float)$stmtSum->fetchColumn();

        $stmtUpO = $pdo->prepare("UPDATE obras SET progresso_total = ? WHERE id = ?");
        $stmtUpO->execute([$novoProgressoTotal, $obra_id]);

        $sucesso = 'Etapa atualizada com sucesso!';
    }

    // 2. Upload de Foto da Obra
    if ($acao === 'upload_foto') {
        $descricao = trim($_POST['descricao_foto'] ?? '');
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            $extsValidas = ['jpg', 'jpeg', 'png', 'webp'];

            if (in_array($ext, $extsValidas)) {
                $nomeFoto = 'foto_' . $obra_id . '_' . time() . '_' . uniqid() . '.' . $ext;
                $diretorio = __DIR__ . '/../uploads/fotos/';

                if (!is_dir($diretorio)) {
                    mkdir($diretorio, 0777, true);
                }

                if (move_uploaded_file($_FILES['foto']['tmp_name'], $diretorio . $nomeFoto)) {
                    $stmtFoto = $pdo->prepare("INSERT INTO obra_fotos (obra_id, caminho_foto, descricao) VALUES (?, ?, ?)");
                    $stmtFoto->execute([$obra_id, $nomeFoto, $descricao ?: null]);
                    $sucesso = 'Foto adicionada à galeria!';
                } else {
                    $erro = 'Erro ao mover a foto para o servidor.';
                }
            } else {
                $erro = 'Formato de imagem inválido. Use JPG, PNG ou WEBP.';
            }
        }
    }

    // 3. Upload de Comprovante / Documento
    if ($acao === 'upload_documento') {
        $nomeDoc = trim($_POST['nome_documento'] ?? '');
        if (isset($_FILES['documento']) && $_FILES['documento']['error'] === UPLOAD_ERR_OK && !empty($nomeDoc)) {
            $ext = strtolower(pathinfo($_FILES['documento']['name'], PATHINFO_EXTENSION));
            $nomeArquivo = 'doc_' . $obra_id . '_' . time() . '_' . uniqid() . '.' . $ext;
            $diretorio = __DIR__ . '/../uploads/documentos/';

            if (!is_dir($diretorio)) {
                mkdir($diretorio, 0777, true);
            }

            if (move_uploaded_file($_FILES['documento']['tmp_name'], $diretorio . $nomeArquivo)) {
                $stmtDoc = $pdo->prepare("INSERT INTO obra_documentos (obra_id, nome_documento, caminho_arquivo) VALUES (?, ?, ?)");
                $stmtDoc->execute([$obra_id, $nomeDoc, $nomeArquivo]);
                $sucesso = 'Documento anexado com sucesso!';
            } else {
                $erro = 'Erro ao mover o documento para o servidor.';
            }
        } else {
            $erro = 'Por favor, selecione um arquivo e informe o nome do documento.';
        }
    }

    // Recarregar dados da obra
    $stmtObra->execute([$obra_id]);
    $obra = $stmtObra->fetch();
}

// Deletar Foto
if (isset($_GET['del_foto'])) {
    $foto_id = (int)$_GET['del_foto'];
    $stmtF = $pdo->prepare("SELECT caminho_foto FROM obra_fotos WHERE id = ? AND obra_id = ?");
    $stmtF->execute([$foto_id, $obra_id]);
    $fotoDel = $stmtF->fetch();

    if ($fotoDel) {
        @unlink(__DIR__ . '/../uploads/fotos/' . $fotoDel['caminho_foto']);
        $pdo->prepare("DELETE FROM obra_fotos WHERE id = ?")->execute([$foto_id]);
        header("Location: /gerenciar-obra?id={$obra_id}&sucesso=foto_del");
        exit;
    }
}

// Deletar Documento
if (isset($_GET['del_doc'])) {
    $doc_id = (int)$_GET['del_doc'];
    $stmtD = $pdo->prepare("SELECT caminho_arquivo FROM obra_documentos WHERE id = ? AND obra_id = ?");
    $stmtD->execute([$doc_id, $obra_id]);
    $docDel = $stmtD->fetch();

    if ($docDel) {
        @unlink(__DIR__ . '/../uploads/documentos/' . $docDel['caminho_arquivo']);
        $pdo->prepare("DELETE FROM obra_documentos WHERE id = ?")->execute([$doc_id]);
        header("Location: /gerenciar-obra?id={$obra_id}&sucesso=doc_del");
        exit;
    }
}

// Buscar Etapas da Obra
$stmtEtapas = $pdo->prepare("SELECT * FROM obra_etapas WHERE obra_id = ? ORDER BY ordem ASC");
$stmtEtapas->execute([$obra_id]);
$etapas = $stmtEtapas->fetchAll();

// Buscar Fotos da Galeria
$stmtFotos = $pdo->prepare("SELECT * FROM obra_fotos WHERE obra_id = ? ORDER BY id DESC");
$stmtFotos->execute([$obra_id]);
$fotos = $stmtFotos->fetchAll();

// Buscar Documentos Anexados
$stmtDocs = $pdo->prepare("SELECT * FROM obra_documentos WHERE obra_id = ? ORDER BY id DESC");
$stmtDocs->execute([$obra_id]);
$documentos = $stmtDocs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <!-- Cabeçalho Principal -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0"><i class="bi bi-gear-fill me-2 text-primary"></i>Gestão da Obra #<?= $obra['id'] ?></h3>
            <p class="text-muted m-0 small"><i class="bi bi-geo-alt text-danger me-1"></i><?= htmlspecialchars($obra['endereco_obra']) ?></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/relatorio-obra?id=<?= $obra['id'] ?>" target="_blank" class="btn btn-outline-danger btn-sm fw-bold">
                <i class="bi bi-file-earmark-pdf me-1"></i> Relatório PDF
            </a>
            <a href="/obra-editar?id=<?= $obra['id'] ?>" class="btn btn-outline-primary btn-sm fw-bold">
                <i class="bi bi-pencil me-1"></i> Editar Dados
            </a>
            <a href="/dashboard" class="btn btn-outline-secondary btn-sm fw-bold">
                <i class="bi bi-arrow-left me-1"></i> Voltar
            </a>
        </div>
    </div>

    <?php if ($sucesso): ?><div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($sucesso) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>
    <?php if ($erro): ?><div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($erro) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php endif; ?>

    <!-- Resumo do Progresso e Dados Físico-Financeiros -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-4">
            <div class="card border-0 shadow-sm p-3 h-100 text-center justify-content-center">
                <small class="text-muted fw-bold d-block mb-1">EVOLUÇÃO GLOBAL DA OBRA</small>
                <h2 class="fw-bold text-success m-0"><?= number_format((float)$obra['progresso_total'], 1, ',', '.') ?>%</h2>
                <div class="progress mt-2" style="height: 12px;">
                    <div class="progress-bar bg-success" style="width: <?= (float)$obra['progresso_total'] ?>%;"></div>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-8">
            <div class="card border-0 shadow-sm p-3 h-100">
                <div class="row g-2 text-center text-sm-start">
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">PROPRIETÁRIO</small>
                        <span class="fw-bold text-dark d-block text-truncate"><?= htmlspecialchars($obra['nome_cliente']) ?></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">FINANCIAMENTO</small>
                        <span class="fw-bold text-dark d-block">R$ <?= number_format($obra['valor_total'], 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">CONSTRUÇÃO</small>
                        <span class="fw-bold text-success d-block">R$ <?= number_format($obra['sobra_construcao'], 2, ',', '.') ?></span>
                    </div>
                    <div class="col-6 col-sm-3">
                        <small class="text-muted d-block fw-bold">TERRENO</small>
                        <span class="fw-bold text-dark d-block">R$ <?= number_format($obra['valor_terreno'], 2, ',', '.') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Navegação por Abas (Etapas, Galeria, Documentos) -->
    <ul class="nav nav-tabs nav-fill mb-4 shadow-sm bg-white rounded p-1" id="obraTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold py-2" id="etapas-tab" data-bs-toggle="tab" data-bs-target="#etapas-content" type="button"><i class="bi bi-list-check me-1"></i> Cronograma / Etapas</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="fotos-tab" data-bs-toggle="tab" data-bs-target="#fotos-content" type="button"><i class="bi bi-camera me-1"></i> Galeria de Fotos (<?= count($fotos) ?>)</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold py-2" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs-content" type="button"><i class="bi bi-paperclip me-1"></i> Comprovantes / Docs (<?= count($documentos) ?>)</button>
        </li>
    </ul>

    <div class="tab-content" id="obraTabsContent">
        <!-- ABA 1: Cronograma / Etapas -->
        <div class="tab-pane fade show active" id="etapas-content">
            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-list-ol me-2 text-primary"></i>Medição de Etapas Físicas</h5>
                
                <?php if (empty($etapas)): ?>
                    <div class="alert alert-info text-center m-0">Nenhuma etapa cadastrada para esta obra.</div>
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
                                <?php foreach ($etapas as $e): ?>
                                    <tr class="<?= $e['concluido'] ? 'table-success bg-opacity-25' : '' ?>">
                                        <td class="fw-bold text-muted"><?= $e['ordem'] ?></td>
                                        <td class="fw-bold text-dark">
                                            <?= htmlspecialchars($e['nome_etapa']) ?>
                                            <?php if ($e['concluido']): ?>
                                                <span class="badge bg-success ms-1"><i class="bi bi-check-lg me-1"></i>Concluída</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><span class="badge bg-light text-dark border"><?= number_format($e['peso_percentual'], 2, ',', '.') ?>%</span></td>
                                        <td class="text-end fw-bold">R$ <?= number_format($e['valor_etapa'], 2, ',', '.') ?></td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <div class="progress flex-grow-1" style="height: 8px;">
                                                    <div class="progress-bar <?= $e['concluido'] ? 'bg-success' : 'bg-primary' ?>" style="width: <?= (float)$e['progresso'] ?>%;"></div>
                                                </div>
                                                <span class="small fw-bold me-1"><?= number_format((float)$e['progresso'], 0) ?>%</span>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-primary fw-bold" data-bs-toggle="modal" data-bs-target="#modalEtapa<?= $e['id'] ?>">
                                                <i class="bi bi-pencil-square me-1"></i> Medir
                                            </button>
                                        </td>
                                    </tr>

                                    <!-- Modal de Medição da Etapa -->
                                    <div class="modal fade" id="modalEtapa<?= $e['id'] ?>" tabindex="-1">
                                        <div class="modal-dialog modal-dialog-centered">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title fw-bold">Atualizar Medição: Etapa #<?= $e['ordem'] ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" action="">
                                                    <input type="hidden" name="acao" value="atualizar_etapa">
                                                    <input type="hidden" name="etapa_id" value="<?= $e['id'] ?>">
                                                    <div class="modal-body">
                                                        <p class="fw-bold text-primary mb-3"><?= htmlspecialchars($e['nome_etapa']) ?></p>
                                                        
                                                        <div class="mb-3">
                                                            <label class="form-label fw-bold">Porcentagem Executada (0 a 100%)</label>
                                                            <div class="input-group">
                                                                <input type="number" step="0.01" min="0" max="100" name="progresso" class="form-control form-control-lg" value="<?= (float)$e['progresso'] ?>" required>
                                                                <span class="input-group-text fw-bold">%</span>
                                                            </div>
                                                        </div>

                                                        <div class="form-check form-switch mb-2">
                                                            <input class="form-check-input" type="checkbox" name="concluido" id="chkConcluido<?= $e['id'] ?>" <?= $e['concluido'] ? 'checked' : '' ?>>
                                                            <label class="form-check-input-label fw-bold" for="chkConcluido<?= $e['id'] ?>">Marcar Etapa como 100% Concluída</label>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                                        <button type="submit" class="btn btn-primary fw-bold">Salvar Medição</button>
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

        <!-- ABA 2: Galeria de Fotos -->
        <div class="tab-pane fade" id="fotos-content">
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-cloud-upload me-2 text-primary"></i>Adicionar Foto da Evolução</h5>
                <form method="POST" action="" enctype="multipart/form-data" class="row g-3 align-items-end">
                    <input type="hidden" name="acao" value="upload_foto">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Selecione a Imagem *</label>
                        <input type="file" name="foto" class="form-control" accept="image/*" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Legenda / Observação</label>
                        <input type="text" name="descricao_foto" class="form-control" placeholder="Ex: Concretagem das sapatas concluída">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary fw-bold w-100"><i class="bi bi-upload me-1"></i> Enviar</button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-images me-2 text-primary"></i>Fotos Anexadas</h5>
                <?php if (empty($fotos)): ?>
                    <div class="alert alert-info text-center m-0">Nenhuma foto adicionada à galeria até o momento.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($fotos as $f): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm overflow-hidden">
                                    <a href="/uploads/fotos/<?= $f['caminho_foto'] ?>" target="_blank">
                                        <img src="/uploads/fotos/<?= $f['caminho_foto'] ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="Foto da Obra">
                                    </a>
                                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                                        <small class="text-muted d-block mb-1 text-truncate"><?= htmlspecialchars($f['descricao'] ?? 'Sem legenda') ?></small>
                                        <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                            <small class="text-muted"><i class="bi bi-calendar3 me-1"></i><?= date('d/m/Y', strtotime($f['criado_em'])) ?></small>
                                            <a href="/gerenciar-obra?id=<?= $obra_id ?>&del_foto=<?= $f['id'] ?>" class="btn btn-sm btn-outline-danger p-1 line-height-1" onclick="return confirm('Deseja realmente excluir esta foto?')" title="Excluir Foto">
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

        <!-- ABA 3: Documentos e Comprovantes -->
        <div class="tab-pane fade" id="docs-content">
            <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-plus me-2 text-primary"></i>Anexar Novo Comprovante / Nota Fiscal</h5>
                <form method="POST" action="" enctype="multipart/form-data" class="row g-3 align-items-end">
                    <input type="hidden" name="acao" value="upload_documento">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Nome / Descrição do Documento *</label>
                        <input type="text" name="nome_documento" class="form-control" placeholder="Ex: Nota Fiscal de Cimento - Etapa 2" required>
                    </div>
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Arquivo (PDF, Imagem, Word, Zip) *</label>
                        <input type="file" name="documento" class="form-control" required>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary fw-bold w-100"><i class="bi bi-paperclip me-1"></i> Anexar</button>
                    </div>
                </form>
            </div>

            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-files me-2 text-primary"></i>Documentos Anexados</h5>
                <?php if (empty($documentos)): ?>
                    <div class="alert alert-info text-center m-0">Nenhum documento ou comprovante anexado.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table align-middle table-hover">
                            <thead class="table-light">
                                <tr>
                                    <th>Documento</th>
                                    <th>Data de Envio</th>
                                    <th class="text-end" style="width: 150px;">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentos as $doc): ?>
                                    <tr>
                                        <td class="fw-bold text-dark">
                                            <i class="bi bi-file-earmark-text text-primary me-2 fs-5"></i>
                                            <?= htmlspecialchars($doc['nome_documento']) ?>
                                        </td>
                                        <td class="small text-muted"><?= date('d/m/Y H:i', strtotime($doc['criado_em'])) ?></td>
                                        <td class="text-end">
                                            <a href="/uploads/documentos/<?= $doc['caminho_arquivo'] ?>" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Visualizar / Download">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <a href="/gerenciar-obra?id=<?= $obra_id ?>&del_doc=<?= $doc['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Deseja remover este documento?')" title="Excluir Documento">
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
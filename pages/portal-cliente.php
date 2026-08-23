<?php
require_once __DIR__ . '/../includes/auth.php';

// Apenas clientes ou admins logados acessam esta visão
$cliente_id = $_SESSION['usuario_id'];

// Buscar a obra vinculada ao cliente logado
$stmtObra = $pdo->prepare("
    SELECT o.*, u.nome AS nome_cliente, u.telefone AS fone_cliente 
    FROM obras o 
    JOIN usuarios u ON o.cliente_id = u.id 
    WHERE o.cliente_id = ?
    ORDER BY o.id DESC LIMIT 1
");
$stmtObra->execute([$cliente_id]);
$obra = $stmtObra->fetch();

if (!$obra) {
    require_once __DIR__ . '/../includes/header.php';
    echo '
    <div class="container my-5 text-center py-5">
        <i class="bi bi-house-exclamation text-warning display-1"></i>
        <h3 class="fw-bold mt-3">Nenhuma obra vinculada</h3>
        <p class="text-muted">No momento não encontramos nenhuma obra vinculada ao seu cadastro.</p>
    </div>';
    require_once __DIR__ . '/../includes/footer.php';
    exit;
}

$obra_id = $obra['id'];

// Buscar Etapas da Obra
$stmtEtapas = $pdo->prepare("SELECT * FROM obra_etapas WHERE obra_id = ? ORDER BY ordem ASC");
$stmtEtapas->execute([$obra_id]);
$etapas = $stmtEtapas->fetchAll();

// Buscar Fotos da Obra
$stmtFotos = $pdo->prepare("SELECT * FROM obra_fotos WHERE obra_id = ? ORDER BY id DESC");
$stmtFotos->execute([$obra_id]);
$fotos = $stmtFotos->fetchAll();

// Buscar Documentos
$stmtDocs = $pdo->prepare("SELECT * FROM obra_documentos WHERE obra_id = ? ORDER BY id DESC");
$stmtDocs->execute([$obra_id]);
$documentos = $stmtDocs->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <!-- Boas-vindas -->
    <div class="card border-0 shadow-sm p-3 p-md-4 mb-4 bg-primary text-white rounded-3">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div>
                <span class="badge bg-white text-primary fw-bold mb-2">Acompanhamento em Tempo Real</span>
                <h3 class="fw-bold m-0">Olá, <?= htmlspecialchars($obra['nome_cliente']) ?>!</h3>
                <p class="m-0 small opacity-75"><i class="bi bi-geo-alt-fill me-1"></i><?= htmlspecialchars($obra['endereco_obra']) ?></p>
            </div>
            <div class="text-md-end">
                <small class="d-block opacity-75">Progresso Geral da Obra</small>
                <h1 class="fw-bold m-0 display-5"><?= number_format((float)$obra['progresso_total'], 1, ',', '.') ?>%</h1>
            </div>
        </div>
        <div class="progress mt-3 bg-white bg-opacity-25" style="height: 12px;">
            <div class="progress-bar bg-warning" style="width: <?= (float)$obra['progresso_total'] ?>%;"></div>
        </div>
    </div>

    <!-- Navegação Mobile Friendly -->
    <ul class="nav nav-pills nav-fill mb-4 bg-white p-2 rounded shadow-sm gap-2" id="portalTabs" role="tablist">
        <li class="nav-item">
            <button class="nav-link active fw-bold py-2" id="crono-tab" data-bs-toggle="tab" data-bs-target="#crono-pane"><i class="bi bi-list-task me-1"></i> Cronograma</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold py-2" id="fotos-tab" data-bs-toggle="tab" data-bs-target="#fotos-pane"><i class="bi bi-camera me-1"></i> Fotos (<?= count($fotos) ?>)</button>
        </li>
        <li class="nav-item">
            <button class="nav-link fw-bold py-2" id="docs-tab" data-bs-toggle="tab" data-bs-target="#docs-pane"><i class="bi bi-paperclip me-1"></i> Documentos</button>
        </li>
    </ul>

    <div class="tab-content" id="portalTabsContent">
        <!-- Cronograma de Etapas -->
        <div class="tab-pane fade show active" id="crono-pane">
            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-check-all me-2 text-primary"></i>Etapas da Construção</h5>
                <div class="row g-3">
                    <?php foreach ($etapas as $e): ?>
                        <div class="col-12 col-md-6">
                            <div class="card card-step p-3 h-100 border-0 shadow-sm bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <span class="fw-bold text-dark fs-6">#<?= $e['ordem'] ?>. <?= htmlspecialchars($e['nome_etapa']) ?></span>
                                    <?php if ($e['concluido']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success fw-bold"><i class="bi bi-check-circle-fill me-1"></i>Concluída</span>
                                    <?php else: ?>
                                        <span class="badge bg-light text-muted border fw-bold"><?= number_format((float)$e['progresso'], 0) ?>%</span>
                                    <?php endif; ?>
                                </div>
                                <div class="progress mt-auto" style="height: 8px;">
                                    <div class="progress-bar <?= $e['concluido'] ? 'bg-success' : 'bg-primary' ?>" style="width: <?= (float)$e['progresso'] ?>%;"></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Galeria de Fotos -->
        <div class="tab-pane fade" id="fotos-pane">
            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-images me-2 text-primary"></i>Fotos de Acompanhamento</h5>
                <?php if (empty($fotos)): ?>
                    <div class="alert alert-info text-center m-0 py-4">Nenhuma foto postada até o momento.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($fotos as $f): ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100 border-0 shadow-sm overflow-hidden">
                                    <a href="/uploads/fotos/<?= $f['caminho_foto'] ?>" target="_blank">
                                        <img src="/uploads/fotos/<?= $f['caminho_foto'] ?>" class="card-img-top" style="height: 180px; object-fit: cover;" alt="Foto da Obra">
                                    </a>
                                    <?php if ($f['descricao']): ?>
                                        <div class="card-body p-2">
                                            <p class="card-text small text-muted m-0"><?= htmlspecialchars($f['descricao']) ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Documentos e Projetos -->
        <div class="tab-pane fade" id="docs-pane">
            <div class="card border-0 shadow-sm p-3 p-md-4">
                <h5 class="fw-bold text-dark mb-3"><i class="bi bi-files me-2 text-primary"></i>Arquivos e Comprovantes</h5>
                
                <?php if ($obra['arquivo_projeto']): ?>
                    <div class="p-3 mb-3 bg-light rounded border d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <strong class="d-block text-dark"><i class="bi bi-file-earmark-pdf text-danger me-2 fs-5"></i>Planta / Projeto Arquitetônico</strong>
                            <small class="text-muted">Projeto aprovado da obra</small>
                        </div>
                        <a href="/uploads/<?= $obra['arquivo_projeto'] ?>" target="_blank" class="btn btn-sm btn-primary fw-bold">
                            <i class="bi bi-download me-1"></i> Baixar Projeto
                        </a>
                    </div>
                <?php endif; ?>

                <?php if (empty($documentos)): ?>
                    <div class="alert alert-info text-center m-0 py-3">Nenhum comprovante ou nota fiscal anexada ainda.</div>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($documentos as $doc): ?>
                            <a href="/uploads/documentos/<?= $doc['caminho_arquivo'] ?>" target="_blank" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                <div>
                                    <i class="bi bi-file-earmark-text text-primary me-2"></i>
                                    <span class="fw-bold text-dark"><?= htmlspecialchars($doc['nome_documento']) ?></span>
                                </div>
                                <i class="bi bi-download text-muted"></i>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
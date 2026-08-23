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

// Buscar Obra
$stmt = $pdo->prepare("SELECT * FROM obras WHERE id = ?");
$stmt->execute([$obra_id]);
$obra = $stmt->fetch();

if (!$obra) {
    header('Location: /dashboard');
    exit;
}

// Buscar Clientes
$clientes = $pdo->query("SELECT id, nome, cpf FROM usuarios WHERE tipo = 'cliente' ORDER BY nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id    = $_POST['cliente_id'] ?? null;
    $endereco_obra = trim($_POST['endereco_obra'] ?? '');

    $valor_total    = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_total'] ?? '0'));
    $valor_terreno  = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_terreno'] ?? '0'));
    $valor_subsidio = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_subsidio'] ?? '0'));
    $valor_entrada  = (float)str_replace(',', '.', str_replace('.', '', $_POST['valor_entrada'] ?? '0'));

    if (!$cliente_id || empty($endereco_obra) || $valor_total <= 0) {
        $erro = 'Por favor, preencha o cliente, endereço e o valor total da obra.';
    } else {
        $sobra_construcao = $valor_total - ($valor_terreno + $valor_subsidio + $valor_entrada);
        $arquivo_projeto = $obra['arquivo_projeto'];

        // Upload de novo arquivo de projeto
        if (isset($_FILES['arquivo_projeto']) && $_FILES['arquivo_projeto']['error'] === UPLOAD_ERR_OK) {
            $extensao = strtolower(pathinfo($_FILES['arquivo_projeto']['name'], PATHINFO_EXTENSION));
            $nomeArquivo = 'projeto_' . time() . '_' . uniqid() . '.' . $extensao;
            $destino = __DIR__ . '/../uploads/' . $nomeArquivo;

            if (!is_dir(__DIR__ . '/../uploads/')) {
                mkdir(__DIR__ . '/../uploads/', 0777, true);
            }

            if (move_uploaded_file($_FILES['arquivo_projeto']['tmp_name'], $destino)) {
                if ($arquivo_projeto && file_exists(__DIR__ . '/../uploads/' . $arquivo_projeto)) {
                    @unlink(__DIR__ . '/../uploads/' . $arquivo_projeto);
                }
                $arquivo_projeto = $nomeArquivo;
            }
        }

        try {
            $stmtUp = $pdo->prepare("
                UPDATE obras 
                SET cliente_id = ?, endereco_obra = ?, valor_total = ?, valor_terreno = ?, valor_subsidio = ?, valor_entrada = ?, sobra_construcao = ?, arquivo_projeto = ?
                WHERE id = ?
            ");
            $stmtUp->execute([
                $cliente_id,
                $endereco_obra,
                $valor_total,
                $valor_terreno,
                $valor_subsidio,
                $valor_entrada,
                $sobra_construcao,
                $arquivo_projeto,
                $obra_id
            ]);

            // Atualiza o valor monetário de cada etapa existente com base na nova sobra
            $stmtEtapas = $pdo->prepare("SELECT id, peso_percentual FROM obra_etapas WHERE obra_id = ?");
            $stmtEtapas->execute([$obra_id]);
            $etapas = $stmtEtapas->fetchAll();

            $stmtUpEtapa = $pdo->prepare("UPDATE obra_etapas SET valor_etapa = ? WHERE id = ?");
            foreach ($etapas as $etapa) {
                $novo_valor_etapa = ($sobra_construcao * $etapa['peso_percentual']) / 100;
                $stmtUpEtapa->execute([$novo_valor_etapa, $etapa['id']]);
            }

            header("Location: /gerenciar-obra?id={$obra_id}");
            exit;
        } catch (PDOException $e) {
            $erro = 'Erro ao atualizar obra: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Obra #<?= $obra['id'] ?></h3>
            <p class="text-muted m-0 small">Atualize as informações gerais e valores da obra</p>
        </div>
        <a href="/gerenciar-obra?id=<?= $obra['id'] ?>" class="btn btn-outline-secondary btn-sm fw-bold w-100 w-md-auto">
            <i class="bi bi-arrow-left me-1"></i> Voltar à Gestão
        </a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Proprietário & Local</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Cliente Proprietário *</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>" <?= $c['id'] == $obra['cliente_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($c['nome']) ?> (CPF: <?= htmlspecialchars($c['cpf']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Endereço Completo da Obra *</label>
                    <input type="text" name="endereco_obra" class="form-control" value="<?= htmlspecialchars($obra['endereco_obra']) ?>" required>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-cash-stack me-2 text-primary"></i>Valores Físico-Financeiros</h5>
            <div class="row g-3">
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold">Valor Total do Financiamento *</label>
                    <input type="text" name="valor_total" id="valor_total" class="form-control mask-money" inputmode="numeric" value="<?= number_format($obra['valor_total'], 2, ',', '.') ?>" required>
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold">Valor do Terreno</label>
                    <input type="text" name="valor_terreno" id="valor_terreno" class="form-control mask-money" inputmode="numeric" value="<?= number_format($obra['valor_terreno'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold">Subsídio</label>
                    <input type="text" name="valor_subsidio" id="valor_subsidio" class="form-control mask-money" inputmode="numeric" value="<?= number_format($obra['valor_subsidio'], 2, ',', '.') ?>">
                </div>
                <div class="col-md-6 col-lg-3">
                    <label class="form-label fw-bold">Entrada (Recurso Próprio)</label>
                    <input type="text" name="valor_entrada" id="valor_entrada" class="form-control mask-money" inputmode="numeric" value="<?= number_format($obra['valor_entrada'], 2, ',', '.') ?>">
                </div>
                <div class="col-12 mt-3">
                    <div class="p-3 bg-light rounded border">
                        <span class="fw-bold text-muted">Sobra Destinada à Construção (Calculada automaticamente):</span>
                        <h4 class="fw-bold text-success m-0 mt-1" id="sobra_display">R$ <?= number_format($obra['sobra_construcao'], 2, ',', '.') ?></h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-pdf me-2 text-primary"></i>Projeto / Planta</h5>
            <div class="row g-3">
                <div class="col-12">
                    <?php if ($obra['arquivo_projeto']): ?>
                        <div class="mb-2">
                            <span class="badge bg-light text-dark border p-2 me-2">
                                <i class="bi bi-file-earmark me-1 text-primary"></i> <?= htmlspecialchars($obra['arquivo_projeto']) ?>
                            </span>
                            <a href="/uploads/<?= $obra['arquivo_projeto'] ?>" target="_blank" class="btn btn-sm btn-outline-primary fw-bold">
                                <i class="bi bi-download me-1"></i> Baixar Arquivo Atual
                            </a>
                        </div>
                    <?php endif; ?>
                    <input type="file" name="arquivo_projeto" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    <small class="text-muted">Selecione um novo arquivo apenas se quiser substituir o projeto atual.</small>
                </div>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary fw-bold px-4 py-2 w-100 w-md-auto">
                <i class="bi bi-check-lg me-1"></i> Salvar Alterações
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function parseMoeda(valor) {
        if (!valor) return 0;
        return parseFloat(valor.replace(/\./g, '').replace(',', '.')) || 0;
    }

    function formatarMoeda(valor) {
        return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function calcularSobra() {
        const vTotal = parseMoeda(document.getElementById('valor_total').value);
        const vTerreno = parseMoeda(document.getElementById('valor_terreno').value);
        const vSubsidio = parseMoeda(document.getElementById('valor_subsidio').value);
        const vEntrada = parseMoeda(document.getElementById('valor_entrada').value);

        const sobra = vTotal - (vTerreno + vSubsidio + vEntrada);
        document.getElementById('sobra_display').innerText = formatarMoeda(sobra);
    }

    ['valor_total', 'valor_terreno', 'valor_subsidio', 'valor_entrada'].forEach(id => {
        document.getElementById(id).addEventListener('input', calcularSobra);
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
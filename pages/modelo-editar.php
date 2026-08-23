<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$id = $_GET['id'] ?? null;
if (!$id) {
    header('Location: /modelos');
    exit;
}

$erro = '';

// Buscar Modelo Existente
$stmtMod = $pdo->prepare("SELECT * FROM modelos_casas WHERE id = ?");
$stmtMod->execute([$id]);
$modelo = $stmtMod->fetch();

if (!$modelo) {
    header('Location: /modelos');
    exit;
}

// Processar Atualização
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');
    $etapasNomes = $_POST['etapa_nome'] ?? [];
    $etapasPesos = $_POST['etapa_peso'] ?? [];
    $etapasValores = $_POST['etapa_valor'] ?? [];

    if (empty($nome)) {
        $erro = 'O nome do modelo é obrigatório.';
    } else {
        try {
            $pdo->beginTransaction();

            // Atualizar modelo
            $stmtUp = $pdo->prepare("UPDATE modelos_casas SET nome = ?, descricao = ? WHERE id = ?");
            $stmtUp->execute([$nome, $descricao ?: null, $id]);

            // Remover etapas antigas e reinserir atualizadas
            $stmtDel = $pdo->prepare("DELETE FROM modelos_etapas WHERE modelo_id = ?");
            $stmtDel->execute([$id]);

            if (!empty($etapasNomes)) {
                $stmtEtapa = $pdo->prepare("INSERT INTO modelos_etapas (modelo_id, ordem, nome_etapa, peso_percentual, valor_estimado) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($etapasNomes as $index => $nomeEtapa) {
                    $nomeClean = trim($nomeEtapa);
                    if ($nomeClean !== '') {
                        $ordem = $index + 1;
                        $peso = str_replace(',', '.', str_replace('.', '', $etapasPesos[$index] ?? '0'));
                        $valor = str_replace(',', '.', str_replace('.', '', $etapasValores[$index] ?? '0'));

                        $stmtEtapa->execute([
                            $id,
                            $ordem,
                            $nomeClean,
                            (float)$peso,
                            (float)$valor
                        ]);
                    }
                }
            }

            $pdo->commit();
            header('Location: /modelos?sucesso=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = 'Erro ao atualizar modelo: ' . $e->getMessage();
        }
    }
}

// Buscar Etapas Existentes do Modelo
$stmtEtapas = $pdo->prepare("SELECT * FROM modelos_etapas WHERE modelo_id = ? ORDER BY ordem ASC");
$stmtEtapas->execute([$id]);
$etapasExistentes = $stmtEtapas->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0"><i class="bi bi-pencil-square me-2 text-primary"></i>Editar Modelo de Casa</h3>
            <p class="text-muted m-0 small">Altere a estrutura de etapas ou os valores de referência do modelo</p>
        </div>
        <a href="/modelos" class="btn btn-outline-secondary btn-sm fw-bold w-100 w-md-auto">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Modelos
        </a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-1 text-primary"></i>Informações do Modelo</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nome do Modelo *</label>
                    <input type="text" name="nome" class="form-control" value="<?= htmlspecialchars($modelo['nome']) ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Descrição</label>
                    <input type="text" name="descricao" class="form-control" value="<?= htmlspecialchars($modelo['descricao'] ?? '') ?>">
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">
                <div>
                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ol me-1 text-primary"></i>Etapas do Cronograma</h5>
                    <small class="text-muted">A soma dos pesos deve totalizar 100%.</small>
                </div>
                <button type="button" class="btn btn-sm btn-success fw-bold w-100 w-sm-auto" id="btn-add-etapa">
                    <i class="bi bi-plus-circle me-1"></i> Adicionar Etapa
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" id="tabela-etapas">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Serviços / Nome da Etapa</th>
                            <th style="width: 150px;">Incidência (%)</th>
                            <th style="width: 180px;">Valor Ref. (R$)</th>
                            <th style="width: 60px;" class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="container-etapas">
                        <!-- Gerado via JS com dados do PHP -->
                    </tbody>
                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">Soma das Incidências:</td>
                            <td id="total-peso-display" class="text-primary">0,00%</td>
                            <td colspan="2"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary fw-bold px-4 py-2 w-100 w-md-auto">
                <i class="bi bi-check-lg me-1"></i> Salvar Alterações no Modelo
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let indexEtapa = 0;

    function calcularSomaPesos() {
        let soma = 0;
        document.querySelectorAll('.mask-percent').forEach(function(input) {
            let val = input.value.replace(/\./g, '').replace(',', '.');
            soma += parseFloat(val) || 0;
        });
        const totalDisplay = document.getElementById('total-peso-display');
        totalDisplay.innerText = soma.toFixed(2).replace('.', ',') + '%';
        
        if (Math.abs(soma - 100) < 0.01) {
            totalDisplay.className = 'text-success fw-bold';
        } else {
            totalDisplay.className = 'text-danger fw-bold';
        }
    }

    function adicionarLinhaEtapa(nome = '', peso = '', valor = '0,00') {
        indexEtapa++;
        const container = document.getElementById('container-etapas');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="fw-bold text-muted index-num">${indexEtapa}</td>
            <td>
                <input type="text" name="etapa_nome[]" class="form-control" placeholder="Nome da Etapa" value="${nome}" required>
            </td>
            <td>
                <input type="text" name="etapa_peso[]" class="form-control mask-percent input-peso" inputmode="numeric" placeholder="0,00" value="${peso}">
            </td>
            <td>
                <input type="text" name="etapa_valor[]" class="form-control mask-money" inputmode="numeric" placeholder="0,00" value="${valor}">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-etapa">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        container.appendChild(tr);
        if (typeof aplicarMascaras === 'function') {
            aplicarMascaras();
        }

        tr.querySelector('.input-peso').addEventListener('input', calcularSomaPesos);
        reindexar();
        calcularSomaPesos();
    }

    function reindexar() {
        const linhas = document.querySelectorAll('#container-etapas tr');
        linhas.forEach((tr, i) => {
            tr.querySelector('.index-num').innerText = i + 1;
        });
    }

    document.getElementById('btn-add-etapa').addEventListener('click', function() {
        adicionarLinhaEtapa();
    });

    document.getElementById('container-etapas').addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove-etapa')) {
            const tr = e.target.closest('tr');
            tr.remove();
            reindexar();
            calcularSomaPesos();
        }
    });

    // Carregar Etapas Existentes do Banco
    <?php foreach ($etapasExistentes as $e): ?>
        adicionarLinhaEtapa(
            "<?= addslashes($e['nome_etapa']) ?>",
            "<?= number_format($e['peso_percentual'], 2, ',', '.') ?>",
            "<?= number_format($e['valor_estimado'], 2, ',', '.') ?>"
        );
    <?php endforeach; ?>
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
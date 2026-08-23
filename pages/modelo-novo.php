<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$erro = '';

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

            $stmtMod = $pdo->prepare("INSERT INTO modelos_casas (nome, descricao) VALUES (?, ?)");
            $stmtMod->execute([$nome, $descricao ?: null]);
            $modeloId = $pdo->lastInsertId();

            if (!empty($etapasNomes)) {
                $stmtEtapa = $pdo->prepare("INSERT INTO modelos_etapas (modelo_id, ordem, nome_etapa, peso_percentual, valor_estimado) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($etapasNomes as $index => $nomeEtapa) {
                    $nomeClean = trim($nomeEtapa);
                    if ($nomeClean !== '') {
                        $ordem = $index + 1;
                        
                        $pesoRaw = $etapasPesos[$index] ?? '0';
                        $valorRaw = $etapasValores[$index] ?? '0';

                        $peso = (float)str_replace(',', '.', str_replace('.', '', $pesoRaw));
                        $valor = (float)str_replace(',', '.', str_replace('.', '', $valorRaw));

                        $stmtEtapa->execute([
                            $modeloId,
                            $ordem,
                            $nomeClean,
                            $peso,
                            $valor
                        ]);
                    }
                }
            }

            $pdo->commit();
            header('Location: /modelos?sucesso=1');
            exit;
        } catch (PDOException $e) {
            $pdo->rollBack();
            $erro = 'Erro ao salvar modelo: ' . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0"><i class="bi bi-plus-square me-2 text-primary"></i>Novo Modelo de Casa</h3>
            <p class="text-muted m-0 small">Cadastre o modelo padrão PFUI Caixa com suas 20 etapas e pesos percentuais</p>
        </div>
        <a href="/modelos" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Modelos
        </a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Informações do Modelo -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-info-circle me-1 text-primary"></i>Informações do Modelo</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nome do Modelo *</label>
                    <input type="text" name="nome" class="form-control" value="Modelo Padrão PFUI Caixa Econômica" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Descrição</label>
                    <input type="text" name="descricao" class="form-control" value="Cronograma físico-financeiro de 20 etapas padrão Caixa">
                </div>
            </div>
        </div>

        <!-- Tabela de Etapas -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3">
                <div>
                    <h5 class="fw-bold text-dark m-0"><i class="bi bi-list-ol me-1 text-primary"></i>Etapas do Cronograma (PFUI Caixa)</h5>
                    <small class="text-muted">A soma dos pesos deve totalizar 100%.</small>
                </div>
                <button type="button" class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center flex-shrink-0" id="btn-add-etapa">
                    <i class="bi bi-plus-circle me-1"></i> Adicionar Etapa
                </button>
            </div>

            <div class="table-responsive">
                <table class="table align-middle" id="tabela-etapas">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 50px;">#</th>
                            <th>Serviços / Nome da Etapa</th>
                            <th style="width: 140px;">Incidência (%)</th>
                            <th style="width: 160px;">Valor Ref. (R$)</th>
                            <th style="width: 60px;" class="text-center">Ação</th>
                        </tr>
                    </thead>
                    <tbody id="container-etapas">
                        <!-- Gerado dinamicamente via JS -->
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

        <!-- Botão Salvar -->
        <div class="text-end mb-5">
            <button type="submit" class="btn btn-primary fw-bold px-4 py-2">
                <i class="bi bi-check-lg me-1"></i> Salvar Modelo Completo
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function parseFloatMoeda(valorStr) {
        if (!valorStr) return 0;
        let v = valorStr.toString().replace(/\./g, '').replace(',', '.');
        return parseFloat(v) || 0;
    }

    function calcularSomaPesos() {
        let soma = 0;
        document.querySelectorAll('.input-peso').forEach(function(input) {
            soma += parseFloatMoeda(input.value);
        });
        
        const totalDisplay = document.getElementById('total-peso-display');
        totalDisplay.innerText = soma.toFixed(2).replace('.', ',') + '%';
        
        if (Math.abs(soma - 100) < 0.05) {
            totalDisplay.className = 'text-success fw-bold';
        } else {
            totalDisplay.className = 'text-danger fw-bold';
        }
    }

    function reindexar() {
        const linhas = document.querySelectorAll('#container-etapas tr');
        linhas.forEach((tr, i) => {
            const indexTd = tr.querySelector('.index-num');
            if (indexTd) indexTd.innerText = i + 1;
        });
    }

    function adicionarLinhaEtapa(nome = '', peso = '', valor = '0,00') {
        const container = document.getElementById('container-etapas');
        const tr = document.createElement('tr');
        
        tr.innerHTML = `
            <td class="fw-bold text-muted index-num">0</td>
            <td>
                <input type="text" name="etapa_nome[]" class="form-control" placeholder="Nome da Etapa" value="${nome}" required>
            </td>
            <td>
                <input type="text" name="etapa_peso[]" class="form-control mask-percent input-peso" inputmode="numeric" placeholder="0,00" value="${peso}">
            </td>
            <td>
                <input type="text" name="etapa_valor[]" class="form-control mask-money" inputmode="numeric" placeholder="0,00" value="${valor}">
            </td>
            <td class="text-center align-middle">
                <button type="button" class="btn btn-sm btn-outline-danger btn-remove-etapa" title="Remover Etapa">
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

    // Clique no botão Adicionar Etapa
    document.getElementById('btn-add-etapa').addEventListener('click', function(e) {
        e.preventDefault();
        adicionarLinhaEtapa();
    });

    // Delegar remoção de linha
    document.getElementById('container-etapas').addEventListener('click', function(e) {
        const btnRemove = e.target.closest('.btn-remove-etapa');
        if (btnRemove) {
            e.preventDefault();
            const tr = btnRemove.closest('tr');
            if (tr) {
                tr.remove();
                reindexar();
                calcularSomaPesos();
            }
        }
    });

    // 20 Etapas Padrão PFUI Caixa Econômica Federal
    const etapasCaixa = [
        { nome: "Barracão + lig. provisórias (água/luz) + projetos/aprovs.", peso: "3,92" },
        { nome: "Infraestrutura (estacas, brocas, baldrames, sapatas)", peso: "6,12" },
        { nome: "Supraestrutura (Vigas, pilares, cintas, escadas)", peso: "12,65" },
        { nome: "Paredes e Painéis", peso: "10,20" },
        { nome: "Esquadrias", peso: "6,45" },
        { nome: "Vidros e Plásticos", peso: "2,37" },
        { nome: "Coberturas (estrutura e telhas)", peso: "7,76" },
        { nome: "Impermeabilizações", peso: "1,88" },
        { nome: "Revestimentos Internos", peso: "8,82" },
        { nome: "Forros", peso: "0,00" },
        { nome: "Revestimentos Externos", peso: "4,90" },
        { nome: "Pinturas", peso: "6,12" },
        { nome: "Pisos", peso: "9,06" },
        { nome: "Acabamentos (soleiras, rodapés, peitoril etc.)", peso: "1,22" },
        { nome: "Instalações Elétricas e Telefônicas", peso: "4,49" },
        { nome: "Instalações Hidráulicas", peso: "3,92" },
        { nome: "Instalações: Esgoto e Águas Pluviais", peso: "4,00" },
        { nome: "Louças e Metais", peso: "4,73" },
        { nome: "Complementos (limpeza final e calafete)", peso: "1,39" },
        { nome: "Outros (discriminar em Serviços Adicionais)", peso: "0,00" }
    ];

    etapasCaixa.forEach(function(item) {
        adicionarLinhaEtapa(item.nome, item.peso, '0,00');
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
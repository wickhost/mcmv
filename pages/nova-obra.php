<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$erro = '';

// Buscar Clientes e Modelos para os selects
$clientes = $pdo->query("SELECT id, nome, cpf FROM usuarios WHERE tipo = 'cliente' ORDER BY nome ASC")->fetchAll();
$modelos  = $pdo->query("SELECT id, nome FROM modelos_casas ORDER BY nome ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cliente_id     = $_POST['cliente_id'] ?? null;
    $modelo_id      = $_POST['modelo_id'] ?? null;
    $endereco_obra  = trim($_POST['endereco_obra'] ?? '');
    
    function limpaMoeda($valor) {
        if (empty($valor)) return 0.0;
        $v = str_replace('.', '', $valor);
        $v = str_replace(',', '.', $v);
        return (float)$v;
    }

    $valor_total        = limpaMoeda($_POST['valor_total'] ?? '0');
    $valor_financiamento= limpaMoeda($_POST['valor_financiamento'] ?? '0');
    $valor_terreno      = limpaMoeda($_POST['valor_terreno'] ?? '0');
    $valor_subsidio     = limpaMoeda($_POST['valor_subsidio'] ?? '0');
    $valor_entrada      = limpaMoeda($_POST['valor_entrada'] ?? '0');

    if (!$cliente_id || empty($endereco_obra) || $valor_total <= 0) {
        $erro = 'Por favor, preencha o cliente, endereço e o valor do imóvel.';
    } else {
        // Sobra para a Construção = Financiamento + Subsídio + Entrada em Dinheiro - Terreno
        $sobra_construcao = ($valor_financiamento + $valor_subsidio + $valor_entrada) - $valor_terreno;

        if ($sobra_construcao <= 0) {
            $erro = 'O valor disponível para a construção é zero ou negativo. Verifique os valores.';
        } else {
            // Upload de arquivo de projeto (opcional)
            $arquivo_projeto = null;
            if (isset($_FILES['arquivo_projeto']) && $_FILES['arquivo_projeto']['error'] === UPLOAD_ERR_OK) {
                $extensao = strtolower(pathinfo($_FILES['arquivo_projeto']['name'], PATHINFO_EXTENSION));
                $nomeArquivo = 'projeto_' . time() . '_' . uniqid() . '.' . $extensao;
                $destino = __DIR__ . '/../uploads/' . $nomeArquivo;
                
                if (!is_dir(__DIR__ . '/../uploads/')) {
                    mkdir(__DIR__ . '/../uploads/', 0777, true);
                }
                
                if (move_uploaded_filename($_FILES['arquivo_projeto']['tmp_name'], $destino)) {
                    $arquivo_projeto = $nomeArquivo;
                }
            }

            try {
                $pdo->beginTransaction();

                $stmtObra = $pdo->prepare("
                    INSERT INTO obras (cliente_id, modelo_id, endereco_obra, valor_total, valor_terreno, valor_subsidio, valor_entrada, sobra_construcao, arquivo_projeto) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmtObra->execute([
                    $cliente_id, 
                    $modelo_id ?: null, 
                    $endereco_obra, 
                    $valor_total, 
                    $valor_terreno, 
                    $valor_subsidio, 
                    $valor_entrada, 
                    $sobra_construcao,
                    $arquivo_projeto
                ]);
                $obra_id = $pdo->lastInsertId();

                if ($modelo_id) {
                    $stmtEtapasModelo = $pdo->prepare("SELECT * FROM modelos_etapas WHERE modelo_id = ? ORDER BY ordem ASC");
                    $stmtEtapasModelo->execute([$modelo_id]);
                    $etapasModelo = $stmtEtapasModelo->fetchAll();

                    $stmtInsertObraEtapa = $pdo->prepare("
                        INSERT INTO obra_etapas (obra_id, ordem, nome_etapa, peso_percentual, valor_etapa, progresso, concluido) 
                        VALUES (?, ?, ?, ?, ?, 0.00, 0)
                    ");

                    foreach ($etapasModelo as $etapa) {
                        $valor_etapa = ($sobra_construcao * $etapa['peso_percentual']) / 100;
                        
                        $stmtInsertObraEtapa->execute([
                            $obra_id,
                            $etapa['ordem'],
                            $etapa['nome_etapa'],
                            $etapa['peso_percentual'],
                            $valor_etapa
                        ]);
                    }
                }

                $pdo->commit();
                header("Location: /gerenciar-obra?id={$obra_id}");
                exit;
            } catch (PDOException $e) {
                $pdo->rollBack();
                $erro = 'Erro ao cadastrar obra: ' . $e->getMessage();
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <div class="page-header">
        <div class="page-header-content">
            <h3 class="page-title">
                <i class="bi bi-building-add text-primary me-2"></i>Nova Obra
            </h3>
            <p class="page-subtitle">Cadastre uma nova obra e vincule ao proprietário</p>
        </div>
        <a href="/dashboard" class="btn btn-outline-secondary btn-action-top">
            <i class="bi bi-arrow-left me-1"></i> Voltar ao Dashboard
        </a>
    </div>

    <?php if ($erro): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= htmlspecialchars($erro) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-person-badge me-2 text-primary"></i>Proprietário & Modelo</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Cliente Proprietário *</label>
                    <select name="cliente_id" class="form-select" required>
                        <option value="">Selecione um cliente...</option>
                        <?php foreach ($clientes as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['nome']) ?> (CPF: <?= htmlspecialchars($c['cpf']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Modelo de Cronograma (Opcional)</label>
                    <select name="modelo_id" class="form-select">
                        <option value="">Sem modelo (cadastrar etapas manualmente)</option>
                        <?php foreach ($modelos as $m): ?>
                            <option value="<?= $m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label fw-bold">Endereço Completo da Obra *</label>
                    <input type="text" name="endereco_obra" class="form-control" placeholder="Rua, Número, Bairro, Cidade" required>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-cash-stack me-2 text-primary"></i>Valores Físico-Financeiros</h5>
            <div class="row g-3">
                <div class="col-md-6 col-lg-4">
                    <label class="form-label fw-bold">Valor Total do Imóvel *</label>
                    <input type="text" name="valor_total" id="valor_total" class="form-control mask-money" value="0,00" required>
                </div>
                <div class="col-md-6 col-lg-4">
                    <label class="form-label fw-bold">Financiamento (Caixa - 80%)</label>
                    <input type="text" name="valor_financiamento" id="valor_financiamento" class="form-control mask-money" value="0,00">
                </div>
                <div class="col-md-6 col-lg-4">
                    <label class="form-label fw-bold">Subsídio MCMV</label>
                    <input type="text" name="valor_subsidio" id="valor_subsidio" class="form-control mask-money" value="0,00">
                </div>
                <div class="col-md-6 col-lg-6">
                    <label class="form-label fw-bold">Entrada em Dinheiro (Recurso Próprio)</label>
                    <input type="text" name="valor_entrada" id="valor_entrada" class="form-control mask-money" value="0,00">
                    <small class="text-muted">Calculada descontando o subsídio (ou ajustada manualmente)</small>
                </div>
                <div class="col-md-6 col-lg-6">
                    <label class="form-label fw-bold">Valor do Terreno</label>
                    <input type="text" name="valor_terreno" id="valor_terreno" class="form-control mask-money" value="0,00">
                </div>
                
                <div class="col-12 mt-3">
                    <div class="p-3 bg-light rounded border">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="text-muted">Total de Recursos da Obra:</span>
                            <span class="fw-bold text-dark" id="total_recursos_display">R$ 0,00</span>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold text-dark fs-5">Sobra Destinada à Construção:</span>
                            <h3 class="fw-bold text-success m-0" id="sobra_display">R$ 0,00</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3"><i class="bi bi-file-earmark-pdf me-2 text-primary"></i>Projeto / Planta (Opcional)</h5>
            <div class="row g-3">
                <div class="col-12">
                    <input type="file" name="arquivo_projeto" class="form-control" accept=".pdf,.png,.jpg,.jpeg">
                    <small class="text-muted">Anexe a planta ou projeto arquitetônico da obra em PDF ou imagem.</small>
                </div>
            </div>
        </div>			
        <div class="col-12 mt-4 text-end">
            <button type="submit" class="btn btn-primary fw-bold px-4 w-100 w-sm-auto">
                <i class="bi bi-check-lg me-1"></i> Criar e Gerenciar Obra
            </button>
        </div>
    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function parseMoeda(valor) {
        if (!valor) return 0;
        const apenasNumeros = valor.toString().replace(/\D/g, '');
        if (!apenasNumeros) return 0;
        return parseFloat(apenasNumeros) / 100;
    }

    function formatarMoeda(valor) {
        return valor.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
    }

    function formatarInput(valorFloat) {
        return valorFloat.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    const inputTotal = document.getElementById('valor_total');
    const inputFinanc = document.getElementById('valor_financiamento');
    const inputSubsidio = document.getElementById('valor_subsidio');
    const inputEntrada = document.getElementById('valor_entrada');
    const inputTerreno = document.getElementById('valor_terreno');

    let entradaEditadaManualmente = false;

    function recalcularEntradaESobra() {
        const vTotal = parseMoeda(inputTotal.value);
        const vFinanc = vTotal > 0 ? (vTotal * 0.80) : parseMoeda(inputFinanc.value);
        const vSubsidio = parseMoeda(inputSubsidio.value);

        if (vTotal > 0) {
            inputFinanc.value = formatarInput(vFinanc);
            
            // Entrada total exigida = Total do Imóvel - Financiamento (geralmente 20%)
            const entradaBrutaNecessaria = vTotal - vFinanc;
            
            // Entrada em dinheiro = Entrada Bruta - Subsídio
            if (!entradaEditadaManualmente) {
                const entradaEmDinheiro = Math.max(0, entradaBrutaNecessaria - vSubsidio);
                inputEntrada.value = formatarInput(entradaEmDinheiro);
            }
        }

        calcularValoresFinal();
    }

    function calcularValoresFinal() {
        const vFinanc = parseMoeda(inputFinanc.value);
        const vSubsidio = parseMoeda(inputSubsidio.value);
        const vEntrada = parseMoeda(inputEntrada.value);
        const vTerreno = parseMoeda(inputTerreno.value);

        // Recursos Totais
        const recursosTotais = vFinanc + vSubsidio + vEntrada;
        document.getElementById('total_recursos_display').innerText = formatarMoeda(recursosTotais);

        // Sobra para construção = Recursos Totais - Terreno
        const sobra = recursosTotais - vTerreno;
        document.getElementById('sobra_display').innerText = formatarMoeda(sobra > 0 ? sobra : 0);
    }

    inputTotal.addEventListener('input', recalcularEntradaESobra);
    inputSubsidio.addEventListener('input', recalcularEntradaESobra);

    inputEntrada.addEventListener('focus', () => { entradaEditadaManualmente = true; });
    inputEntrada.addEventListener('input', calcularValoresFinal);
    inputTerreno.addEventListener('input', calcularValoresFinal);
    inputFinanc.addEventListener('input', calcularValoresFinal);

    calcularValoresFinal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
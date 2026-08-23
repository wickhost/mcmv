<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $descricao = trim($_POST['descricao'] ?? '');

    $etapasNomes = isset($_POST['etapa_nome']) && is_array($_POST['etapa_nome'])
        ? $_POST['etapa_nome']
        : [];

    $etapasPesos = isset($_POST['etapa_peso']) && is_array($_POST['etapa_peso'])
        ? $_POST['etapa_peso']
        : [];

    $etapasValores = isset($_POST['etapa_valor']) && is_array($_POST['etapa_valor'])
        ? $_POST['etapa_valor']
        : [];

    if ($nome === '') {
        $erro = 'O nome do modelo é obrigatório.';
    } else {
        $etapasValidas = [];
        $somaPesos = 0.0;

        foreach ($etapasNomes as $index => $nomeEtapa) {
            $nomeClean = trim((string) $nomeEtapa);

            if ($nomeClean === '') {
                continue;
            }

            $pesoRaw = isset($etapasPesos[$index])
                ? trim((string) $etapasPesos[$index])
                : '0';

            $valorRaw = isset($etapasValores[$index])
                ? trim((string) $etapasValores[$index])
                : '0';

            /*
             * Aceita:
             * 10
             * 10,50
             * 1.234,56
             *
             * Como os campos utilizam padrão brasileiro, o ponto é
             * considerado separador de milhar e a vírgula decimal.
             */
            $pesoNormalizado = str_replace('.', '', $pesoRaw);
            $pesoNormalizado = str_replace(',', '.', $pesoNormalizado);

            $valorNormalizado = str_replace('.', '', $valorRaw);
            $valorNormalizado = str_replace(',', '.', $valorNormalizado);

            $peso = is_numeric($pesoNormalizado)
                ? (float) $pesoNormalizado
                : 0.0;

            $valor = is_numeric($valorNormalizado)
                ? (float) $valorNormalizado
                : 0.0;

            if ($peso < 0) {
                $erro = 'Os pesos das etapas não podem ser negativos.';
                break;
            }

            if ($valor < 0) {
                $erro = 'Os valores estimados das etapas não podem ser negativos.';
                break;
            }

            $somaPesos += $peso;

            $etapasValidas[] = [
                'nome'  => $nomeClean,
                'peso'  => $peso,
                'valor' => $valor
            ];
        }

        if ($erro === '' && empty($etapasValidas)) {
            $erro = 'Adicione pelo menos uma etapa ao modelo.';
        }

        if ($erro === '' && abs($somaPesos - 100.0) > 0.05) {
            $erro = 'A soma das incidências deve totalizar 100%. Atualmente está em ' .
                number_format($somaPesos, 2, ',', '.') . '%.';
        }

        if ($erro === '') {
            try {
                $pdo->beginTransaction();

                $stmtMod = $pdo->prepare("
                    INSERT INTO modelos_casas (nome, descricao)
                    VALUES (?, ?)
                ");

                $stmtMod->execute([
                    $nome,
                    $descricao !== '' ? $descricao : null
                ]);

                $modeloId = (int) $pdo->lastInsertId();

                if ($modeloId <= 0) {
                    throw new RuntimeException('Não foi possível obter o ID do modelo.');
                }

                $stmtEtapa = $pdo->prepare("
                    INSERT INTO modelos_etapas
                        (
                            modelo_id,
                            ordem,
                            nome_etapa,
                            peso_percentual,
                            valor_estimado
                        )
                    VALUES
                        (?, ?, ?, ?, ?)
                ");

                foreach ($etapasValidas as $ordem => $etapa) {
                    $stmtEtapa->execute([
                        $modeloId,
                        $ordem + 1,
                        $etapa['nome'],
                        $etapa['peso'],
                        $etapa['valor']
                    ]);
                }

                $pdo->commit();

                header('Location: /modelos?sucesso=1');
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $erro = 'Erro ao salvar modelo.';
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">
    <!-- Cabeçalho -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0">
                <i class="bi bi-plus-square me-2 text-primary"></i>Novo Modelo de Casa
            </h3>
            <p class="text-muted m-0 small">
                Cadastre o modelo padrão PFUI Caixa com suas 20 etapas e pesos percentuais
            </p>
        </div>

        <a href="/modelos" class="btn btn-outline-secondary btn-sm fw-bold">
            <i class="bi bi-arrow-left me-1"></i> Voltar para Modelos
        </a>
    </div>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">
        <!-- Informações do Modelo -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-info-circle me-1 text-primary"></i>Informações do Modelo
            </h5>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nome do Modelo *</label>
                    <input
                        type="text"
                        name="nome"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['nome'] ?? 'Modelo Padrão PFUI Caixa Econômica', ENT_QUOTES, 'UTF-8') ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">Descrição</label>
                    <input
                        type="text"
                        name="descricao"
                        class="form-control"
                        value="<?= htmlspecialchars($_POST['descricao'] ?? 'Cronograma físico-financeiro de 20 etapas padrão Caixa', ENT_QUOTES, 'UTF-8') ?>"
                    >
                </div>
            </div>
        </div>

        <!-- Tabela de Etapas -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-3">
                <div>
                    <h5 class="fw-bold text-dark m-0">
                        <i class="bi bi-list-ol me-1 text-primary"></i>Etapas do Cronograma (PFUI Caixa)
                    </h5>
                    <small class="text-muted">
                        A soma dos pesos deve totalizar 100%.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-sm btn-success fw-bold d-inline-flex align-items-center flex-shrink-0"
                    id="btn-add-etapa"
                >
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
                            <td colspan="2" class="text-end">
                                Soma das Incidências:
                            </td>

                            <td id="total-peso-display" class="text-danger fw-bold">
                                0,00%
                            </td>

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
document.addEventListener('DOMContentLoaded', function () {
    const container = document.getElementById('container-etapas');
    const btnAddEtapa = document.getElementById('btn-add-etapa');
    const totalDisplay = document.getElementById('total-peso-display');

    if (!container || !btnAddEtapa || !totalDisplay) {
        return;
    }

    function parseNumero(valorStr) {
        if (valorStr === null || valorStr === undefined || valorStr === '') {
            return 0;
        }

        let valor = String(valorStr).trim();

        valor = valor
            .replace(/\./g, '')
            .replace(',', '.');

        const numero = parseFloat(valor);

        return Number.isFinite(numero) ? numero : 0;
    }

    function calcularSomaPesos() {
        let soma = 0;

        container.querySelectorAll('.input-peso').forEach(function (input) {
            soma += parseNumero(input.value);
        });

        totalDisplay.innerText =
            soma.toFixed(2).replace('.', ',') + '%';

        if (Math.abs(soma - 100) < 0.05) {
            totalDisplay.className = 'text-success fw-bold';
        } else {
            totalDisplay.className = 'text-danger fw-bold';
        }
    }

    function reindexar() {
        const linhas = container.querySelectorAll('tr');

        linhas.forEach(function (tr, index) {
            const indexTd = tr.querySelector('.index-num');

            if (indexTd) {
                indexTd.innerText = index + 1;
            }
        });
    }

    function adicionarLinhaEtapa(nome = '', peso = '', valor = '0,00') {
        const tr = document.createElement('tr');

        const tdIndex = document.createElement('td');
        tdIndex.className = 'fw-bold text-muted index-num';
        tdIndex.innerText = '0';

        const tdNome = document.createElement('td');
        const inputNome = document.createElement('input');

        inputNome.type = 'text';
        inputNome.name = 'etapa_nome[]';
        inputNome.className = 'form-control';
        inputNome.placeholder = 'Nome da Etapa';
        inputNome.value = nome;
        inputNome.required = true;

        tdNome.appendChild(inputNome);

        const tdPeso = document.createElement('td');
        const inputPeso = document.createElement('input');

        inputPeso.type = 'text';
        inputPeso.name = 'etapa_peso[]';
        inputPeso.className = 'form-control mask-percent input-peso';
        inputPeso.inputMode = 'numeric';
        inputPeso.placeholder = '0,00';
        inputPeso.value = peso;

        tdPeso.appendChild(inputPeso);

        const tdValor = document.createElement('td');
        const inputValor = document.createElement('input');

        inputValor.type = 'text';
        inputValor.name = 'etapa_valor[]';
        inputValor.className = 'form-control mask-money';
        inputValor.inputMode = 'numeric';
        inputValor.placeholder = '0,00';
        inputValor.value = valor;

        tdValor.appendChild(inputValor);

        const tdAcao = document.createElement('td');
        tdAcao.className = 'text-center align-middle';

        const btnRemove = document.createElement('button');

        btnRemove.type = 'button';
        btnRemove.className = 'btn btn-sm btn-outline-danger btn-remove-etapa';
        btnRemove.title = 'Remover Etapa';

        const icon = document.createElement('i');
        icon.className = 'bi bi-trash';

        btnRemove.appendChild(icon);
        tdAcao.appendChild(btnRemove);

        tr.appendChild(tdIndex);
        tr.appendChild(tdNome);
        tr.appendChild(tdPeso);
        tr.appendChild(tdValor);
        tr.appendChild(tdAcao);

        container.appendChild(tr);

        if (typeof aplicarMascaras === 'function') {
            aplicarMascaras();
        }

        inputPeso.addEventListener('input', calcularSomaPesos);

        reindexar();
        calcularSomaPesos();
    }

    btnAddEtapa.addEventListener('click', function (event) {
        event.preventDefault();
        adicionarLinhaEtapa();
    });

    container.addEventListener('click', function (event) {
        const btnRemove = event.target.closest('.btn-remove-etapa');

        if (!btnRemove) {
            return;
        }

        event.preventDefault();

        const tr = btnRemove.closest('tr');

        if (tr) {
            tr.remove();
            reindexar();
            calcularSomaPesos();
        }
    });

    /*
     * 20 Etapas Padrão PFUI Caixa Econômica Federal
     */
    const etapasCaixa = [
        {
            nome: 'Barracão + lig. provisórias (água/luz) + projetos/aprovs.',
            peso: '3,92'
        },
        {
            nome: 'Infraestrutura (estacas, brocas, baldrames, sapatas)',
            peso: '6,12'
        },
        {
            nome: 'Supraestrutura (Vigas, pilares, cintas, escadas)',
            peso: '12,65'
        },
        {
            nome: 'Paredes e Painéis',
            peso: '10,20'
        },
        {
            nome: 'Esquadrias',
            peso: '6,45'
        },
        {
            nome: 'Vidros e Plásticos',
            peso: '2,37'
        },
        {
            nome: 'Coberturas (estrutura e telhas)',
            peso: '7,76'
        },
        {
            nome: 'Impermeabilizações',
            peso: '1,88'
        },
        {
            nome: 'Revestimentos Internos',
            peso: '8,82'
        },
        {
            nome: 'Forros',
            peso: '0,00'
        },
        {
            nome: 'Revestimentos Externos',
            peso: '4,90'
        },
        {
            nome: 'Pinturas',
            peso: '6,12'
        },
        {
            nome: 'Pisos',
            peso: '9,06'
        },
        {
            nome: 'Acabamentos (soleiras, rodapés, peitoril etc.)',
            peso: '1,22'
        },
        {
            nome: 'Instalações Elétricas e Telefônicas',
            peso: '4,49'
        },
        {
            nome: 'Instalações Hidráulicas',
            peso: '3,92'
        },
        {
            nome: 'Instalações: Esgoto e Águas Pluviais',
            peso: '4,00'
        },
        {
            nome: 'Louças e Metais',
            peso: '4,73'
        },
        {
            nome: 'Complementos (limpeza final e calafete)',
            peso: '1,39'
        },
        {
            nome: 'Outros (discriminar em Serviços Adicionais)',
            peso: '0,00'
        }
    ];

    etapasCaixa.forEach(function (item) {
        adicionarLinhaEtapa(
            item.nome,
            item.peso,
            '0,00'
        );
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
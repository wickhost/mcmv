<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /modelos');
    exit;
}

$erro = '';

// Buscar modelo existente
$stmtMod = $pdo->prepare("SELECT * FROM modelos_casas WHERE id = ?");
$stmtMod->execute([$id]);
$modelo = $stmtMod->fetch(PDO::FETCH_ASSOC);

if (!$modelo) {
    header('Location: /modelos');
    exit;
}

// Processar atualização
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

                // Atualizar modelo
                $stmtUp = $pdo->prepare("
                    UPDATE modelos_casas
                    SET nome = ?, descricao = ?
                    WHERE id = ?
                ");

                $stmtUp->execute([
                    $nome,
                    $descricao !== '' ? $descricao : null,
                    $id
                ]);

                // Remover etapas antigas
                $stmtDel = $pdo->prepare("
                    DELETE FROM modelos_etapas
                    WHERE modelo_id = ?
                ");

                $stmtDel->execute([$id]);

                // Inserir etapas atualizadas
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
                        $id,
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

                $erro = 'Erro ao atualizar modelo.';
            }
        }
    }
}

// Buscar etapas existentes do modelo
$stmtEtapas = $pdo->prepare("
    SELECT *
    FROM modelos_etapas
    WHERE modelo_id = ?
    ORDER BY ordem ASC, id ASC
");

$stmtEtapas->execute([$id]);
$etapasExistentes = $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h3 class="fw-bold m-0">
                <i class="bi bi-pencil-square me-2 text-primary"></i>
                Editar Modelo de Casa
            </h3>

            <p class="text-muted m-0 small">
                Altere a estrutura de etapas ou os valores de referência do modelo
            </p>
        </div>

        <a href="/modelos" class="btn btn-outline-secondary btn-sm fw-bold w-100 w-md-auto">
            <i class="bi bi-arrow-left me-1"></i>
            Voltar para Modelos
        </a>
    </div>

    <?php if ($erro !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>
        </div>
    <?php endif; ?>

    <form method="POST" action="">

        <!-- Informações do Modelo -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-info-circle me-1 text-primary"></i>
                Informações do Modelo
            </h5>

            <div class="row g-3">

                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        Nome do Modelo *
                    </label>

                    <input
                        type="text"
                        name="nome"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $_POST['nome'] ?? $modelo['nome'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-bold">
                        Descrição
                    </label>

                    <input
                        type="text"
                        name="descricao"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $_POST['descricao'] ?? $modelo['descricao'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >
                </div>

            </div>
        </div>

        <!-- Etapas -->
        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-2 mb-3">

                <div>
                    <h5 class="fw-bold text-dark m-0">
                        <i class="bi bi-list-ol me-1 text-primary"></i>
                        Etapas do Cronograma
                    </h5>

                    <small class="text-muted">
                        A soma dos pesos deve totalizar 100%.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn btn-sm btn-success fw-bold w-100 w-sm-auto"
                    id="btn-add-etapa"
                >
                    <i class="bi bi-plus-circle me-1"></i>
                    Adicionar Etapa
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
                            <th style="width: 60px;" class="text-center">
                                Ação
                            </th>
                        </tr>
                    </thead>

                    <tbody id="container-etapas"></tbody>

                    <tfoot class="table-light fw-bold">
                        <tr>
                            <td colspan="2" class="text-end">
                                Soma das Incidências:
                            </td>

                            <td
                                id="total-peso-display"
                                class="text-danger fw-bold"
                            >
                                0,00%
                            </td>

                            <td colspan="2"></td>
                        </tr>
                    </tfoot>

                </table>

            </div>
        </div>

        <div class="text-end mb-5">

            <button
                type="submit"
                class="btn btn-primary fw-bold px-4 py-2 w-100 w-md-auto"
            >
                <i class="bi bi-check-lg me-1"></i>
                Salvar Alterações no Modelo
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

    function escaparHtml(valor) {
        const div = document.createElement('div');
        div.textContent = valor ?? '';
        return div.innerHTML;
    }

    function parseNumero(valor) {
        if (valor === null || valor === undefined || valor === '') {
            return 0;
        }

        let numero = String(valor).trim();

        numero = numero
            .replace(/\./g, '')
            .replace(',', '.');

        const resultado = parseFloat(numero);

        return Number.isFinite(resultado) ? resultado : 0;
    }

    function calcularSomaPesos() {
        let soma = 0;

        container.querySelectorAll('.input-peso').forEach(function (input) {
            soma += parseNumero(input.value);
        });

        totalDisplay.textContent =
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
            const indexElement = tr.querySelector('.index-num');

            if (indexElement) {
                indexElement.textContent = index + 1;
            }
        });
    }

    function adicionarLinhaEtapa(nome = '', peso = '', valor = '0,00') {

        const tr = document.createElement('tr');

        tr.innerHTML = `
            <td class="fw-bold text-muted index-num">0</td>

            <td>
                <input
                    type="text"
                    name="etapa_nome[]"
                    class="form-control"
                    placeholder="Nome da Etapa"
                    value="${escaparHtml(nome)}"
                    required
                >
            </td>

            <td>
                <input
                    type="text"
                    name="etapa_peso[]"
                    class="form-control mask-percent input-peso"
                    inputmode="numeric"
                    placeholder="0,00"
                    value="${escaparHtml(peso)}"
                >
            </td>

            <td>
                <input
                    type="text"
                    name="etapa_valor[]"
                    class="form-control mask-money"
                    inputmode="numeric"
                    placeholder="0,00"
                    value="${escaparHtml(valor)}"
                >
            </td>

            <td class="text-center align-middle">
                <button
                    type="button"
                    class="btn btn-sm btn-outline-danger btn-remove-etapa"
                    title="Remover Etapa"
                >
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;

        container.appendChild(tr);

        if (typeof aplicarMascaras === 'function') {
            aplicarMascaras();
        }

        const inputPeso = tr.querySelector('.input-peso');

        if (inputPeso) {
            inputPeso.addEventListener(
                'input',
                calcularSomaPesos
            );
        }

        reindexar();
        calcularSomaPesos();
    }

    btnAddEtapa.addEventListener('click', function (event) {
        event.preventDefault();

        adicionarLinhaEtapa();
    });

    container.addEventListener('click', function (event) {

        const btnRemove = event.target.closest(
            '.btn-remove-etapa'
        );

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
     * Quando houve erro de validação no POST, manter
     * exatamente os dados enviados pelo formulário.
     */
    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($etapasValidas)): ?>

    const etapasPost = <?= json_encode(
        array_map(
            static function ($etapa) {
                return [
                    'nome' => $etapa['nome'],
                    'peso' => number_format($etapa['peso'], 2, ',', '.'),
                    'valor' => number_format($etapa['valor'], 2, ',', '.')
                ];
            },
            $etapasValidas
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    etapasPost.forEach(function (etapa) {
        adicionarLinhaEtapa(
            etapa.nome,
            etapa.peso,
            etapa.valor
        );
    });

    <?php else: ?>

    /*
     * Carregar etapas existentes do banco.
     */
    const etapasExistentes = <?= json_encode(
        array_map(
            static function ($etapa) {
                return [
                    'nome' => $etapa['nome_etapa'] ?? '',
                    'peso' => number_format(
                        (float) ($etapa['peso_percentual'] ?? 0),
                        2,
                        ',',
                        '.'
                    ),
                    'valor' => number_format(
                        (float) ($etapa['valor_estimado'] ?? 0),
                        2,
                        ',',
                        '.'
                    )
                ];
            },
            $etapasExistentes
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES |
        JSON_HEX_TAG |
        JSON_HEX_AMP |
        JSON_HEX_APOS |
        JSON_HEX_QUOT
    ) ?>;

    etapasExistentes.forEach(function (etapa) {
        adicionarLinhaEtapa(
            etapa.nome,
            etapa.peso,
            etapa.valor
        );
    });

    <?php endif; ?>

    calcularSomaPesos();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
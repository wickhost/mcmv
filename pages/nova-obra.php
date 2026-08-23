<?php

require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$erro = '';

/**
 * Converte valor monetário brasileiro para float.
 *
 * Exemplos:
 * 1.234,56 -> 1234.56
 * 1234,56  -> 1234.56
 * 1234.56  -> 1234.56
 */
function limpaMoeda($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    $valor = trim((string)$valor);

    // Remove espaços e símbolos de moeda.
    $valor = preg_replace('/[^\d,.\-]/', '', $valor);

    if ($valor === '') {
        return 0.0;
    }

    /*
     * Quando existe vírgula, considera-se o padrão brasileiro:
     * 1.234,56
     */
    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

/**
 * Valida o arquivo de projeto.
 */
function validarArquivoProjeto(array $arquivo): string
{
    if (!isset($arquivo['error'])) {
        throw new RuntimeException('Arquivo de projeto inválido.');
    }

    if ($arquivo['error'] === UPLOAD_ERR_NO_FILE) {
        throw new RuntimeException('Nenhum arquivo foi enviado.');
    }

    if ($arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Erro ao enviar o arquivo de projeto.');
    }

    if (($arquivo['size'] ?? 0) <= 0) {
        throw new RuntimeException('O arquivo de projeto está vazio.');
    }

    // Limite de 20 MB.
    if (($arquivo['size'] ?? 0) > 20 * 1024 * 1024) {
        throw new RuntimeException(
            'O arquivo de projeto não pode ultrapassar 20 MB.'
        );
    }

    $permitidos = [
        'pdf'  => [
            'application/pdf',
        ],
        'png'  => [
            'image/png',
        ],
        'jpg'  => [
            'image/jpeg',
        ],
        'jpeg' => [
            'image/jpeg',
        ],
    ];

    $extensao = strtolower(
        pathinfo($arquivo['name'] ?? '', PATHINFO_EXTENSION)
    );

    if (!isset($permitidos[$extensao])) {
        throw new RuntimeException(
            'Formato de arquivo inválido. Use PDF, PNG ou JPG.'
        );
    }

    if (
        !isset($arquivo['tmp_name']) ||
        !is_uploaded_file($arquivo['tmp_name'])
    ) {
        throw new RuntimeException(
            'O arquivo enviado não é um upload válido.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    if (
        $mime === false ||
        !in_array($mime, $permitidos[$extensao], true)
    ) {
        throw new RuntimeException(
            'O conteúdo do arquivo não corresponde ao formato informado.'
        );
    }

    return $extensao;
}

/*
 * Buscar clientes e modelos.
 */
$clientes = $pdo
    ->query("
        SELECT id, nome, cpf
        FROM usuarios
        WHERE tipo = 'cliente'
        ORDER BY nome ASC
    ")
    ->fetchAll();

$modelos = $pdo
    ->query("
        SELECT id, nome
        FROM modelos_casas
        ORDER BY nome ASC
    ")
    ->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $clienteId = filter_input(
        INPUT_POST,
        'cliente_id',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $modeloId = filter_input(
        INPUT_POST,
        'modelo_id',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $enderecoObra = trim(
        (string)($_POST['endereco_obra'] ?? '')
    );

    $valorTotal = limpaMoeda(
        $_POST['valor_total'] ?? '0'
    );

    $valorFinanciamento = limpaMoeda(
        $_POST['valor_financiamento'] ?? '0'
    );

    $valorTerreno = limpaMoeda(
        $_POST['valor_terreno'] ?? '0'
    );

    $valorSubsidio = limpaMoeda(
        $_POST['valor_subsidio'] ?? '0'
    );

    $valorEntrada = limpaMoeda(
        $_POST['valor_entrada'] ?? '0'
    );

    /*
     * Validações básicas.
     */
    if ($clienteId <= 0) {
        $erro = 'Selecione um cliente válido.';
    } elseif ($enderecoObra === '') {
        $erro = 'Informe o endereço da obra.';
    } elseif ($valorTotal <= 0) {
        $erro = 'Informe um valor total do imóvel válido.';
    } elseif (
        $valorFinanciamento < 0 ||
        $valorTerreno < 0 ||
        $valorSubsidio < 0 ||
        $valorEntrada < 0
    ) {
        $erro = 'Os valores financeiros não podem ser negativos.';
    }

    /*
     * Validar se o cliente realmente existe e é cliente.
     */
    if ($erro === '') {
        $stmtCliente = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE id = ?
              AND tipo = 'cliente'
            LIMIT 1
        ");

        $stmtCliente->execute([$clienteId]);

        if (!$stmtCliente->fetchColumn()) {
            $erro = 'O cliente selecionado não foi encontrado.';
        }
    }

    /*
     * Validar modelo, quando informado.
     */
    if ($erro === '' && $modeloId > 0) {
        $stmtModelo = $pdo->prepare("
            SELECT id
            FROM modelos_casas
            WHERE id = ?
            LIMIT 1
        ");

        $stmtModelo->execute([$modeloId]);

        if (!$stmtModelo->fetchColumn()) {
            $erro = 'O modelo de cronograma selecionado não foi encontrado.';
        }
    }

    if ($erro === '') {
        /*
         * Sobra para construção:
         *
         * Financiamento
         * + Subsídio
         * + Entrada
         * - Terreno
         */
        $sobraConstrucao =
            $valorFinanciamento
            + $valorSubsidio
            + $valorEntrada
            - $valorTerreno;

        if ($sobraConstrucao <= 0) {
            $erro = 'O valor disponível para a construção é zero ou negativo. Verifique os valores.';
        }
    }

    $arquivoProjeto = null;
    $destinoProjeto = null;

    if (
        $erro === '' &&
        isset($_FILES['arquivo_projeto']) &&
        ($_FILES['arquivo_projeto']['error'] ?? UPLOAD_ERR_NO_FILE)
            !== UPLOAD_ERR_NO_FILE
    ) {
        try {
            $extensao = validarArquivoProjeto(
                $_FILES['arquivo_projeto']
            );

            $diretorioUploads = __DIR__ . '/../uploads/';

            if (
                !is_dir($diretorioUploads) &&
                !mkdir($diretorioUploads, 0755, true) &&
                !is_dir($diretorioUploads)
            ) {
                throw new RuntimeException(
                    'Não foi possível criar o diretório de uploads.'
                );
            }

            $nomeArquivo =
                'projeto_' .
                bin2hex(random_bytes(16)) .
                '.' .
                $extensao;

            $destinoProjeto =
                $diretorioUploads . $nomeArquivo;

            /*
             * CORREÇÃO:
             * A função correta do PHP é move_uploaded_file().
             */
            if (
                !move_uploaded_file(
                    $_FILES['arquivo_projeto']['tmp_name'],
                    $destinoProjeto
                )
            ) {
                throw new RuntimeException(
                    'Não foi possível salvar o arquivo de projeto.'
                );
            }

            $arquivoProjeto = $nomeArquivo;
        } catch (Throwable $e) {
            $erro = $e->getMessage();

            /*
             * Se o upload foi salvo mas posteriormente houve erro,
             * remove o arquivo para não deixar lixo no servidor.
             */
            if (
                $destinoProjeto !== null &&
                is_file($destinoProjeto)
            ) {
                @unlink($destinoProjeto);
            }

            $arquivoProjeto = null;
            $destinoProjeto = null;
        }
    }

    if ($erro === '') {
        try {
            $pdo->beginTransaction();

            /*
             * Criar a obra.
             */
            $stmtObra = $pdo->prepare("
                INSERT INTO obras (
                    cliente_id,
                    modelo_id,
                    endereco_obra,
                    valor_total,
                    valor_terreno,
                    valor_subsidio,
                    valor_entrada,
                    sobra_construcao,
                    arquivo_projeto
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmtObra->execute([
                $clienteId,
                $modeloId > 0 ? $modeloId : null,
                $enderecoObra,
                $valorTotal,
                $valorTerreno,
                $valorSubsidio,
                $valorEntrada,
                $sobraConstrucao,
                $arquivoProjeto
            ]);

            $obraId = (int)$pdo->lastInsertId();

            if ($obraId <= 0) {
                throw new RuntimeException(
                    'Não foi possível obter o ID da obra criada.'
                );
            }

            /*
             * Copiar as etapas do modelo para a obra.
             */
            if ($modeloId > 0) {
                $stmtEtapasModelo = $pdo->prepare("
                    SELECT
                        ordem,
                        nome_etapa,
                        peso_percentual
                    FROM modelos_etapas
                    WHERE modelo_id = ?
                    ORDER BY ordem ASC
                ");

                $stmtEtapasModelo->execute([
                    $modeloId
                ]);

                $etapasModelo = $stmtEtapasModelo->fetchAll();

                $stmtInsertObraEtapa = $pdo->prepare("
                    INSERT INTO obra_etapas (
                        obra_id,
                        ordem,
                        nome_etapa,
                        peso_percentual,
                        valor_etapa,
                        progresso,
                        concluido
                    )
                    VALUES (?, ?, ?, ?, ?, 0.00, 0)
                ");

                foreach ($etapasModelo as $etapa) {
                    $peso = (float)$etapa['peso_percentual'];

                    $valorEtapa =
                        ($sobraConstrucao * $peso) / 100;

                    $stmtInsertObraEtapa->execute([
                        $obraId,
                        (int)$etapa['ordem'],
                        $etapa['nome_etapa'],
                        $peso,
                        $valorEtapa
                    ]);
                }
            }

            $pdo->commit();

            header(
                'Location: /gerenciar-obra?id=' .
                $obraId
            );
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            /*
             * Se o banco falhou depois do upload,
             * remove o arquivo enviado.
             */
            if (
                $destinoProjeto !== null &&
                is_file($destinoProjeto)
            ) {
                @unlink($destinoProjeto);
            }

            $arquivoProjeto = null;
            $destinoProjeto = null;

            /*
             * Não expõe detalhes internos do banco ao usuário.
             */
            $erro = 'Erro ao cadastrar a obra. Verifique os dados e tente novamente.';
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="page-header">
        <div class="page-header-content">
            <h3 class="page-title">
                <i class="bi bi-building-add text-primary me-2"></i>
                Nova Obra
            </h3>

            <p class="page-subtitle">
                Cadastre uma nova obra e vincule ao proprietário
            </p>
        </div>

        <a
            href="/dashboard"
            class="btn btn-outline-secondary btn-action-top"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Voltar ao Dashboard
        </a>
    </div>

    <?php if ($erro): ?>
        <div
            class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"
            role="alert"
        >
            <i class="bi bi-exclamation-triangle-fill me-2"></i>
            <?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Fechar"
            ></button>
        </div>
    <?php endif; ?>

    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
    >

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-person-badge me-2 text-primary"></i>
                Proprietário & Modelo
            </h5>

            <div class="row g-3">

                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Cliente Proprietário *
                    </label>

                    <select
                        name="cliente_id"
                        class="form-select"
                        required
                    >
                        <option value="">
                            Selecione um cliente...
                        </option>

                        <?php foreach ($clientes as $cliente): ?>

                            <option
                                value="<?= (int)$cliente['id'] ?>"
                                <?= (
                                    isset($_POST['cliente_id']) &&
                                    (int)$_POST['cliente_id'] === (int)$cliente['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $cliente['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                                <?php if (!empty($cliente['cpf'])): ?>
                                    (CPF:
                                    <?= htmlspecialchars(
                                        $cliente['cpf'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>)
                                <?php endif; ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-md-6">

                    <label class="form-label fw-bold">
                        Modelo de Cronograma (Opcional)
                    </label>

                    <select
                        name="modelo_id"
                        class="form-select"
                    >
                        <option value="">
                            Sem modelo (cadastrar etapas manualmente)
                        </option>

                        <?php foreach ($modelos as $modelo): ?>

                            <option
                                value="<?= (int)$modelo['id'] ?>"
                                <?= (
                                    isset($_POST['modelo_id']) &&
                                    (int)$_POST['modelo_id'] === (int)$modelo['id']
                                ) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars(
                                    $modelo['nome'],
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>
                            </option>

                        <?php endforeach; ?>

                    </select>

                </div>

                <div class="col-12">

                    <label class="form-label fw-bold">
                        Endereço Completo da Obra *
                    </label>

                    <input
                        type="text"
                        name="endereco_obra"
                        class="form-control"
                        placeholder="Rua, Número, Bairro, Cidade"
                        value="<?= htmlspecialchars(
                            $_POST['endereco_obra'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>

            </div>

        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-cash-stack me-2 text-primary"></i>
                Valores Físico-Financeiros
            </h5>

            <div class="row g-3">

                <div class="col-md-6 col-lg-4">

                    <label class="form-label fw-bold">
                        Valor Total do Imóvel *
                    </label>

                    <input
                        type="text"
                        name="valor_total"
                        id="valor_total"
                        class="form-control mask-money"
                        value="<?= htmlspecialchars(
                            $_POST['valor_total'] ?? '0,00',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>

                <div class="col-md-6 col-lg-4">

                    <label class="form-label fw-bold">
                        Financiamento (Caixa - 80%)
                    </label>

                    <input
                        type="text"
                        name="valor_financiamento"
                        id="valor_financiamento"
                        class="form-control mask-money"
                        value="<?= htmlspecialchars(
                            $_POST['valor_financiamento'] ?? '0,00',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="col-md-6 col-lg-4">

                    <label class="form-label fw-bold">
                        Subsídio MCMV
                    </label>

                    <input
                        type="text"
                        name="valor_subsidio"
                        id="valor_subsidio"
                        class="form-control mask-money"
                        value="<?= htmlspecialchars(
                            $_POST['valor_subsidio'] ?? '0,00',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="col-md-6 col-lg-6">

                    <label class="form-label fw-bold">
                        Entrada em Dinheiro (Recurso Próprio)
                    </label>

                    <input
                        type="text"
                        name="valor_entrada"
                        id="valor_entrada"
                        class="form-control mask-money"
                        value="<?= htmlspecialchars(
                            $_POST['valor_entrada'] ?? '0,00',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                    <small class="text-muted">
                        Calculada descontando o subsídio
                        (ou ajustada manualmente)
                    </small>

                </div>

                <div class="col-md-6 col-lg-6">

                    <label class="form-label fw-bold">
                        Valor do Terreno
                    </label>

                    <input
                        type="text"
                        name="valor_terreno"
                        id="valor_terreno"
                        class="form-control mask-money"
                        value="<?= htmlspecialchars(
                            $_POST['valor_terreno'] ?? '0,00',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="col-12 mt-3">

                    <div class="p-3 bg-light rounded border">

                        <div class="d-flex justify-content-between align-items-center mb-1">

                            <span class="text-muted">
                                Total de Recursos da Obra:
                            </span>

                            <span
                                class="fw-bold text-dark"
                                id="total_recursos_display"
                            >
                                R$ 0,00
                            </span>

                        </div>

                        <hr class="my-2">

                        <div class="d-flex justify-content-between align-items-center">

                            <span class="fw-bold text-dark fs-5">
                                Sobra Destinada à Construção:
                            </span>

                            <h3
                                class="fw-bold text-success m-0"
                                id="sobra_display"
                            >
                                R$ 0,00
                            </h3>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">
                <i class="bi bi-file-earmark-pdf me-2 text-primary"></i>
                Projeto / Planta (Opcional)
            </h5>

            <div class="row g-3">

                <div class="col-12">

                    <input
                        type="file"
                        name="arquivo_projeto"
                        class="form-control"
                        accept=".pdf,.png,.jpg,.jpeg"
                    >

                    <small class="text-muted">
                        Anexe a planta ou projeto arquitetônico da obra
                        em PDF ou imagem. Máximo: 20 MB.
                    </small>

                </div>

            </div>

        </div>

        <div class="col-12 mt-4 text-end">

            <button
                type="submit"
                class="btn btn-primary fw-bold px-4 w-100 w-sm-auto"
            >
                <i class="bi bi-check-lg me-1"></i>
                Criar e Gerenciar Obra
            </button>

        </div>

    </form>

</div>

<script>
document.addEventListener('DOMContentLoaded', function () {

    function parseMoeda(valor) {
        if (!valor) {
            return 0;
        }

        const apenasNumeros = valor
            .toString()
            .replace(/\D/g, '');

        if (!apenasNumeros) {
            return 0;
        }

        return parseFloat(apenasNumeros) / 100;
    }

    function formatarMoeda(valor) {
        return valor.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });
    }

    function formatarInput(valorFloat) {
        return valorFloat.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    const inputTotal =
        document.getElementById('valor_total');

    const inputFinanc =
        document.getElementById('valor_financiamento');

    const inputSubsidio =
        document.getElementById('valor_subsidio');

    const inputEntrada =
        document.getElementById('valor_entrada');

    const inputTerreno =
        document.getElementById('valor_terreno');

    const totalRecursosDisplay =
        document.getElementById('total_recursos_display');

    const sobraDisplay =
        document.getElementById('sobra_display');

    if (
        !inputTotal ||
        !inputFinanc ||
        !inputSubsidio ||
        !inputEntrada ||
        !inputTerreno ||
        !totalRecursosDisplay ||
        !sobraDisplay
    ) {
        return;
    }

    let entradaEditadaManualmente = false;

    function calcularValoresFinal() {

        const vFinanc =
            parseMoeda(inputFinanc.value);

        const vSubsidio =
            parseMoeda(inputSubsidio.value);

        const vEntrada =
            parseMoeda(inputEntrada.value);

        const vTerreno =
            parseMoeda(inputTerreno.value);

        const recursosTotais =
            vFinanc +
            vSubsidio +
            vEntrada;

        totalRecursosDisplay.innerText =
            formatarMoeda(recursosTotais);

        const sobra =
            recursosTotais -
            vTerreno;

        sobraDisplay.innerText =
            formatarMoeda(
                sobra > 0 ? sobra : 0
            );
    }

    function recalcularEntradaESobra() {

        const vTotal =
            parseMoeda(inputTotal.value);

        const vSubsidio =
            parseMoeda(inputSubsidio.value);

        if (vTotal > 0) {

            const vFinanc =
                vTotal * 0.80;

            inputFinanc.value =
                formatarInput(vFinanc);

            const entradaBrutaNecessaria =
                vTotal - vFinanc;

            if (!entradaEditadaManualmente) {

                const entradaEmDinheiro =
                    Math.max(
                        0,
                        entradaBrutaNecessaria -
                        vSubsidio
                    );

                inputEntrada.value =
                    formatarInput(
                        entradaEmDinheiro
                    );
            }
        }

        calcularValoresFinal();
    }

    inputTotal.addEventListener(
        'input',
        function () {
            entradaEditadaManualmente = false;
            recalcularEntradaESobra();
        }
    );

    inputSubsidio.addEventListener(
        'input',
        recalcularEntradaESobra
    );

    inputEntrada.addEventListener(
        'focus',
        function () {
            entradaEditadaManualmente = true;
        }
    );

    inputEntrada.addEventListener(
        'input',
        calcularValoresFinal
    );

    inputTerreno.addEventListener(
        'input',
        calcularValoresFinal
    );

    inputFinanc.addEventListener(
        'input',
        calcularValoresFinal
    );

    calcularValoresFinal();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
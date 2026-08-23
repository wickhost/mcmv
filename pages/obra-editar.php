<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$obraId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

if (!$obraId) {
    header('Location: /dashboard');
    exit;
}

$erro = '';
$sucesso = '';

/*
|--------------------------------------------------------------------------
| Buscar obra
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM obras
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$obraId]);

$obra = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$obra) {
    header('Location: /dashboard');
    exit;
}

/*
|--------------------------------------------------------------------------
| Buscar clientes
|--------------------------------------------------------------------------
*/
$clientes = $pdo->query("
    SELECT
        id,
        nome,
        cpf
    FROM usuarios
    WHERE tipo = 'cliente'
    ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);


/*
|--------------------------------------------------------------------------
| Funções auxiliares
|--------------------------------------------------------------------------
*/
function limpaMoedaEdicao($valor): float
{
    if ($valor === null || $valor === '') {
        return 0.0;
    }

    $valor = trim((string)$valor);

    $valor = preg_replace('/[^\d,.-]/', '', $valor);

    if ($valor === '') {
        return 0.0;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return max(0.0, (float)$valor);
}


function uploadProjetoEdicao(array $arquivo): ?string
{
    $erroUpload = $arquivo['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($erroUpload === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($erroUpload !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            'Erro ao enviar o arquivo do projeto.'
        );
    }

    if (($arquivo['size'] ?? 0) > 10 * 1024 * 1024) {
        throw new RuntimeException(
            'O arquivo do projeto não pode ultrapassar 10 MB.'
        );
    }

    $permitidos = [
        'pdf' => [
            'application/pdf'
        ],
        'png' => [
            'image/png'
        ],
        'jpg' => [
            'image/jpeg'
        ],
        'jpeg' => [
            'image/jpeg'
        ]
    ];

    $extensao = strtolower(
        pathinfo($arquivo['name'], PATHINFO_EXTENSION)
    );

    if (!isset($permitidos[$extensao])) {
        throw new RuntimeException(
            'Formato de projeto inválido. Use PDF, PNG ou JPG.'
        );
    }

    if (
        !isset($arquivo['tmp_name']) ||
        !is_uploaded_file($arquivo['tmp_name'])
    ) {
        throw new RuntimeException(
            'Arquivo enviado inválido.'
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($arquivo['tmp_name']);

    if (!in_array($mime, $permitidos[$extensao], true)) {
        throw new RuntimeException(
            'O conteúdo do arquivo não corresponde ao formato informado.'
        );
    }

    $diretorio = __DIR__ . '/../uploads/';

    if (
        !is_dir($diretorio) &&
        !mkdir($diretorio, 0755, true) &&
        !is_dir($diretorio)
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

    $destino = $diretorio . $nomeArquivo;

    if (!move_uploaded_file(
        $arquivo['tmp_name'],
        $destino
    )) {
        throw new RuntimeException(
            'Não foi possível salvar o novo projeto.'
        );
    }

    return $nomeArquivo;
}


/*
|--------------------------------------------------------------------------
| Atualização da obra
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $clienteId = filter_input(
        INPUT_POST,
        'cliente_id',
        FILTER_VALIDATE_INT
    ) ?: 0;

    $enderecoObra = trim(
        $_POST['endereco_obra'] ?? ''
    );

    $valorTotal = limpaMoedaEdicao(
        $_POST['valor_total'] ?? '0'
    );

    $valorTerreno = limpaMoedaEdicao(
        $_POST['valor_terreno'] ?? '0'
    );

    $valorSubsidio = limpaMoedaEdicao(
        $_POST['valor_subsidio'] ?? '0'
    );

    $valorEntrada = limpaMoedaEdicao(
        $_POST['valor_entrada'] ?? '0'
    );


    /*
    |--------------------------------------------------------------------------
    | Atualizar valores exibidos caso ocorra erro
    |--------------------------------------------------------------------------
    */
    $obra['cliente_id'] = $clienteId;
    $obra['endereco_obra'] = $enderecoObra;
    $obra['valor_total'] = $valorTotal;
    $obra['valor_terreno'] = $valorTerreno;
    $obra['valor_subsidio'] = $valorSubsidio;
    $obra['valor_entrada'] = $valorEntrada;


    /*
    |--------------------------------------------------------------------------
    | Validações
    |--------------------------------------------------------------------------
    */
    if (
        !$clienteId ||
        $enderecoObra === '' ||
        $valorTotal <= 0
    ) {

        $erro =
            'Por favor, preencha o cliente, endereço e o valor total da obra.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Validar cliente
        |--------------------------------------------------------------------------
        */
        $stmtCliente = $pdo->prepare("
            SELECT id
            FROM usuarios
            WHERE id = ?
              AND tipo = 'cliente'
            LIMIT 1
        ");

        $stmtCliente->execute([
            $clienteId
        ]);

        if (!$stmtCliente->fetchColumn()) {

            $erro = 'Cliente selecionado inválido.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Cálculo da construção
            |--------------------------------------------------------------------------
            |
            | Valor da construção =
            | Valor total do imóvel - valor do terreno
            |
            */
            $sobraConstrucao = round(
                $valorTotal - $valorTerreno,
                2
            );

            $obra['sobra_construcao'] = $sobraConstrucao;


            if ($sobraConstrucao <= 0) {

                $erro =
                    'O valor destinado à construção deve ser maior que zero.';

            } else {

                $arquivoProjetoAtual =
                    $obra['arquivo_projeto'] ?? null;

                $novoArquivoProjeto = null;

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Upload de novo projeto
                    |--------------------------------------------------------------------------
                    */
                    if (
                        isset($_FILES['arquivo_projeto']) &&
                        ($_FILES['arquivo_projeto']['error'] ?? UPLOAD_ERR_NO_FILE)
                            !== UPLOAD_ERR_NO_FILE
                    ) {

                        $novoArquivoProjeto =
                            uploadProjetoEdicao(
                                $_FILES['arquivo_projeto']
                            );
                    }


                    $arquivoProjetoFinal =
                        $novoArquivoProjeto !== null
                            ? $novoArquivoProjeto
                            : $arquivoProjetoAtual;


                    /*
                    |--------------------------------------------------------------------------
                    | Transação
                    |--------------------------------------------------------------------------
                    */
                    $pdo->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | Atualizar obra
                    |--------------------------------------------------------------------------
                    */
                    $stmtUp = $pdo->prepare("
                        UPDATE obras
                        SET
                            cliente_id = ?,
                            endereco_obra = ?,
                            valor_total = ?,
                            valor_terreno = ?,
                            valor_subsidio = ?,
                            valor_entrada = ?,
                            sobra_construcao = ?,
                            arquivo_projeto = ?
                        WHERE id = ?
                    ");

                    $stmtUp->execute([
                        $clienteId,
                        $enderecoObra,
                        $valorTotal,
                        $valorTerreno,
                        $valorSubsidio,
                        $valorEntrada,
                        $sobraConstrucao,
                        $arquivoProjetoFinal,
                        $obraId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Recalcular valores das etapas
                    |--------------------------------------------------------------------------
                    */
                    $stmtEtapas = $pdo->prepare("
                        SELECT
                            id,
                            peso_percentual
                        FROM obra_etapas
                        WHERE obra_id = ?
                    ");

                    $stmtEtapas->execute([
                        $obraId
                    ]);

                    $etapas =
                        $stmtEtapas->fetchAll(PDO::FETCH_ASSOC);


                    $stmtUpEtapa = $pdo->prepare("
                        UPDATE obra_etapas
                        SET valor_etapa = ?
                        WHERE id = ?
                          AND obra_id = ?
                    ");


                    foreach ($etapas as $etapa) {

                        $peso =
                            (float)$etapa['peso_percentual'];

                        $novoValorEtapa = round(
                            (
                                $sobraConstrucao *
                                $peso
                            ) / 100,
                            2
                        );

                        $stmtUpEtapa->execute([
                            $novoValorEtapa,
                            (int)$etapa['id'],
                            $obraId
                        ]);
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Finalizar transação
                    |--------------------------------------------------------------------------
                    */
                    $pdo->commit();


                    /*
                    |--------------------------------------------------------------------------
                    | Excluir projeto antigo somente depois
                    | que o banco foi atualizado com sucesso
                    |--------------------------------------------------------------------------
                    */
                    if (
                        $novoArquivoProjeto !== null &&
                        !empty($arquivoProjetoAtual) &&
                        $arquivoProjetoAtual !== $novoArquivoProjeto
                    ) {

                        $arquivoAntigo =
                            __DIR__ .
                            '/../uploads/' .
                            basename($arquivoProjetoAtual);

                        if (is_file($arquivoAntigo)) {
                            @unlink($arquivoAntigo);
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Redirecionar
                    |--------------------------------------------------------------------------
                    */
                    header(
                        "Location: /gerenciar-obra?id={$obraId}"
                    );

                    exit;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Remover novo arquivo se o banco falhou
                    |--------------------------------------------------------------------------
                    */
                    if (
                        $novoArquivoProjeto !== null
                    ) {

                        $arquivoNovo =
                            __DIR__ .
                            '/../uploads/' .
                            basename($novoArquivoProjeto);

                        if (is_file($arquivoNovo)) {
                            @unlink($arquivoNovo);
                        }
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Não expor detalhes internos do banco
                    |--------------------------------------------------------------------------
                    */
                    $erro =
                        'Erro ao atualizar a obra. Verifique os dados e tente novamente.';
                }
            }
        }
    }
}


require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div
        class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4"
    >

        <div>

            <h3 class="fw-bold m-0">

                <i class="bi bi-pencil-square me-2 text-primary"></i>

                Editar Obra #<?= (int)$obra['id'] ?>

            </h3>

            <p class="text-muted m-0 small">
                Atualize as informações gerais e valores da obra
            </p>

        </div>

        <a
            href="/gerenciar-obra?id=<?= (int)$obra['id'] ?>"
            class="btn btn-outline-secondary btn-sm fw-bold w-100 w-md-auto"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Voltar à Gestão
        </a>

    </div>


    <?php if ($erro !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

            <?= htmlspecialchars(
                $erro,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($sucesso !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

            <?= htmlspecialchars(
                $sucesso,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <form
        method="POST"
        action=""
        enctype="multipart/form-data"
    >

        <!-- PROPRIETÁRIO -->

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">

                <i class="bi bi-person-badge me-2 text-primary"></i>

                Proprietário & Local

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
                                    (int)$cliente['id'] ===
                                    (int)$obra['cliente_id']
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
                        Endereço Completo da Obra *
                    </label>

                    <input
                        type="text"
                        name="endereco_obra"
                        class="form-control"
                        value="<?= htmlspecialchars(
                            $obra['endereco_obra'] ?? '',
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>

            </div>

        </div>


        <!-- VALORES -->

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">

                <i class="bi bi-cash-stack me-2 text-primary"></i>

                Valores Físico-Financeiros

            </h5>


            <div class="row g-3">

                <div class="col-md-6 col-lg-3">

                    <label class="form-label fw-bold">
                        Valor Total do Imóvel *
                    </label>

                    <input
                        type="text"
                        name="valor_total"
                        id="valor_total"
                        class="form-control mask-money"
                        inputmode="numeric"
                        value="<?= number_format(
                            (float)($obra['valor_total'] ?? 0),
                            2,
                            ',',
                            '.'
                        ) ?>"
                        required
                    >

                </div>


                <div class="col-md-6 col-lg-3">

                    <label class="form-label fw-bold">
                        Valor do Terreno
                    </label>

                    <input
                        type="text"
                        name="valor_terreno"
                        id="valor_terreno"
                        class="form-control mask-money"
                        inputmode="numeric"
                        value="<?= number_format(
                            (float)($obra['valor_terreno'] ?? 0),
                            2,
                            ',',
                            '.'
                        ) ?>"
                    >

                </div>


                <div class="col-md-6 col-lg-3">

                    <label class="form-label fw-bold">
                        Subsídio
                    </label>

                    <input
                        type="text"
                        name="valor_subsidio"
                        id="valor_subsidio"
                        class="form-control mask-money"
                        inputmode="numeric"
                        value="<?= number_format(
                            (float)($obra['valor_subsidio'] ?? 0),
                            2,
                            ',',
                            '.'
                        ) ?>"
                    >

                </div>


                <div class="col-md-6 col-lg-3">

                    <label class="form-label fw-bold">
                        Entrada (Recurso Próprio)
                    </label>

                    <input
                        type="text"
                        name="valor_entrada"
                        id="valor_entrada"
                        class="form-control mask-money"
                        inputmode="numeric"
                        value="<?= number_format(
                            (float)($obra['valor_entrada'] ?? 0),
                            2,
                            ',',
                            '.'
                        ) ?>"
                    >

                </div>


                <div class="col-12 mt-3">

                    <div class="p-3 bg-light rounded border">

                        <span class="fw-bold text-muted">
                            Sobra Destinada à Construção:
                        </span>

                        <h4
                            class="fw-bold text-success m-0 mt-1"
                            id="sobra_display"
                        >

                            R$
                            <?= number_format(
                                (float)($obra['sobra_construcao'] ?? 0),
                                2,
                                ',',
                                '.'
                            ) ?>

                        </h4>

                    </div>

                </div>

            </div>

        </div>


        <!-- PROJETO -->

        <div class="card border-0 shadow-sm p-3 p-md-4 mb-4">

            <h5 class="fw-bold text-dark mb-3">

                <i class="bi bi-file-earmark-pdf me-2 text-primary"></i>

                Projeto / Planta

            </h5>


            <div class="row g-3">

                <div class="col-12">

                    <?php if (!empty($obra['arquivo_projeto'])): ?>

                        <div class="mb-2">

                            <span
                                class="badge bg-light text-dark border p-2 me-2"
                            >

                                <i class="bi bi-file-earmark me-1 text-primary"></i>

                                <?= htmlspecialchars(
                                    basename($obra['arquivo_projeto']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>


                            <a
                                href="/uploads/<?= rawurlencode(
                                    basename($obra['arquivo_projeto'])
                                ) ?>"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="btn btn-sm btn-outline-primary fw-bold"
                            >

                                <i class="bi bi-download me-1"></i>

                                Baixar Arquivo Atual

                            </a>

                        </div>

                    <?php endif; ?>


                    <input
                        type="file"
                        name="arquivo_projeto"
                        class="form-control"
                        accept=".pdf,.png,.jpg,.jpeg"
                    >

                    <small class="text-muted">
                        Selecione um novo arquivo somente se quiser substituir
                        o projeto atual. Máximo: 10 MB.
                    </small>

                </div>

            </div>

        </div>


        <!-- BOTÃO -->

        <div class="text-end mb-5">

            <button
                type="submit"
                class="btn btn-primary fw-bold px-4 py-2 w-100 w-md-auto"
            >

                <i class="bi bi-check-lg me-1"></i>

                Salvar Alterações

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

        const numeros = String(valor).replace(/\D/g, '');

        if (!numeros) {
            return 0;
        }

        return parseFloat(numeros) / 100;
    }


    function formatarMoeda(valor) {

        return Number(valor).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL'
        });

    }


    function calcularSobra() {

        const campoTotal =
            document.getElementById('valor_total');

        const campoTerreno =
            document.getElementById('valor_terreno');

        const display =
            document.getElementById('sobra_display');


        if (!campoTotal || !campoTerreno || !display) {
            return;
        }


        const valorTotal =
            parseMoeda(campoTotal.value);

        const valorTerreno =
            parseMoeda(campoTerreno.value);


        const sobra =
            valorTotal - valorTerreno;


        display.innerText =
            formatarMoeda(Math.max(0, sobra));


        display.classList.toggle(
            'text-danger',
            sobra <= 0
        );

        display.classList.toggle(
            'text-success',
            sobra > 0
        );

    }


    [
        'valor_total',
        'valor_terreno',
        'valor_subsidio',
        'valor_entrada'
    ].forEach(function (id) {

        const input =
            document.getElementById(id);

        if (input) {

            input.addEventListener(
                'input',
                calcularSobra
            );

        }

    });


    calcularSobra();

});
</script>


<?php
require_once __DIR__ . '/../includes/footer.php';
?>
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {
    header('Location: /clientes');
    exit;
}

$erro = '';
$sucesso = '';

/*
|--------------------------------------------------------------------------
| Buscar cliente
|--------------------------------------------------------------------------
*/
$stmt = $pdo->prepare("
    SELECT *
    FROM usuarios
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([$id]);
$cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    header('Location: /clientes');
    exit;
}

/*
|--------------------------------------------------------------------------
| Atualizar cliente
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nome = trim($_POST['nome'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $telefone = trim($_POST['telefone'] ?? '');
    $endereco = trim($_POST['endereco'] ?? '');
    $novaSenha = $_POST['nova_senha'] ?? '';

    /*
    |--------------------------------------------------------------------------
    | Validações básicas
    |--------------------------------------------------------------------------
    */

    if ($nome === '' || $cpf === '') {

        $erro = 'Nome e CPF são obrigatórios.';

    } else {

        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpfLimpo) !== 11) {

            $erro = 'Informe um CPF válido.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Verificar CPF duplicado
            |--------------------------------------------------------------------------
            */

            $stmtCheck = $pdo->prepare("
                SELECT id
                FROM usuarios
                WHERE
                    REPLACE(
                        REPLACE(
                            REPLACE(cpf, '.', ''),
                            '-',
                            ''
                        ),
                        ' ',
                        ''
                    ) = ?
                    AND id != ?
                LIMIT 1
            ");

            $stmtCheck->execute([
                $cpfLimpo,
                $id
            ]);

            if ($stmtCheck->fetchColumn()) {

                $erro = 'Este CPF já pertence a outro usuário cadastrado.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Atualizar com ou sem senha
                    |--------------------------------------------------------------------------
                    */

                    if ($novaSenha !== '') {

                        $senhaHash = password_hash(
                            $novaSenha,
                            PASSWORD_DEFAULT
                        );

                        if ($senhaHash === false) {
                            throw new RuntimeException(
                                'Não foi possível gerar a nova senha.'
                            );
                        }

                        $stmtUp = $pdo->prepare("
                            UPDATE usuarios
                            SET
                                nome = ?,
                                cpf = ?,
                                email = ?,
                                telefone = ?,
                                endereco = ?,
                                senha = ?
                            WHERE id = ?
                        ");

                        $stmtUp->execute([
                            $nome,
                            $cpf,
                            $email !== '' ? $email : null,
                            $telefone !== '' ? $telefone : null,
                            $endereco !== '' ? $endereco : null,
                            $senhaHash,
                            $id
                        ]);

                    } else {

                        $stmtUp = $pdo->prepare("
                            UPDATE usuarios
                            SET
                                nome = ?,
                                cpf = ?,
                                email = ?,
                                telefone = ?,
                                endereco = ?
                            WHERE id = ?
                        ");

                        $stmtUp->execute([
                            $nome,
                            $cpf,
                            $email !== '' ? $email : null,
                            $telefone !== '' ? $telefone : null,
                            $endereco !== '' ? $endereco : null,
                            $id
                        ]);
                    }

                    $sucesso = 'Dados atualizados com sucesso!';

                    /*
                    |--------------------------------------------------------------------------
                    | Recarregar cliente
                    |--------------------------------------------------------------------------
                    */

                    $stmt->execute([$id]);
                    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

                } catch (PDOException $e) {

                    /*
                    | Não exibir detalhes do banco para o usuário.
                    */
                    $erro = 'Erro ao atualizar os dados.';

                } catch (Throwable $e) {

                    $erro = $e->getMessage();
                }
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-3 my-md-4">

    <div class="row justify-content-center">

        <div class="col-12 col-md-10 col-lg-8">

            <!-- Cabeçalho -->
            <div
                class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4"
            >

                <div class="flex-grow-1">

                    <h3 class="fw-bold m-0">

                        <i class="bi bi-pencil-square me-2 text-primary"></i>

                        Editar Usuário

                    </h3>

                    <p class="text-muted m-0 small">
                        Atualize as informações cadastrais
                    </p>

                </div>

                <div>

                    <a
                        href="/clientes"
                        class="btn btn-outline-secondary btn-sm fw-bold text-nowrap"
                    >
                        <i class="bi bi-arrow-left me-1"></i>
                        Voltar para Clientes
                    </a>

                </div>

            </div>

            <!-- Mensagem de sucesso -->
            <?php if ($sucesso !== ''): ?>

                <div
                    class="alert alert-success alert-dismissible fade show border-0 shadow-sm"
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
                        aria-label="Fechar"
                    ></button>

                </div>

            <?php endif; ?>

            <!-- Mensagem de erro -->
            <?php if ($erro !== ''): ?>

                <div
                    class="alert alert-danger alert-dismissible fade show border-0 shadow-sm"
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
                        aria-label="Fechar"
                    ></button>

                </div>

            <?php endif; ?>

            <!-- Formulário -->
            <div class="card border-0 shadow-sm p-3 p-md-4">

                <form
                    method="POST"
                    action=""
                    autocomplete="off"
                >

                    <div class="row g-3">

                        <!-- Nome -->
                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                Nome Completo *
                            </label>

                            <input
                                type="text"
                                name="nome"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $cliente['nome'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="255"
                                required
                            >

                        </div>

                        <!-- CPF -->
                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                CPF (Login) *
                            </label>

                            <input
                                type="text"
                                name="cpf"
                                class="form-control mask-cpf"
                                inputmode="numeric"
                                value="<?= htmlspecialchars(
                                    $cliente['cpf'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="14"
                                required
                            >

                        </div>

                        <!-- E-mail -->
                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                E-mail
                            </label>

                            <input
                                type="email"
                                name="email"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $cliente['email'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="255"
                            >

                        </div>

                        <!-- Telefone -->
                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                Telefone / WhatsApp
                            </label>

                            <input
                                type="text"
                                name="telefone"
                                class="form-control mask-phone"
                                inputmode="numeric"
                                value="<?= htmlspecialchars(
                                    $cliente['telefone'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="20"
                            >

                        </div>

                        <!-- Endereço -->
                        <div class="col-12">

                            <label class="form-label fw-bold">
                                Endereço Residencial
                            </label>

                            <input
                                type="text"
                                name="endereco"
                                class="form-control"
                                value="<?= htmlspecialchars(
                                    $cliente['endereco'] ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="500"
                            >

                        </div>

                        <!-- Separador -->
                        <div class="col-12">

                            <hr class="my-3">

                        </div>

                        <!-- Senha -->
                        <div class="col-12">

                            <h6 class="fw-bold text-muted m-0">

                                <i class="bi bi-key me-1"></i>

                                Redefinir Senha de Acesso

                            </h6>

                            <small class="text-muted d-block mt-1">
                                Preencha apenas se desejar alterar a senha do usuário.
                            </small>

                        </div>

                        <div class="col-md-6">

                            <label class="form-label fw-bold">
                                Nova Senha
                            </label>

                            <input
                                type="password"
                                name="nova_senha"
                                class="form-control"
                                placeholder="Deixe em branco para manter a atual"
                                autocomplete="new-password"
                            >

                        </div>

                        <!-- Botão -->
                        <div class="col-12 mt-4">

                            <div class="d-flex flex-column flex-sm-row justify-content-end gap-2">

                                <a
                                    href="/clientes"
                                    class="btn btn-outline-secondary fw-bold"
                                >
                                    <i class="bi bi-x-lg me-1"></i>
                                    Cancelar
                                </a>

                                <button
                                    type="submit"
                                    class="btn btn-primary fw-bold px-4"
                                >
                                    <i class="bi bi-check-lg me-1"></i>
                                    Salvar Alterações
                                </button>

                            </div>

                        </div>

                    </div>

                </form>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
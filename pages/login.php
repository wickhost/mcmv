<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| Se já estiver logado, redireciona para a área correspondente
|--------------------------------------------------------------------------
*/
if (!empty($_SESSION['usuario_id'])) {

    if (($_SESSION['usuario_tipo'] ?? '') === 'cliente') {
        header('Location: /portal');
        exit;
    }

    header('Location: /dashboard');
    exit;
}

$erro = '';

/*
|--------------------------------------------------------------------------
| Processar login
|--------------------------------------------------------------------------
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $cpf = trim($_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($cpf === '' || $senha === '') {

        $erro = 'Informe o CPF e a senha.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Normalizar CPF
        |--------------------------------------------------------------------------
        */
        $cpfLimpo = preg_replace('/[^0-9]/', '', $cpf);

        if (strlen($cpfLimpo) !== 11) {

            $erro = 'Informe um CPF válido.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Buscar usuário pelo CPF
            |--------------------------------------------------------------------------
            */
            $stmt = $pdo->prepare("
                SELECT
                    id,
                    nome,
                    cpf,
                    senha,
                    tipo
                FROM usuarios
                WHERE REPLACE(
                    REPLACE(
                        REPLACE(cpf, '.', ''),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ) = ?
                LIMIT 1
            ");

            $stmt->execute([$cpfLimpo]);

            $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

            /*
            |--------------------------------------------------------------------------
            | Validar senha
            |--------------------------------------------------------------------------
            */
            if (
                !$usuario ||
                !password_verify($senha, $usuario['senha'])
            ) {

                $erro = 'CPF ou senha inválidos.';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Regenerar sessão após login
                |--------------------------------------------------------------------------
                */
                session_regenerate_id(true);

                $_SESSION['usuario_id'] = (int)$usuario['id'];
                $_SESSION['usuario_nome'] = $usuario['nome'];
                $_SESSION['usuario_cpf'] = $usuario['cpf'];
                $_SESSION['usuario_tipo'] = $usuario['tipo'];

                /*
                |--------------------------------------------------------------------------
                | Redirecionamento
                |--------------------------------------------------------------------------
                */
                if ($usuario['tipo'] === 'cliente') {

                    header('Location: /portal');
                    exit;

                }

                header('Location: /dashboard');
                exit;
            }
        }
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-4 my-md-5">

    <div class="row justify-content-center">

        <div class="col-12 col-sm-10 col-md-6 col-lg-4">

            <div class="card border-0 shadow-sm">

                <div class="card-body p-4 p-md-5">

                    <!-- Cabeçalho -->
                    <div class="text-center mb-4">

                        <div class="mb-3">

                            <span
                                class="d-inline-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-circle"
                                style="width: 70px; height: 70px;"
                            >
                                <i class="bi bi-person-lock fs-2"></i>
                            </span>

                        </div>

                        <h3 class="fw-bold m-0">
                            Acesso ao Sistema
                        </h3>

                        <p class="text-muted small mb-0 mt-1">
                            Entre com seus dados para continuar
                        </p>

                    </div>

                    <!-- Erro -->
                    <?php if ($erro !== ''): ?>

                        <div
                            class="alert alert-danger alert-dismissible fade show border-0"
                            role="alert"
                        >

                            <i class="bi bi-exclamation-triangle me-1"></i>

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
                    <form
                        method="POST"
                        action=""
                        autocomplete="on"
                    >

                        <!-- CPF -->
                        <div class="mb-3">

                            <label
                                for="cpf"
                                class="form-label fw-bold"
                            >
                                CPF
                            </label>

                            <input
                                type="text"
                                id="cpf"
                                name="cpf"
                                class="form-control form-control-lg mask-cpf"
                                inputmode="numeric"
                                autocomplete="username"
                                value="<?= htmlspecialchars(
                                    $cpf ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                                maxlength="14"
                                required
                                autofocus
                            >

                        </div>

                        <!-- Senha -->
                        <div class="mb-4">

                            <label
                                for="senha"
                                class="form-label fw-bold"
                            >
                                Senha
                            </label>

                            <input
                                type="password"
                                id="senha"
                                name="senha"
                                class="form-control form-control-lg"
                                autocomplete="current-password"
                                required
                            >

                        </div>

                        <!-- Botão -->
                        <button
                            type="submit"
                            class="btn btn-primary btn-lg fw-bold w-100"
                        >
                            <i class="bi bi-box-arrow-in-right me-1"></i>
                            Entrar
                        </button>

                    </form>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
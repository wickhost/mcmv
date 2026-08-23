<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$usuario_nome = $_SESSION['usuario_nome'] ?? 'Usuário';
$usuario_tipo = $_SESSION['usuario_tipo'] ?? 'cliente';

$request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$pagina_atual = trim(
    is_string($request_path) ? $request_path : '/',
    '/'
);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gestão de Obras MCMV</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css"
    >

    <link
        rel="stylesheet"
        href="/assets/css/style.css"
    >

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        defer
    ></script>
</head>

<body class="d-flex flex-column min-vh-100">

<nav class="navbar navbar-expand-lg navbar-dark navbar-custom sticky-top">
    <div class="container">

        <a
            class="navbar-brand"
            href="<?= $usuario_tipo === 'admin' ? '/dashboard' : '/portal' ?>"
        >
            <i class="bi bi-building-fill"></i>
            Gestão de Obras
        </a>

        <button
            class="navbar-toggler border-0"
            type="button"
            data-bs-toggle="collapse"
            data-bs-target="#navbarMain"
            aria-controls="navbarMain"
            aria-expanded="false"
            aria-label="Alternar navegação"
        >
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarMain">

            <?php if (isset($_SESSION['usuario_id'])): ?>

                <ul class="navbar-nav me-auto mb-2 mb-lg-0 mt-2 mt-lg-0">

                    <?php if ($usuario_tipo === 'admin'): ?>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= $pagina_atual === 'dashboard' ? 'active' : '' ?>"
                                href="/dashboard"
                            >
                                <i class="bi bi-speedometer2"></i>
                                Dashboard
                            </a>
                        </li>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= $pagina_atual === 'clientes' ? 'active' : '' ?>"
                                href="/clientes"
                            >
                                <i class="bi bi-people-fill"></i>
                                Clientes
                            </a>
                        </li>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= in_array(
                                    $pagina_atual,
                                    ['modelos', 'modelo-novo', 'modelo-editar'],
                                    true
                                ) ? 'active' : '' ?>"
                                href="/modelos"
                            >
                                <i class="bi bi-house-door-fill"></i>
                                Modelos
                            </a>
                        </li>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= $pagina_atual === 'nova-obra' ? 'active' : '' ?>"
                                href="/nova-obra"
                            >
                                <i class="bi bi-plus-circle-fill"></i>
                                Nova Obra
                            </a>
                        </li>

                    <?php else: ?>

                        <li class="nav-item">
                            <a
                                class="nav-link <?= $pagina_atual === 'portal' ? 'active' : '' ?>"
                                href="/portal"
                            >
                                <i class="bi bi-house-heart-fill"></i>
                                Minha Obra
                            </a>
                        </li>

                    <?php endif; ?>

                </ul>

                <div class="d-flex align-items-lg-center gap-2 user-menu-mobile">

                    <a
                        href="/meu-perfil"
                        class="btn-user-profile d-inline-flex align-items-center gap-2"
                    >
                        <i class="bi bi-person-circle"></i>

                        <?= htmlspecialchars(
                            $usuario_nome,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>
                    </a>

                    <a
                        href="/logout"
                        class="btn btn-outline-light btn-sm btn-logout d-inline-flex align-items-center gap-1"
                    >
                        <i class="bi bi-box-arrow-right"></i>
                        Sair
                    </a>

                </div>

            <?php endif; ?>

        </div>
    </div>
</nav>

<main class="flex-grow-1 py-4">
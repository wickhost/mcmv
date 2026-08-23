<?php
session_start();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$route = trim($request, '/');

// Se o usuário já estiver logado e tentar acessar a raiz '/', '/login' ou '/hlogin'
if (isset($_SESSION['usuario_id']) && in_array($route, ['', 'login', 'hlogin'])) {
    if ($_SESSION['usuario_tipo'] === 'admin') {
        header('Location: /dashboard');
    } else {
        header('Location: /portal');
    }
    exit;
}

switch ($route) {
    case '':
    case 'login':
        require __DIR__ . '/pages/login.php';
        break;

    case 'hlogin':
        require __DIR__ . '/pages/admin-login.php';
        break;

    case 'dashboard':
        require __DIR__ . '/pages/dashboard.php';
        break;

    case 'clientes':
        require __DIR__ . '/pages/clientes.php';
        break;

    case 'cliente-editar':
        require __DIR__ . '/pages/cliente-editar.php';
        break;
		
    case 'cliente-novo':
        require __DIR__ . '/pages/cliente-novo.php';
        break;

    case 'modelos':
        require __DIR__ . '/pages/modelos.php';
        break;

    case 'modelo-novo':
        require __DIR__ . '/pages/modelo-novo.php';
        break;

    case 'modelo-editar':
        require __DIR__ . '/pages/modelo-editar.php';
        break;

    case 'nova-obra':
        require __DIR__ . '/pages/nova-obra.php';
        break;

    case 'gerenciar-obra':
        require __DIR__ . '/pages/gerenciar-obra.php';
        break;

    case 'obra-editar':
        require __DIR__ . '/pages/obra-editar.php';
        break;

    case 'portal':
        require __DIR__ . '/pages/portal-cliente.php';
        break;

    case 'relatorio-obra':
        require __DIR__ . '/pages/relatorio-obra.php';
        break;

    case 'meu-perfil':
        require __DIR__ . '/pages/meu-perfil.php';
        break;

    case 'logout':
        require __DIR__ . '/pages/logout.php';
        break;

    default:
        http_response_code(404);
        require __DIR__ . '/pages/404.php';
        break;
}
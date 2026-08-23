<?php
http_response_code(404);

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container my-5 text-center">
    <div class="card border-0 shadow-sm p-4 p-md-5 mx-auto" style="max-width: 550px;">

        <div class="mb-3">
            <i class="bi bi-exclamation-triangle-fill text-warning display-1"></i>
        </div>

        <h1 class="fw-bold text-dark mb-2">404</h1>

        <h4 class="fw-semibold text-secondary mb-3">
            Página não encontrada
        </h4>

        <p class="text-muted small mb-4">
            O endereço que você tentou acessar não existe, foi removido ou está temporariamente indisponível.
        </p>

        <div>
            <a href="/dashboard" class="btn btn-primary fw-bold px-4 py-2">
                <i class="bi bi-house-door-fill me-1"></i>
                Voltar ao Início
            </a>
        </div>

    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
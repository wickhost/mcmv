<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

verificarAdmin();

$acao = $_GET['acao'] ?? '';

$id = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
) ?: 0;

$obraId = filter_input(
    INPUT_GET,
    'obra_id',
    FILTER_VALIDATE_INT
) ?: 0;

if (!$id || !$obraId) {
    header('Location: /dashboard');
    exit;
}

if ($acao === 'excluir_foto') {
    $stmt = $pdo->prepare("
        SELECT caminho_foto
        FROM obra_fotos
        WHERE id = ?
          AND obra_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $obraId
    ]);

    $foto = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($foto) {
        $nomeArquivo = basename(
            $foto['caminho_foto']
        );

        $arquivo = __DIR__ .
            '/../uploads/fotos/' .
            $nomeArquivo;

        if (is_file($arquivo)) {
            @unlink($arquivo);
        }

        $stmtDel = $pdo->prepare("
            DELETE FROM obra_fotos
            WHERE id = ?
              AND obra_id = ?
        ");

        $stmtDel->execute([
            $id,
            $obraId
        ]);
    }

    header(
        "Location: /gerenciar-obra?id=" .
        $obraId .
        "&sucesso=foto_del"
    );
    exit;
}

if ($acao === 'excluir_doc') {
    $stmt = $pdo->prepare("
        SELECT caminho_arquivo
        FROM obra_documentos
        WHERE id = ?
          AND obra_id = ?
        LIMIT 1
    ");

    $stmt->execute([
        $id,
        $obraId
    ]);

    $documento = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($documento) {
        $nomeArquivo = basename(
            $documento['caminho_arquivo']
        );

        $arquivo = __DIR__ .
            '/../uploads/documentos/' .
            $nomeArquivo;

        if (is_file($arquivo)) {
            @unlink($arquivo);
        }

        $stmtDel = $pdo->prepare("
            DELETE FROM obra_documentos
            WHERE id = ?
              AND obra_id = ?
        ");

        $stmtDel->execute([
            $id,
            $obraId
        ]);
    }

    header(
        "Location: /gerenciar-obra?id=" .
        $obraId .
        "&sucesso=doc_del"
    );
    exit;
}

header(
    "Location: /gerenciar-obra?id=" .
    $obraId
);
exit;
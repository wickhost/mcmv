<?php
require_once __DIR__ . '/../includes/auth.php';
verificarAdmin();

$acao = $_GET['acao'] ?? null;
$id = (int)($_GET['id'] ?? 0);
$obra_id = (int)($_GET['obra_id'] ?? 0);

if (!$id || !$obra_id) {
    header('Location: /dashboard');
    exit;
}

// Excluir Foto da Obra
if ($acao === 'excluir_foto') {
    $stmt = $pdo->prepare("SELECT caminho_foto FROM obra_fotos WHERE id = ? AND obra_id = ?");
    $stmt->execute([$id, $obra_id]);
    $foto = $stmt->fetch();

    if ($foto) {
        $arquivo = __DIR__ . '/../uploads/fotos/' . $foto['caminho_foto'];
        if (file_exists($arquivo)) {
            @unlink($arquivo);
        }
        $stmtDel = $pdo->prepare("DELETE FROM obra_fotos WHERE id = ?");
        $stmtDel->execute([$id]);
    }
}

// Excluir Documento da Obra
if ($acao === 'excluir_doc') {
    $stmt = $pdo->prepare("SELECT caminho_arquivo FROM obra_documentos WHERE id = ? AND obra_id = ?");
    $stmt->execute([$id, $obra_id]);
    $doc = $stmt->fetch();

    if ($doc) {
        $arquivo = __DIR__ . '/../uploads/documentos/' . $doc['caminho_arquivo'];
        if (file_exists($arquivo)) {
            @unlink($arquivo);
        }
        $stmtDel = $pdo->prepare("DELETE FROM obra_documentos WHERE id = ?");
        $stmtDel->execute([$id]);
    }
}

header("Location: /gerenciar-obra?id={$obra_id}");
exit;
<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$produtoId = isset($_GET['produto']) ? (int)$_GET['produto'] : 0;

$token = trim($token);

if ($token === '' || $produtoId <= 0) {
    http_response_code(400);
    exit('Requisição inválida.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM clientes_acesso
    WHERE token = ?
      AND status = 'ativo'
    LIMIT 1
");
$stmt->execute([$token]);
$cliente = $stmt->fetch();

if (!$cliente) {
    http_response_code(403);
    exit('Acesso inválido ou bloqueado.');
}

$stmt = $pdo->prepare("
    SELECT *
    FROM produtos_digitais
    WHERE id = ?
      AND ativo = TRUE
    LIMIT 1
");
$stmt->execute([$produtoId]);
$produto = $stmt->fetch();

if (!$produto) {
    http_response_code(404);
    exit('Produto não encontrado.');
}

$arquivo = basename((string)$produto['arquivo']);
$caminhoBase = realpath(__DIR__ . '/arquivos');
$caminhoArquivo = realpath(__DIR__ . '/arquivos/' . $arquivo);

if (!$caminhoBase || !$caminhoArquivo || strpos($caminhoArquivo, $caminhoBase) !== 0 || !is_file($caminhoArquivo)) {
    http_response_code(404);
    exit('Arquivo não encontrado.');
}

$ip = $_SERVER['REMOTE_ADDR'] ?? '';
$navegador = $_SERVER['HTTP_USER_AGENT'] ?? '';

$stmt = $pdo->prepare("
    INSERT INTO downloads_log (cliente_id, produto_id, ip, navegador)
    VALUES (?, ?, ?, ?)
");
$stmt->execute([
    (int)$cliente['id'],
    (int)$produto['id'],
    $ip,
    $navegador
]);

$stmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM downloads_log
    WHERE cliente_id = ?
      AND produto_id = ?
");
$stmt->execute([
    (int)$cliente['id'],
    (int)$produto['id']
]);

$totalDownloads = (int)$stmt->fetch()['total'];

if ($totalDownloads >= 5) {
    $stmt = $pdo->prepare("
        SELECT id
        FROM alertas_download
        WHERE cliente_id = ?
          AND produto_id = ?
        LIMIT 1
    ");
    $stmt->execute([
        (int)$cliente['id'],
        (int)$produto['id']
    ]);

    $alertaExistente = $stmt->fetch();

    if (!$alertaExistente) {
        $assunto = 'Alerta: 5 downloads do mesmo produto';

        $mensagem = "Alerta de download\n\n";
        $mensagem .= "Cliente: " . ($cliente['nome'] ?: 'Não informado') . "\n";
        $mensagem .= "Email: " . ($cliente['email'] ?: 'Não informado') . "\n";
        $mensagem .= "Pedido: " . ($cliente['pedido'] ?: 'Não informado') . "\n";
        $mensagem .= "Origem: " . ($cliente['origem'] ?: 'Não informado') . "\n";
        $mensagem .= "Produto: " . ($produto['titulo'] ?: 'Não informado') . "\n";
        $mensagem .= "Arquivo: " . $arquivo . "\n";
        $mensagem .= "Total de downloads: " . $totalDownloads . "\n";
        $mensagem .= "IP atual: " . $ip . "\n";
        $mensagem .= "Navegador: " . $navegador . "\n";
        $mensagem .= "Data: " . date('d/m/Y H:i:s') . "\n\n";
        $mensagem .= "Verifique no painel administrativo se há comportamento suspeito.";

        $headers = "From: Sistema Farmacia Natural <naoresponder@enilton.com.br>\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

        $emailEnviado = false;

        if (!empty($emailAdmin)) {
            $mailData = json_encode([
                'to' => $emailAdmin,
                'subject' => $assunto,
                'message' => $mensagem,
                'headers' => $headers
            ]);

            // In web environments, PHP_BINARY might point to php-fpm which doesn't support -r.
            // Using a separate script executed via 'php' ensures CLI execution.
            // printf '%s' is used instead of echo to avoid backslash evaluation issues in some shells.
            // Piped into stdin to avoid process list exposure and ARG_MAX limits.
            $cmd = 'printf \'%s\' ' . escapeshellarg($mailData) . ' | php ' . escapeshellarg(__DIR__ . '/send_mail_bg.php') . ' > /dev/null 2>&1 &';
            exec($cmd);
            $emailEnviado = true; // Assume success for log as it's fire-and-forget
        }

        $stmt = $pdo->prepare("
            INSERT INTO alertas_download
                (cliente_id, produto_id, total_downloads, alerta_enviado, enviado_em)
            VALUES
                (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            (int)$cliente['id'],
            (int)$produto['id'],
            $totalDownloads,
            $emailEnviado
        ]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE alertas_download
            SET total_downloads = ?
            WHERE cliente_id = ?
              AND produto_id = ?
        ");
        $stmt->execute([
            $totalDownloads,
            (int)$cliente['id'],
            (int)$produto['id']
        ]);
    }
}

$nomeDownload = preg_replace('/[^a-zA-Z0-9._-]/', '-', $arquivo);

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nomeDownload . '"');
header('Content-Length: ' . filesize($caminhoArquivo));
header('Cache-Control: private, no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

readfile($caminhoArquivo);
exit;
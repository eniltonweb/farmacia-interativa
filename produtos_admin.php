<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function nomeArquivoSeguro(string $nomeOriginal): string
{
    $nome = strtolower(trim($nomeOriginal));
    $nome = preg_replace('/[^a-z0-9._-]/', '-', $nome);
    $nome = preg_replace('/-+/', '-', $nome);
    return $nome ?: 'arquivo.pdf';
}

if (empty($_SESSION['csrf_produtos'])) {
    $_SESSION['csrf_produtos'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_produtos'];
$mensagem = '';
$erro = '';

$pastaArquivos = __DIR__ . '/arquivos/';

if (!is_dir($pastaArquivos)) {
    $erro = 'A pasta /arquivos/ não existe.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRecebido = $_POST['csrf'] ?? '';

    if (!hash_equals($csrf, (string)$csrfRecebido)) {
        $erro = 'Falha de segurança. Atualize a página e tente novamente.';
    } else {
        $acao = $_POST['acao'] ?? '';

        try {
            if ($acao === 'criar') {
                $titulo = trim($_POST['titulo'] ?? '');
                $arquivoManual = trim($_POST['arquivo_manual'] ?? '');

                if ($titulo === '') {
                    throw new RuntimeException('Informe o título do produto.');
                }

                $nomeArquivoFinal = '';

                if (!empty($_FILES['arquivo_pdf']['name'])) {
                    if ($_FILES['arquivo_pdf']['error'] !== UPLOAD_ERR_OK) {
                        throw new RuntimeException('Erro no upload do PDF.');
                    }

                    $extensao = strtolower(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_EXTENSION));

                    if ($extensao !== 'pdf') {
                        throw new RuntimeException('Envie apenas arquivo PDF.');
                    }

                    $nomeArquivoFinal = nomeArquivoSeguro(pathinfo($_FILES['arquivo_pdf']['name'], PATHINFO_FILENAME)) . '-' . date('YmdHis') . '.pdf';
                    $destino = $pastaArquivos . $nomeArquivoFinal;

                    if (!move_uploaded_file($_FILES['arquivo_pdf']['tmp_name'], $destino)) {
                        throw new RuntimeException('Não foi possível salvar o PDF no servidor.');
                    }
                } elseif ($arquivoManual !== '') {
                    $nomeArquivoFinal = basename($arquivoManual);
                } else {
                    throw new RuntimeException('Envie um PDF ou informe o nome do arquivo já existente.');
                }

                $stmt = $pdo->prepare("
                    INSERT INTO produtos_digitais (titulo, arquivo, ativo)
                    VALUES (?, ?, TRUE)
                ");
                $stmt->execute([$titulo, $nomeArquivoFinal]);

                $mensagem = 'Produto cadastrado com sucesso.';
            }

            if ($acao === 'editar') {
                $id = (int)($_POST['id'] ?? 0);
                $titulo = trim($_POST['titulo'] ?? '');
                $arquivo = trim($_POST['arquivo'] ?? '');

                if ($id <= 0) {
                    throw new RuntimeException('Produto inválido.');
                }

                if ($titulo === '') {
                    throw new RuntimeException('Informe o título.');
                }

                if ($arquivo === '') {
                    throw new RuntimeException('Informe o nome do arquivo.');
                }

                $arquivo = basename($arquivo);

                $stmt = $pdo->prepare("
                    UPDATE produtos_digitais
                    SET titulo = ?, arquivo = ?
                    WHERE id = ?
                ");
                $stmt->execute([$titulo, $arquivo, $id]);

                $mensagem = 'Produto atualizado com sucesso.';
            }

            if ($acao === 'alternar_status') {
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new RuntimeException('Produto inválido.');
                }

                $stmt = $pdo->prepare("
                    UPDATE produtos_digitais
                    SET ativo = NOT ativo
                    WHERE id = ?
                ");
                $stmt->execute([$id]);

                $mensagem = 'Status do produto alterado com sucesso.';
            }

            if ($acao === 'excluir') {
                $id = (int)($_POST['id'] ?? 0);

                if ($id <= 0) {
                    throw new RuntimeException('Produto inválido.');
                }

                $stmt = $pdo->prepare("
                    SELECT COUNT(*) AS total
                    FROM downloads_log
                    WHERE produto_id = ?
                ");
                $stmt->execute([$id]);
                $totalDownloads = (int)$stmt->fetch()['total'];

                if ($totalDownloads > 0) {
                    $stmt = $pdo->prepare("
                        UPDATE produtos_digitais
                        SET ativo = FALSE
                        WHERE id = ?
                    ");
                    $stmt->execute([$id]);

                    $mensagem = 'Este produto já possui downloads. Ele foi desativado, não excluído, para preservar o histórico.';
                } else {
                    $stmt = $pdo->prepare("
                        DELETE FROM produtos_digitais
                        WHERE id = ?
                    ");
                    $stmt->execute([$id]);

                    $mensagem = 'Produto excluído com sucesso.';
                }
            }
        } catch (Throwable $e) {
            $erro = $e->getMessage();
        }
    }
}

$stmt = $pdo->query("
    SELECT
        p.id,
        p.titulo,
        p.arquivo,
        p.ativo,
        COALESCE(COUNT(d.id), 0) AS total_downloads,
        MAX(d.baixado_em) AS ultimo_download
    FROM produtos_digitais p
    LEFT JOIN downloads_log d ON d.produto_id = p.id
    GROUP BY p.id, p.titulo, p.arquivo, p.ativo
    ORDER BY p.id ASC
");
$produtos = $stmt->fetchAll();

$arquivosServidor = [];

if (is_dir($pastaArquivos)) {
    $lista = glob(rtrim($pastaArquivos, '/\\') . DIRECTORY_SEPARATOR . '*.[pP][dD][fF]');

    if ($lista !== false) {
        foreach ($lista as $caminho) {
            $arquivosServidor[] = basename($caminho);
        }
    }

    sort($arquivosServidor);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Gerenciar Produtos Digitais</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            background: #f7f2e9;
            color: #23302a;
            font-family: Arial, Helvetica, sans-serif;
            padding: 20px;
        }

        .container {
            max-width: 1250px;
            margin: auto;
        }

        .topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        h1, h2 {
            color: #123f31;
            margin-top: 0;
        }

        .nav a {
            color: white;
            background: #123f31;
            padding: 10px 14px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            margin-left: 6px;
            display: inline-block;
        }

        .card {
            background: white;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
            margin-bottom: 22px;
            border: 1px solid rgba(18,63,49,.08);
        }

        .grid {
            display: grid;
            grid-template-columns: 1.2fr 1fr;
            gap: 18px;
        }

        label {
            display: block;
            font-weight: bold;
            color: #123f31;
            margin-bottom: 7px;
        }

        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #d8d2c6;
            border-radius: 12px;
            font-size: 15px;
            margin-bottom: 14px;
        }

        button {
            border: none;
            border-radius: 10px;
            padding: 10px 13px;
            font-weight: bold;
            cursor: pointer;
            font-size: 14px;
        }

        .btn-primary {
            background: #123f31;
            color: white;
        }

        .btn-warning {
            background: #c49a3a;
            color: #171b14;
        }

        .btn-danger {
            background: #9e3428;
            color: white;
        }

        .btn-muted {
            background: #e8e2d6;
            color: #23302a;
        }

        .mensagem {
            background: #eef8f1;
            border-left: 5px solid #1b5b47;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .erro {
            background: #ffe8e4;
            border-left: 5px solid #9e3428;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 16px;
            color: #8d2b1f;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            padding: 10px;
            border-bottom: 1px solid #eee5d8;
            text-align: left;
            font-size: 14px;
            vertical-align: top;
        }

        th {
            background: #123f31;
            color: white;
        }

        .scroll {
            overflow-x: auto;
        }

        .tag {
            display: inline-block;
            padding: 5px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: bold;
        }

        .ativo {
            background: #e8f7ed;
            color: #176b38;
        }

        .inativo {
            background: #ffe8e4;
            color: #9e3428;
        }

        .arquivo {
            font-family: monospace;
            font-size: 13px;
            color: #4f5f56;
        }

        .acoes {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }

        .form-inline {
            display: inline;
        }

        .aviso {
            background: #fff8e8;
            border-left: 5px solid #c49a3a;
            padding: 14px;
            border-radius: 12px;
            color: #4b4332;
            margin-bottom: 16px;
        }

        .lista-arquivos {
            max-height: 270px;
            overflow: auto;
            background: #fffdfa;
            border: 1px solid #e2dacb;
            border-radius: 12px;
            padding: 12px;
        }

        .lista-arquivos div {
            font-family: monospace;
            font-size: 13px;
            padding: 6px 0;
            border-bottom: 1px solid #eee5d8;
        }

        .lista-arquivos div:last-child {
            border-bottom: none;
        }

        @media(max-width: 900px) {
            .grid {
                grid-template-columns: 1fr;
            }

            .topo {
                flex-direction: column;
                align-items: flex-start;
            }

            .nav a {
                margin: 4px 4px 0 0;
            }
        }
    </style>
</head>
<body>

<div class="container">

    <div class="topo">
        <h1>Gerenciar Produtos Digitais</h1>

        <div class="nav">
            <a href="painel_admin.php">Painel</a>
            <a href="cadastrar_cliente.php">Cadastrar cliente</a>
            <a href="admin_logout.php">Sair</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="mensagem"><?= e($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <div class="aviso">
        Para remover um produto da página do cliente, use <strong>Desativar</strong>. Excluir definitivo só é seguro quando o produto ainda não teve downloads.
    </div>

    <div class="grid">
        <div class="card">
            <h2>Cadastrar novo produto</h2>

            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="acao" value="criar">

                <label>Título do produto</label>
                <input type="text" name="titulo" placeholder="Ex: Novo bônus em PDF" required>

                <label>Enviar PDF</label>
                <input type="file" name="arquivo_pdf" accept="application/pdf">

                <label>Ou informar nome do arquivo já existente na pasta /arquivos/</label>
                <input type="text" name="arquivo_manual" placeholder="Ex: meu-bonus.pdf">

                <button type="submit" class="btn-primary">Cadastrar produto</button>
            </form>
        </div>

        <div class="card">
            <h2>PDFs encontrados no servidor</h2>

            <?php if (!$arquivosServidor): ?>
                <p>Nenhum PDF encontrado na pasta /arquivos/.</p>
            <?php else: ?>
                <div class="lista-arquivos">
                    <?php foreach ($arquivosServidor as $arquivo): ?>
                        <div><?= e($arquivo) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <h2>Produtos cadastrados</h2>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Arquivo</th>
                        <th>Status</th>
                        <th>Downloads</th>
                        <th>Último download</th>
                        <th>Editar</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$produtos): ?>
                        <tr>
                            <td colspan="8">Nenhum produto cadastrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($produtos as $produto): ?>
                        <tr>
                            <td><?= (int)$produto['id'] ?></td>

                            <td><?= e($produto['titulo']) ?></td>

                            <td class="arquivo"><?= e($produto['arquivo']) ?></td>

                            <td>
                                <?php if ($produto['ativo']): ?>
                                    <span class="tag ativo">Ativo</span>
                                <?php else: ?>
                                    <span class="tag inativo">Inativo</span>
                                <?php endif; ?>
                            </td>

                            <td><strong><?= (int)$produto['total_downloads'] ?></strong></td>

                            <td><?= e($produto['ultimo_download']) ?></td>

                            <td>
                                <form method="post">
                                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="acao" value="editar">
                                    <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

                                    <label>Título</label>
                                    <input type="text" name="titulo" value="<?= e($produto['titulo']) ?>" required>

                                    <label>Arquivo</label>
                                    <input type="text" name="arquivo" value="<?= e($produto['arquivo']) ?>" required>

                                    <button type="submit" class="btn-primary">Salvar</button>
                                </form>
                            </td>

                            <td>
                                <div class="acoes">
                                    <form method="post" class="form-inline">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="acao" value="alternar_status">
                                        <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

                                        <?php if ($produto['ativo']): ?>
                                            <button type="submit" class="btn-warning">Desativar</button>
                                        <?php else: ?>
                                            <button type="submit" class="btn-muted">Ativar</button>
                                        <?php endif; ?>
                                    </form>

                                    <form method="post" class="form-inline" onsubmit="return confirm('Tem certeza? Se houver downloads, o sistema apenas desativa para preservar histórico.');">
                                        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int)$produto['id'] ?>">

                                        <button type="submit" class="btn-danger">Excluir</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</body>
</html>
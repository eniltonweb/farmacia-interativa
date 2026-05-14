<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_limpar_testes'])) {
    $_SESSION['csrf_limpar_testes'] = bin2hex(random_bytes(32));
}

$csrf = $_SESSION['csrf_limpar_testes'];
$mensagem = '';
$erro = '';

function contarRegistros(PDO $pdo): array
{
    $stmt = $pdo->query("
        SELECT
            (SELECT COUNT(*) FROM clientes_acesso) AS clientes_acesso,
            (SELECT COUNT(*) FROM downloads_log) AS downloads_log,
            (SELECT COUNT(*) FROM alertas_download) AS alertas_download,
            (SELECT COUNT(*) FROM produtos_digitais) AS produtos_digitais
    ");
    $counts = $stmt->fetch();

    return [
        'clientes_acesso' => (int)($counts['clientes_acesso'] ?? 0),
        'downloads_log' => (int)($counts['downloads_log'] ?? 0),
        'alertas_download' => (int)($counts['alertas_download'] ?? 0),
        'produtos_digitais' => (int)($counts['produtos_digitais'] ?? 0),
    ];
}

$antes = contarRegistros($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfRecebido = $_POST['csrf'] ?? '';
    $confirmacao = trim($_POST['confirmacao'] ?? '');

    if (!hash_equals($csrf, (string)$csrfRecebido)) {
        $erro = 'Falha de segurança. Atualize a página e tente novamente.';
    } elseif ($confirmacao !== 'LIMPAR') {
        $erro = 'Digite LIMPAR para confirmar.';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->exec("
                TRUNCATE TABLE
                    alertas_download,
                    downloads_log,
                    clientes_acesso
                RESTART IDENTITY CASCADE
            ");

            $pdo->commit();

            $mensagem = 'Base de testes limpa com sucesso. Clientes, downloads e alertas foram zerados. Produtos digitais foram mantidos.';
            $antes = contarRegistros($pdo);

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $erro = 'Ocorreu um erro interno ao limpar a base de dados.';
        }
    }
}

$depois = contarRegistros($pdo);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Limpar Dados de Teste</title>
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
            padding: 24px;
        }

        .container {
            max-width: 900px;
            margin: auto;
        }

        .topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 22px;
        }

        h1 {
            color: #123f31;
            margin: 0;
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
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
            margin-bottom: 22px;
            border: 1px solid rgba(18,63,49,.08);
        }

        .aviso {
            background: #fff8e8;
            border-left: 5px solid #c49a3a;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            color: #4b4332;
        }

        .erro {
            background: #ffe8e4;
            border-left: 5px solid #9e3428;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            color: #8d2b1f;
        }

        .sucesso {
            background: #eef8f1;
            border-left: 5px solid #1b5b47;
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 18px;
            color: #176b38;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 14px;
        }

        th, td {
            border-bottom: 1px solid #eee5d8;
            padding: 12px;
            text-align: left;
        }

        th {
            background: #123f31;
            color: white;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d8d2c6;
            border-radius: 12px;
            font-size: 16px;
            margin: 8px 0 14px;
        }

        button {
            background: #9e3428;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #84291f;
        }

        .mantido {
            color: #176b38;
            font-weight: bold;
        }

        .zerado {
            color: #9e3428;
            font-weight: bold;
        }

        @media(max-width: 700px) {
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
        <h1>Limpar Dados de Teste</h1>

        <div class="nav">
            <a href="painel_admin.php">Painel</a>
            <a href="cadastrar_cliente.php">Cadastrar cliente</a>
            <a href="produtos_admin.php">Produtos</a>
            <a href="admin_logout.php">Sair</a>
        </div>
    </div>

    <?php if ($mensagem): ?>
        <div class="sucesso"><?= e($mensagem) ?></div>
    <?php endif; ?>

    <?php if ($erro): ?>
        <div class="erro"><?= e($erro) ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="aviso">
            Esta limpeza remove apenas dados de teste: clientes cadastrados, registros de download e alertas.
            <br><br>
            Os produtos digitais cadastrados serão mantidos.
        </div>

        <h2>Situação atual da base</h2>

        <table>
            <thead>
                <tr>
                    <th>Tabela</th>
                    <th>Registros</th>
                    <th>Ação</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>clientes_acesso</td>
                    <td><?= (int)$depois['clientes_acesso'] ?></td>
                    <td class="zerado">Será zerada</td>
                </tr>
                <tr>
                    <td>downloads_log</td>
                    <td><?= (int)$depois['downloads_log'] ?></td>
                    <td class="zerado">Será zerada</td>
                </tr>
                <tr>
                    <td>alertas_download</td>
                    <td><?= (int)$depois['alertas_download'] ?></td>
                    <td class="zerado">Será zerada</td>
                </tr>
                <tr>
                    <td>produtos_digitais</td>
                    <td><?= (int)$depois['produtos_digitais'] ?></td>
                    <td class="mantido">Será mantida</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h2>Confirmar limpeza</h2>

        <p>Digite <strong>LIMPAR</strong> para zerar os dados de teste.</p>

        <form method="post" onsubmit="return confirm('Tem certeza? Clientes, downloads e alertas serão apagados. Produtos serão mantidos.');">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

            <label>Confirmação</label>
            <input type="text" name="confirmacao" placeholder="Digite LIMPAR" required>

            <button type="submit">Limpar dados de teste</button>
        </form>
    </div>

</div>

</body>
</html>

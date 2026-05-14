<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

$mensagem = '';
$linkGerado = '';

function gerarTokenMercadoLivre(): string
{
    return 'ML' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $origem = trim($_POST['origem'] ?? 'Mercado Livre');
    $pedido = trim($_POST['pedido'] ?? '');
    $tokenManual = trim($_POST['token'] ?? '');

    $token = $tokenManual !== '' ? $tokenManual : gerarTokenMercadoLivre();

    if ($nome === '') {
        $mensagem = 'Informe o nome do cliente.';
    } else {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO clientes_acesso
                    (nome, email, origem, pedido, token, status)
                VALUES
                    (?, ?, ?, ?, ?, 'ativo')
            ");

            $stmt->execute([
                $nome,
                $email !== '' ? $email : null,
                $origem !== '' ? $origem : 'Mercado Livre',
                $pedido !== '' ? $pedido : null,
                $token
            ]);

            $baseUrl = 'https://enilton.com.br/farmacia-interativa/acesso.php?token=';
            $linkGerado = $baseUrl . urlencode($token);

            $mensagem = 'Cliente cadastrado com sucesso.';
        } catch (Throwable $e) {
            $mensagem = 'Erro ao cadastrar cliente: ' . $e->getMessage();
        }
    }
}

$stmt = $pdo->query("
    SELECT id, nome, email, origem, pedido, token, status, criado_em
    FROM clientes_acesso
    ORDER BY id DESC
    LIMIT 30
");
$clientes = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Cadastrar Cliente</title>
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
            max-width: 1100px;
            margin: auto;
        }

        .topo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
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
            padding: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
            margin-bottom: 22px;
            border: 1px solid rgba(18,63,49,.08);
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
            color: #123f31;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #d8d2c6;
            border-radius: 12px;
            margin-bottom: 14px;
            font-size: 15px;
        }

        button {
            background: #123f31;
            color: white;
            border: none;
            border-radius: 12px;
            padding: 13px 18px;
            font-weight: bold;
            cursor: pointer;
            font-size: 15px;
        }

        button:hover {
            background: #1b5b47;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 14px;
        }

        .mensagem {
            background: #eef8f1;
            border-left: 5px solid #1b5b47;
            padding: 14px;
            border-radius: 12px;
            margin-bottom: 16px;
        }

        .link {
            background: #fff8e8;
            border: 1px solid #e5d7aa;
            padding: 14px;
            border-radius: 12px;
            word-break: break-all;
            margin-top: 12px;
        }

        textarea {
            width: 100%;
            min-height: 150px;
            padding: 13px;
            border: 1px solid #d8d2c6;
            border-radius: 12px;
            font-size: 14px;
            resize: vertical;
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

        .token {
            font-family: monospace;
            font-size: 13px;
        }

        @media(max-width: 760px) {
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

            table {
                display: block;
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="topo">
        <h1>Cadastrar Cliente</h1>
        <div class="nav">
            <a href="painel_admin.php">Painel</a>
            <a href="admin_logout.php">Sair</a>
        </div>
    </div>

    <div class="card">
        <?php if ($mensagem): ?>
            <div class="mensagem"><?= e($mensagem) ?></div>
        <?php endif; ?>

        <form method="post">
            <div class="grid">
                <div>
                    <label>Nome do cliente</label>
                    <input type="text" name="nome" required placeholder="Ex: João da Silva">
                </div>

                <div>
                    <label>Email do cliente</label>
                    <input type="email" name="email" placeholder="Ex: cliente@email.com">
                </div>

                <div>
                    <label>Origem</label>
                    <input type="text" name="origem" value="Mercado Livre">
                </div>

                <div>
                    <label>Número do pedido</label>
                    <input type="text" name="pedido" placeholder="Ex: ML-123456789">
                </div>

                <div>
                    <label>Token manual opcional</label>
                    <input type="text" name="token" placeholder="Deixe vazio para gerar automático">
                </div>
            </div>

            <button type="submit">Cadastrar e gerar link</button>
        </form>

        <?php if ($linkGerado): ?>
            <div class="link">
                <strong>Link de acesso:</strong><br>
                <?= e($linkGerado) ?>
            </div>

            <h3>Mensagem pronta para enviar no Mercado Livre</h3>
            <textarea readonly>Olá, tudo bem?

Obrigado pela compra do Kit Digital Farmácia Natural em Casa.

Segue seu link de acesso:

<?= e($linkGerado) ?>


O kit contém:
• Ebook principal
• Bônus sobre frutas
• Bônus sobre chás por fase da vida

Observação importante:
Este material é educativo e informativo. Não substitui orientação médica, diagnóstico, tratamento ou medicamentos prescritos.

Qualquer dificuldade para acessar, me chame por aqui.</textarea>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Últimos clientes cadastrados</h2>

        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Email</th>
                    <th>Origem</th>
                    <th>Pedido</th>
                    <th>Token</th>
                    <th>Status</th>
                    <th>Criado em</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($clientes as $cliente): ?>
                    <tr>
                        <td><?= e($cliente['nome']) ?></td>
                        <td><?= e($cliente['email']) ?></td>
                        <td><?= e($cliente['origem']) ?></td>
                        <td><?= e($cliente['pedido']) ?></td>
                        <td class="token"><?= e($cliente['token']) ?></td>
                        <td><?= e($cliente['status']) ?></td>
                        <td><?= e($cliente['criado_em']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</body>
</html>
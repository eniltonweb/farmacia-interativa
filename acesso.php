<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$token = $_GET['token'] ?? '';
$token = trim($token);

if ($token === '') {
    http_response_code(400);
    exit('Token de acesso não informado.');
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

$stmt = $pdo->query("
    SELECT id, titulo, arquivo
    FROM produtos_digitais
    WHERE ativo = TRUE
    ORDER BY id ASC
");
$produtos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Acesso ao Kit Digital</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <style>
        :root {
            --verde: #123f31;
            --verde2: #1b5b47;
            --fundo: #f7f2e9;
            --card: #ffffff;
            --dourado: #c49a3a;
            --texto: #23302a;
            --muted: #66736c;
            --linha: #ddd6c8;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: linear-gradient(180deg, #f8f3ea, #f3eee4);
            color: var(--texto);
            padding: 24px;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
        }

        .hero {
            background: linear-gradient(135deg, var(--verde), var(--verde2));
            color: white;
            padding: 30px;
            border-radius: 24px;
            box-shadow: 0 18px 40px rgba(18,63,49,.18);
            margin-bottom: 22px;
        }

        .hero h1 {
            margin: 0 0 10px;
            font-size: 30px;
            line-height: 1.1;
        }

        .hero p {
            margin: 0;
            color: rgba(255,255,255,.82);
            font-size: 16px;
        }

        .box {
            background: var(--card);
            border-radius: 22px;
            padding: 24px;
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
            border: 1px solid rgba(18,63,49,.08);
        }

        .cliente {
            background: #f7f3ea;
            border: 1px solid var(--linha);
            padding: 16px;
            border-radius: 16px;
            margin-bottom: 22px;
            color: var(--muted);
        }

        .cliente strong {
            color: var(--verde);
        }

        .item {
            border: 1px solid var(--linha);
            border-radius: 16px;
            padding: 18px;
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            background: #fffdfa;
        }

        .item h3 {
            margin: 0 0 5px;
            color: var(--verde);
            font-size: 18px;
        }

        .item small {
            color: var(--muted);
        }

        .btn {
            display: inline-block;
            background: var(--verde);
            color: white;
            padding: 12px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            white-space: nowrap;
        }

        .btn:hover {
            background: var(--verde2);
        }

        .aviso {
            margin-top: 22px;
            background: #fff8e8;
            border-left: 5px solid var(--dourado);
            padding: 16px;
            border-radius: 12px;
            font-size: 14px;
            color: #4b4332;
        }

        .rodape {
            text-align: center;
            color: var(--muted);
            font-size: 13px;
            margin-top: 20px;
        }

        @media(max-width: 650px) {
            body {
                padding: 14px;
            }

            .hero {
                padding: 24px;
            }

            .hero h1 {
                font-size: 25px;
            }

            .item {
                align-items: flex-start;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                text-align: center;
            }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="hero">
        <h1>Kit Digital Farmácia Natural em Casa</h1>
        <p>Seu acesso aos materiais digitais está liberado.</p>
    </div>

    <div class="box">
        <div class="cliente">
            <strong>Cliente:</strong> <?= e($cliente['nome'] ?: 'Não informado') ?><br>
            <strong>Pedido:</strong> <?= e($cliente['pedido'] ?: 'Não informado') ?><br>
            <strong>Origem:</strong> <?= e($cliente['origem'] ?: 'Mercado Livre') ?>
        </div>

        <?php if (!$produtos): ?>
            <p>Nenhum produto digital disponível no momento.</p>
        <?php endif; ?>

        <?php foreach ($produtos as $produto): ?>
            <div class="item">
                <div>
                    <h3><?= e($produto['titulo']) ?></h3>
                    <small>Arquivo digital em PDF</small>
                </div>

                <a class="btn" href="baixar.php?token=<?= urlencode($token) ?>&produto=<?= (int)$produto['id'] ?>">
                    Baixar PDF
                </a>
            </div>
        <?php endforeach; ?>

        <div class="aviso">
            Este material é de uso individual. A redistribuição, revenda, publicação em grupos ou compartilhamento integral não é autorizada. O material é educativo e não substitui orientação médica, diagnóstico ou tratamento.
        </div>
    </div>

    <div class="rodape">
        Farmácia Natural em Casa • Produto digital
    </div>
</div>

</body>
</html>
<?php
declare(strict_types=1);

require_once __DIR__ . '/admin_guard.php';

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM clientes_acesso
");
$totalClientes = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM downloads_log
");
$totalDownloads = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT COUNT(*) AS total
    FROM alertas_download
");
$totalAlertas = (int)$stmt->fetch()['total'];

$stmt = $pdo->query("
    SELECT
        a.id,
        c.nome,
        c.email,
        c.origem,
        c.pedido,
        c.token,
        p.titulo AS produto,
        a.total_downloads,
        a.alerta_enviado,
        a.enviado_em,
        a.criado_em
    FROM alertas_download a
    JOIN clientes_acesso c ON c.id = a.cliente_id
    JOIN produtos_digitais p ON p.id = a.produto_id
    ORDER BY a.criado_em DESC
");
$alertas = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        c.nome,
        c.email,
        c.origem,
        c.pedido,
        c.token,
        c.status,
        p.titulo AS produto,
        COUNT(d.id) AS total_downloads,
        COUNT(DISTINCT d.ip) AS ips_diferentes,
        MAX(d.baixado_em) AS ultimo_download
    FROM downloads_log d
    JOIN clientes_acesso c ON c.id = d.cliente_id
    JOIN produtos_digitais p ON p.id = d.produto_id
    GROUP BY
        c.id,
        c.nome,
        c.email,
        c.origem,
        c.pedido,
        c.token,
        c.status,
        p.id,
        p.titulo
    ORDER BY total_downloads DESC, ultimo_download DESC
");
$resumoDownloads = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT
        d.baixado_em,
        c.nome,
        c.email,
        c.pedido,
        c.token,
        p.titulo AS produto,
        d.ip,
        d.navegador
    FROM downloads_log d
    JOIN clientes_acesso c ON c.id = d.cliente_id
    JOIN produtos_digitais p ON p.id = d.produto_id
    ORDER BY d.baixado_em DESC
    LIMIT 80
");
$ultimosDownloads = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Painel Administrativo</title>
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

        h1 {
            color: #123f31;
            margin: 0;
        }

        h2 {
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

        .cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 22px;
        }

        .card-num {
            background: white;
            border-radius: 18px;
            padding: 20px;
            box-shadow: 0 10px 26px rgba(0,0,0,.06);
            border: 1px solid rgba(18,63,49,.08);
        }

        .card-num strong {
            display: block;
            color: #123f31;
            font-size: 34px;
            margin-bottom: 5px;
        }

        .card-num span {
            color: #68766e;
            font-size: 14px;
        }

        .box {
            background: white;
            border-radius: 20px;
            padding: 22px;
            box-shadow: 0 12px 30px rgba(0,0,0,.07);
            margin-bottom: 22px;
            border: 1px solid rgba(18,63,49,.08);
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
            position: sticky;
            top: 0;
        }

        tr.alerta {
            background: #fff8e4;
        }

        .tag {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .tag-sim {
            background: #e8f7ed;
            color: #176b38;
        }

        .tag-nao {
            background: #ffe8e4;
            color: #9a2d20;
        }

        .token {
            font-family: monospace;
            font-size: 13px;
        }

        .scroll {
            overflow-x: auto;
        }

        .navegador {
            max-width: 360px;
            word-break: break-word;
            color: #68766e;
            font-size: 12px;
        }

        @media(max-width: 850px) {
            .cards {
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
        <h1>Painel Administrativo</h1>

        <div class="nav">
    <a href="cadastrar_cliente.php">Cadastrar cliente</a>
    <a href="produtos_admin.php">Produtos</a>
    <a href="admin_logout.php">Sair</a>
</div>
    </div>

    <div class="cards">
        <div class="card-num">
            <strong><?= $totalClientes ?></strong>
            <span>Clientes cadastrados</span>
        </div>

        <div class="card-num">
            <strong><?= $totalDownloads ?></strong>
            <span>Downloads realizados</span>
        </div>

        <div class="card-num">
            <strong><?= $totalAlertas ?></strong>
            <span>Alertas de 5 downloads</span>
        </div>
    </div>

    <div class="box">
        <h2>Alertas de downloads suspeitos</h2>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Pedido</th>
                        <th>Produto</th>
                        <th>Downloads</th>
                        <th>Email enviado</th>
                        <th>Data alerta</th>
                        <th>Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$alertas): ?>
                        <tr>
                            <td colspan="8">Nenhum alerta registrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($alertas as $alerta): ?>
                        <tr class="alerta">
                            <td><?= e($alerta['nome']) ?></td>
                            <td><?= e($alerta['email']) ?></td>
                            <td><?= e($alerta['pedido']) ?></td>
                            <td><?= e($alerta['produto']) ?></td>
                            <td><strong><?= (int)$alerta['total_downloads'] ?></strong></td>
                            <td>
                                <?php if ($alerta['alerta_enviado']): ?>
                                    <span class="tag tag-sim">Sim</span>
                                <?php else: ?>
                                    <span class="tag tag-nao">Não</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($alerta['criado_em']) ?></td>
                            <td class="token"><?= e($alerta['token']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="box">
        <h2>Resumo por cliente e produto</h2>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Cliente</th>
                        <th>Email</th>
                        <th>Pedido</th>
                        <th>Produto</th>
                        <th>Downloads</th>
                        <th>IPs diferentes</th>
                        <th>Último download</th>
                        <th>Status</th>
                        <th>Token</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$resumoDownloads): ?>
                        <tr>
                            <td colspan="9">Nenhum download registrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($resumoDownloads as $linha): ?>
                        <tr class="<?= ((int)$linha['total_downloads'] >= 5) ? 'alerta' : '' ?>">
                            <td><?= e($linha['nome']) ?></td>
                            <td><?= e($linha['email']) ?></td>
                            <td><?= e($linha['pedido']) ?></td>
                            <td><?= e($linha['produto']) ?></td>
                            <td><strong><?= (int)$linha['total_downloads'] ?></strong></td>
                            <td><?= (int)$linha['ips_diferentes'] ?></td>
                            <td><?= e($linha['ultimo_download']) ?></td>
                            <td><?= e($linha['status']) ?></td>
                            <td class="token"><?= e($linha['token']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="box">
        <h2>Últimos downloads</h2>

        <div class="scroll">
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Pedido</th>
                        <th>Produto</th>
                        <th>IP</th>
                        <th>Navegador</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$ultimosDownloads): ?>
                        <tr>
                            <td colspan="6">Nenhum download registrado.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($ultimosDownloads as $download): ?>
                        <tr>
                            <td><?= e($download['baixado_em']) ?></td>
                            <td><?= e($download['nome']) ?><br><small><?= e($download['email']) ?></small></td>
                            <td><?= e($download['pedido']) ?></td>
                            <td><?= e($download['produto']) ?></td>
                            <td><?= e($download['ip']) ?></td>
                            <td class="navegador"><?= e($download['navegador']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

</body>
</html>
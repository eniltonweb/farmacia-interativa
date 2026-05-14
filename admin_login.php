<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $senha = $_POST['senha'] ?? '';

    if (!isset($senhaAdminSistema) || trim((string)$senhaAdminSistema) === '') {
        $erro = 'Senha administrativa não configurada no config.php.';
    } elseif (hash_equals((string)$senhaAdminSistema, (string)$senha)) {
        $_SESSION['admin_logado'] = true;
        $_SESSION['admin_login_em'] = date('Y-m-d H:i:s');

        header('Location: painel_admin.php');
        exit;
    } else {
        $erro = 'Senha incorreta.';
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Login Administrativo</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: linear-gradient(135deg, #123f31, #1b5b47);
            font-family: Arial, Helvetica, sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border-radius: 22px;
            padding: 28px;
            box-shadow: 0 20px 50px rgba(0,0,0,.25);
        }

        h1 {
            margin: 0 0 8px;
            color: #123f31;
        }

        p {
            margin: 0 0 22px;
            color: #64716a;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 8px;
            color: #23302a;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #d8d2c6;
            border-radius: 12px;
            font-size: 16px;
            margin-bottom: 16px;
        }

        button {
            width: 100%;
            background: #123f31;
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #1b5b47;
        }

        .erro {
            background: #ffe8e4;
            color: #8d2b1f;
            border-left: 4px solid #b64232;
            padding: 12px;
            border-radius: 10px;
            margin-bottom: 16px;
        }
    </style>
</head>
<body>

<div class="login">
    <h1>Painel Administrativo</h1>
    <p>Acesso restrito ao administrador.</p>

    <?php if ($erro): ?>
        <div class="erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="post">
        <label for="senha">Senha administrativa</label>
        <input type="password" name="senha" id="senha" required autofocus>
        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
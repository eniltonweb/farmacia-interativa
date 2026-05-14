<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

$erro = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    $senha = $_POST['senha'] ?? '';
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attemptsFile = __DIR__ . '/admin_login_attempts.json';
    $maxAttempts = 5;
    $lockoutTime = 300; // 5 minutos

    $fp = @fopen($attemptsFile, 'c+');
    $blocked = false;
    $changed = false;

    if ($fp) {
        flock($fp, LOCK_EX);
        clearstatcache(true, $attemptsFile);
        $filesize = filesize($attemptsFile);
        $attemptsData = [];
        if ($filesize > 0) {
            rewind($fp);
            $content = fread($fp, $filesize);
            $attemptsData = json_decode($content, true) ?: [];
        }

        $currentTime = time();

        foreach ($attemptsData as $storedIp => $data) {
            if ($currentTime - $data['last_attempt'] > $lockoutTime) {
                unset($attemptsData[$storedIp]);
                $changed = true;
            }
        }

        $ipData = $attemptsData[$ip] ?? ['count' => 0, 'last_attempt' => 0];

        if ($ipData['count'] >= $maxAttempts) {
            $minutosRestantes = ceil(($lockoutTime - ($currentTime - $ipData['last_attempt'])) / 60);
            $erro = "Muitas tentativas falhas. Tente novamente em {$minutosRestantes} minutos.";
            $blocked = true;
        }
    }

    if (!$blocked) {
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
            $erro = 'Token CSRF inválido.';
            if ($fp) {
                $ipData['count']++;
                $ipData['last_attempt'] = time();
                $attemptsData[$ip] = $ipData;
                $changed = true;
            }
        } elseif (!isset($senhaAdminSistema) || trim((string)$senhaAdminSistema) === '') {
            $erro = 'Senha administrativa não configurada no config.php.';
        } elseif (hash_equals((string)$senhaAdminSistema, (string)$senha)) {
            if ($fp) {
                unset($attemptsData[$ip]);
                $changed = true;
            }
            $_SESSION['admin_logado'] = true;
            $_SESSION['admin_login_em'] = date('Y-m-d H:i:s');
        } else {
            $erro = 'Senha incorreta.';
            if ($fp) {
                $ipData['count']++;
                $ipData['last_attempt'] = time();
                $attemptsData[$ip] = $ipData;
                $changed = true;
            }
        }
    }

    if ($fp) {
        if ($changed) {
            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($attemptsData));
        }
        flock($fp, LOCK_UN);
        fclose($fp);
    }

    if (isset($_SESSION['admin_logado']) && $_SESSION['admin_logado']) {
        header('Location: painel_admin.php');
        exit;
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
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="senha">Senha administrativa</label>
        <input type="password" name="senha" id="senha" required autofocus>
        <button type="submit">Entrar</button>
    </form>
</div>

</body>
</html>
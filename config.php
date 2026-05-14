<?php
declare(strict_types=1);

$host = 'infoprodutonil.postgresql.dbaas.com.br';
$port = '5432';
$dbname = 'infoprodutonil';
$user = 'infoprodutonil';
$pass = 'Nil2024#';

$hashSenhaAdminSistema = '$2y$10$rCKTDg4Zye0rloycATtepOyWEBrF5/wb22t/MMfcLW5uWBg/pBPvS';
$emailAdmin = 'nil@enilton.com.br';

try {
    $pdo = new PDO(
        "pgsql:host={$host};port={$port};dbname={$dbname}",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    error_log('Erro ao conectar ao banco de dados: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno ao conectar ao banco de dados.');
}

function limparTexto(?string $valor): string
{
    return htmlspecialchars(trim((string)$valor), ENT_QUOTES, 'UTF-8');
}

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

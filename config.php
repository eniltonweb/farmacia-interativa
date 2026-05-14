<?php
declare(strict_types=1);

$host = 'infoprodutonil.postgresql.dbaas.com.br';
$port = '5432';
$dbname = 'infoprodutonil';
$user = 'infoprodutonil';
$pass = 'Nil2024#';

$senhaAdminSistema = 'adminnil2026';
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
    http_response_code(500);
    exit('Erro ao conectar ao banco de dados: ' . $e->getMessage());
}

function limparTexto(?string $valor): string
{
    return htmlspecialchars(trim((string)$valor), ENT_QUOTES, 'UTF-8');
}

function e(?string $valor): string
{
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
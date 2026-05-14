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
    error_log('Erro de banco de dados: ' . $e->getMessage());
    http_response_code(500);
    exit('Erro interno do servidor. Por favor, tente novamente mais tarde.');
}



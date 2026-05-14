<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/config.php';

if (empty($_SESSION['admin_logado']) || $_SESSION['admin_logado'] !== true) {
    header('Location: admin_login.php');
    exit;
}
<?php

session_start();

require '../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '\\..\\config');
$dotenv->safeLoad();

$_ENV['BASE_URL'] = $_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'];

if (!isset($_GET['url'])) {
    header('Location: ' . $_ENV['BASE_URL'] . '/login');
    exit;
}

function isAdmin(): bool
{
    return (bool) $_SESSION['usuario_is_admin'] ?? false;
}

function onlyAdmin()
{
    if (!isAdmin()) {
        include '../src/404.php';
        exit;
    }
}

function onlyMaster()
{
    if (($_SESSION['usuario_id'] ?? 0) != 1) {
        include '../src/404.php';
        exit;
    }
}

include "../src/loadController.php";

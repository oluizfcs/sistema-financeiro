<?php

declare(strict_types=1);

$appDir = dirname(__DIR__);

chdir($appDir);

require $appDir . '/vendor/autoload.php';

$env = static function (string $key, string $default): string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    if ($value === false || $value === null || $value === '') {
        return $default;
    }

    return (string) $value;
};

$host = $env('TEST_DB_HOST', '127.0.0.1');
$name = $env('TEST_DB_NAME', 'sistema_financeiro_test');
$user = $env('TEST_DB_USERNAME', 'root');
$password = $env('TEST_DB_PASSWORD', '1234');

if ($name === 'sistema_financeiro' || preg_match('/_test(?:_|$)/', $name) !== 1) {
    fwrite(STDERR, "Refusing to run tests against unsafe database: {$name}\n");
    exit(1);
}

$quotedName = '`' . str_replace('`', '``', $name) . '`';

$multiStatements = class_exists(\Pdo\Mysql::class)
    ? \Pdo\Mysql::ATTR_MULTI_STATEMENTS
    : PDO::MYSQL_ATTR_MULTI_STATEMENTS;

$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    $multiStatements => true,
];

try {
    $admin = new PDO("mysql:host={$host};charset=utf8mb4", $user, $password, $options);
    $admin->exec("DROP DATABASE IF EXISTS {$quotedName}");
    $admin->exec("CREATE DATABASE {$quotedName} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    $schema = file_get_contents($appDir . '/config/database/schema.sql');

    if ($schema === false) {
        throw new RuntimeException('Não foi possível ler config/database/schema.sql.');
    }

    $schema = preg_replace('/CREATE DATABASE.*?;/is', '', $schema, 1);
    $schema = preg_replace('/^\s*USE\s+[^;]+;\s*$/im', '', (string) $schema);

    $pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $password, $options);
    $pdo->exec((string) $schema);
} catch (Throwable $e) {
    fwrite(STDERR, "Failed to prepare test database: {$e->getMessage()}\n");
    exit(1);
}

$_ENV['APP_ENV'] = 'test';
$_ENV['HOST_NAME'] = $host;
$_ENV['DB_NAME'] = $name;
$_ENV['DB_USERNAME'] = $user;
$_ENV['DB_PASSWORD'] = $password;
$_ENV['BASE_URL'] = '/contas';

$_SERVER['APP_ENV'] = 'test';
$_SERVER['HOST_NAME'] = $host;
$_SERVER['DB_NAME'] = $name;
$_SERVER['DB_USERNAME'] = $user;
$_SERVER['DB_PASSWORD'] = $password;
$_SERVER['BASE_URL'] = '/contas';

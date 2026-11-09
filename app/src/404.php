<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 - Página não encontrada</title>
</head>

<body>
    <div id="error-container">
        <h1>404</h1>
        <p>Página não encontrada.</p>
        <a href="<?= isset($_SESSION['usuario_id']) ? ($_ENV['BASE_URL'] . '/dashboard') : ($_ENV['BASE_URL'] . '/login') ?>" class="error-btn">Voltar</a>
    </div>
</body>

</html>

<style>
    body {
        display: flex;
        font-family: Arial, Helvetica, sans-serif;
        align-items: center;
        justify-content: center;
        min-height: 100vh;
        margin: 0;
        background: linear-gradient(135deg, hsl(214, 77%, 14%), hsl(214, 77%, 20%));
        color: white;
        text-align: center;
    }

    #error-container h1 {
        font-size: 8rem;
        font-weight: 800;
        margin: 0;
        line-height: 1;
        color: rgba(255, 255, 255, 0.15);
        text-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }

    #error-container p {
        font-size: 1.2rem;
        margin: 0.5rem 0 2rem;
        color: rgba(255, 255, 255, 0.8);
    }

    .error-btn {
        display: inline-block;
        padding: 0.6rem 1.5rem;
        background: rgba(255, 255, 255, 0.15);
        color: white;
        text-decoration: none;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 500;
        transition: background 0.2s;
    }

    .error-btn:hover {
        background: rgba(255, 255, 255, 0.25);
    }
</style>
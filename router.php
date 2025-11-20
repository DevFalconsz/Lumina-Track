<?php
// router.php - Roteador para o servidor embutido do PHP

$request_uri = $_SERVER['REQUEST_URI'];
$public_dir = __DIR__ . '/public';

// Se a requisição for para um arquivo estático na pasta /public
if (is_file($public_dir . $request_uri)) {
    return false; // Sirva o arquivo diretamente
}

// Se a requisição for para a raiz, sirva o index.html
if ($request_uri === '/') {
    readfile($public_dir . '/index.html');
    return;
}

// Para todas as outras requisições (API, scripts de teste),
// extrai a rota e passa para o roteador principal do backend.
if (preg_match('/^\/([a-zA-Z0-9_\-\/]+)/', $request_uri, $matches)) {
    $_GET['route'] = $matches[1];
    
    // Se for um acesso direto a um script PHP no backend (vide teste)
    if (is_file(__DIR__ . '/' . $request_uri)) {
        require __DIR__ . '/' . $request_uri;
        return;
    }

    // Caso contrário, é uma rota de API
    require __DIR__ . '/backend/routes.php';
} else {
    // Se nada corresponder, retorna um 404 simples
    http_response_code(404);
    echo "<h1>404 Not Found</h1><p>A rota solicitada não foi encontrada.</p>";
}
?>

<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<pre>";
echo "<h1>Teste de Conexão cURL para Nominatim</h1>";

$url = "https://nominatim.openstreetmap.org/search?q=Brasil&format=json";

echo "<strong>URL Alvo:</strong> " . htmlspecialchars($url) . "\n\n";

$ch = curl_init();

// Configurações do cURL
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_USERAGENT, 'LuminaTrack-CurlTest/1.0');
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); 
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

// Desabilitar verificação SSL (para ambientes de dev antigos)
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

// [A CORREÇÃO] Força o uso do TLS 1.2, que é compatível com servidores modernos
curl_setopt($ch, CURLOPT_SSLVERSION, 6); // 6 = CURL_SSLVERSION_TLSv1_2

// Habilitar modo verbose para obter o máximo de detalhes
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

echo "<strong>Executando cURL...</strong>\n";
$response = curl_exec($ch);

echo "<strong>Execução concluída.</strong>\n\n";

echo "<h2>--- Resultados ---</h2>\n";

if ($response === false) {
    echo "<strong>Erro cURL:</strong> <span style='color:red;'>" . htmlspecialchars(curl_error($ch)) . "</span>\n";
    echo "<strong>Número do Erro cURL:</strong> " . htmlspecialchars(curl_errno($ch)) . "\n\n";
} else {
    echo "<strong>Resposta da API recebida com sucesso!</strong>\n";
    echo "<strong>Código de Status HTTP:</strong> " . htmlspecialchars(curl_getinfo($ch, CURLINFO_HTTP_CODE)) . "\n";
    echo "<strong>Conteúdo da Resposta (JSON):</strong>\n";
    echo htmlspecialchars($response);
}

echo "\n\n<h2>--- Informações Detalhadas da Conexão ---</h2>\n";
print_r(curl_getinfo($ch));

rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "\n\n<h2>--- Log Verbose do cURL ---</h2>\n";
echo "<div style='background-color:#f5f5f5; border:1px solid #ccc; padding:10px; font-family:monospace; white-space:pre-wrap;'>";
echo htmlspecialchars($verboseLog);
echo "</div>";

curl_close($ch);

echo "</pre>";
?>
<?php

// --- Configuração do Teste ---
$baseUrl = 'http://localhost/backend/routes.php';
$nfeKey = '12345678901234567890123456789012345678904321';
$xmlFilePath = __DIR__ . '/sample_nfe.xml';

// --- Funções Auxiliares ---

function sendRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    $options = array(
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
    );

    if ($method == 'POST' && $data) {
        if (isset($data['nfe_xml'])) {
            $options[CURLOPT_HTTPHEADER] = array('Content-Type: multipart/form-data');
            $data['nfe_xml'] = '@' . realpath($data['nfe_xml']); // Usar realpath para garantir caminho absoluto
            $options[CURLOPT_POSTFIELDS] = $data;
        } else {
            $options[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }

    curl_setopt_array($ch, $options);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if(curl_errno($ch)){
        echo 'Erro cURL: ' . curl_error($ch) . PHP_EOL;
    }
    
    curl_close($ch);
    
    $decoded_response = json_decode($response, true);
    // Se o JSON falhar, retorna a resposta crua para depuração
    $body = $decoded_response === null ? $response : $decoded_response;

    return array('code' => $httpCode, 'body' => $body);
}

function runTest($description, $success, $result = null) {
    echo $description . ': ' . ($success ? "✅ SUCESSO" : "❌ FALHA") . PHP_EOL;
    if (!$success) {
        if ($result) {
            echo "   -> Código HTTP: " . (isset($result['code']) ? $result['code'] : 'N/A') . PHP_EOL;
            if (is_array($result['body'])) {
                echo "   -> Resposta JSON: " . json_encode($result['body']) . PHP_EOL;
            } else {
                echo "   -> Resposta Crua do Servidor: " . PHP_EOL . "   " . $result['body'] . PHP_EOL;
            }
        }
        exit(1);
    }
}

function cleanup($nfeKey, $conn) {
    if ($conn) {
        $stmt = $conn->prepare("DELETE FROM entregas WHERE nfe_key = ?");
        $stmt->bind_param("s", $nfeKey);
        $stmt->execute();
        echo "🧹 Dados de teste limpos." . PHP_EOL;
    }
}

// --- Execução dos Testes ---

echo "Iniciando testes da API Lumina Track..." . PHP_EOL;
echo "----------------------------------------" . PHP_EOL;

require_once __DIR__ . '/../backend/config.php';
$conn = connectDB();

cleanup($nfeKey, $conn);

// 1. Teste de Upload de NF-e
$uploadData = array('nfe_xml' => $xmlFilePath);
$uploadResult = sendRequest($baseUrl . '?route=/upload', 'POST', $uploadData);
runTest('1. POST /upload - Registrar nova entrega', $uploadResult['code'] == 201 && isset($uploadResult['body']['success']), $uploadResult);

// 2. Teste de Upload Duplicado
$duplicateResult = sendRequest($baseUrl . '?route=/upload', 'POST', $uploadData);
runTest('2. POST /upload - Impedir NF-e duplicada', $duplicateResult['code'] == 409 && isset($duplicateResult['body']['error']), $duplicateResult);

// 3. Teste de Webhook
$webhookData = array(
    'nfe_key' => $nfeKey,
    'status' => 'Em trânsito para o centro de distribuição.',
    'event_date' => date('Y-m-d H:i:s')
);
$webhookResult = sendRequest($baseUrl . '?route=/webhook', 'POST', $webhookData);
runTest('3. POST /webhook - Adicionar evento de rastreamento', $webhookResult['code'] == 200 && isset($webhookResult['body']['success']), $webhookResult);

// 4. Teste de Rastreamento
$trackingResult = sendRequest($baseUrl . '?route=/rastreamento/' . $nfeKey);
runTest('4. GET /rastreamento/{nfe_key} - Buscar rastreamento', $trackingResult['code'] == 200 && isset($trackingResult['body']['entrega']) && count($trackingResult['body']['eventos']) == 2, $trackingResult);

// 5. Teste de Listagem de Entregas
$listResult = sendRequest($baseUrl . '?route=/entregas');
runTest('5. GET /entregas - Listar entregas', $listResult['code'] == 200 && is_array($listResult['body']), $listResult);

// 6. Teste de Detalhes da Entrega
$detailsResult = sendRequest($baseUrl . '?route=/entregas/' . $nfeKey);
runTest('6. GET /entregas/{nfe_key} - Buscar detalhes da entrega', $detailsResult['code'] == 200 && $detailsResult['body']['nfe_key'] == $nfeKey, $detailsResult);

// 7. Teste de Métricas
$metricsResult = sendRequest($baseUrl . '?route=/metricas');
runTest('7. GET /metricas - Obter métricas', $metricsResult['code'] == 200 && isset($metricsResult['body']['total_entregas']), $metricsResult);

cleanup($nfeKey, $conn);
$conn->close();

echo "----------------------------------------" . PHP_EOL;
echo "✅ Todos os testes foram concluídos." . PHP_EOL;

?>
<?php

// Habilita a exibição de todos os erros para depuração
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Inicia o buffer de saída para capturar qualquer saída inesperada
ob_start();

// --- Tratamento de Erro Global ---

// Função para enviar resposta JSON de erro
function sendFatalErrorResponse($message) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    header("Content-Type: application/json; charset=UTF-8");
    header("HTTP/1.1 500 Internal Server Error");
    echo json_encode(array('error' => 'Erro interno do servidor: ' . $message));
    exit();
}

// Captura erros fatais (shutdown function)
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], array(E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR))) {
        sendFatalErrorResponse("Falha fatal: " . $error['message'] . " na linha " . $error['line'] . " do arquivo " . $error['file']);
    }
});

// Captura erros não fatais (error handler)
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    if (!(error_reporting() & $errno)) {
        return false;
    }
    error_log("Erro PHP: $errstr em $errfile na linha $errline");
    return true;
});

// --- Fim do Tratamento de Erro Global ---

// Define o cabeçalho de resposta como JSON
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

// Trata a requisição OPTIONS (pre-flight) do CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Inclui os arquivos de configuração e funções
require_once 'config.php';
require_once 'functions.php';

// --- Roteamento Básico ---
$route = isset($_GET['route']) ? $_GET['route'] : '/';
$request_method = $_SERVER['REQUEST_METHOD'];

$route_parts = array_filter(explode('/', $route));
$endpoint = array_shift($route_parts);
$param = array_shift($route_parts);

// Conecta ao banco de dados
$conn = connectDB();

// --- Lógica do Roteador ---
switch ($endpoint) {
    case 'upload':
        if ($request_method == 'POST') {
            handleUpload($conn);
        } else {
            sendResponse(405, array('error' => 'Método não permitido. Use POST.'));
        }
        break;

    case 'webhook':
        if ($request_method == 'POST') {
            handleWebhook($conn);
        } else {
            sendResponse(405, array('error' => 'Método não permitido. Use POST.'));
        }
        break;

    case 'entregas':
        if ($request_method == 'GET') {
            if ($param) {
                getDeliveryDetails($conn, $param);
            } else {
                listDeliveries($conn);
            }
        } else {
            sendResponse(405, array('error' => 'Método não permitido. Use GET.'));
        }
        break;

    case 'rastreamento':
        if ($request_method == 'GET' && $param) {
            getTrackingInfo($conn, $param);
        } else {
            sendResponse(400, array('error' => 'Requisição inválida. Forneça a chave da NF-e.'));
        }
        break;

    case 'metricas':
        if ($request_method == 'GET') {
            getMetrics($conn);
        } else {
            sendResponse(405, array('error' => 'Método não permitido. Use GET.'));
        }
        break;

    default:
        sendResponse(404, array('error' => 'Endpoint não encontrado.'));
        break;
}

$conn->close();

// --- Handlers --- //

function handleUpload($conn) {
    if (!isset($_FILES['nfe_xml']) || $_FILES['nfe_xml']['error'] !== UPLOAD_ERR_OK) {
        sendResponse(400, array('error' => 'Nenhum arquivo XML enviado ou erro no upload.'));
        return;
    }

    $xmlContent = file_get_contents($_FILES['nfe_xml']['tmp_name']);
    $nfeData = parseNFeXML($xmlContent);

    // parseNFeXML pode retornar array('error'=>...)
    if (!is_array($nfeData) || isset($nfeData['error'])) {
        $msg = is_array($nfeData) && isset($nfeData['error']) ? $nfeData['error'] : 'XML inválido ou dados essenciais faltando.';
        sendResponse(400, array('error' => $msg));
        return;
    }

    // Confere se já existe
    if (getDeliveryByNFeKey($nfeData['nfe_key'], $conn)) {
        sendResponse(409, array('error' => 'NF-e com esta chave já existe.'));
        return;
    }

    // Normaliza endereço antes de tentar geocodificar
    $normalized = normalizeAddressParts(
        $nfeData['dest_logradouro'],
        $nfeData['dest_numero'],
        $nfeData['dest_bairro'],
        $nfeData['dest_municipio'],
        $nfeData['dest_uf'],
        $nfeData['dest_cep']
    );

    // Substitui campos por valores normalizados
    $nfeData['dest_logradouro'] = $normalized['logradouro'];
    $nfeData['dest_numero'] = $normalized['numero'];
    $nfeData['dest_bairro'] = $normalized['bairro'];
    $nfeData['dest_municipio'] = $normalized['cidade'];
    $nfeData['dest_uf'] = $normalized['uf'];
    $nfeData['dest_cep'] = $normalized['cep'];

    // Tenta geocodificar com os fallbacks implementados
    $coordinates = getCoordinatesFromAddress(
        $nfeData['dest_logradouro'],
        $nfeData['dest_numero'],
        $nfeData['dest_bairro'],
        $nfeData['dest_municipio'],
        $nfeData['dest_uf'],
        $nfeData['dest_cep']
    );

    // Se não encontrou por endereço, tenta apenas por CEP como último recurso
    if ($coordinates === null && !empty($nfeData['dest_cep'])) {
        $coordinates = getCoordinatesForCEP($nfeData['dest_cep']);
    }

    // Prepara dados para salvar no banco
    $deliveryData = array(
        'nfe_key' => $nfeData['nfe_key'],
        'dest_name' => $nfeData['dest_name'],
        'dest_cep' => $nfeData['dest_cep'],
        'dest_logradouro' => $nfeData['dest_logradouro'],
        'dest_numero' => $nfeData['dest_numero'],
        'dest_bairro' => $nfeData['dest_bairro'],
        'dest_municipio' => $nfeData['dest_municipio'],
        'dest_uf' => $nfeData['dest_uf'],
        'dest_lat' => $coordinates ? (float)$coordinates['lat'] : null,
        'dest_lng' => $coordinates ? (float)$coordinates['lng'] : null,
    );

    $deliveryId = saveDelivery($deliveryData, $conn);

    if ($deliveryId) {
        $eventData = array(
            'entrega_id' => $deliveryId,
            'status' => 'Pedido recebido e em processamento.',
            'event_date' => date('Y-m-d H:i:s')
        );
        saveEvent($eventData, $conn);
        sendResponse(201, array('success' => true, 'message' => 'Entrega registrada com sucesso.', 'delivery_id' => $deliveryId, 'coordinates' => $coordinates));
    } else {
        sendResponse(500, array('error' => 'Falha ao salvar a entrega no banco de dados.'));
    }
}

function handleWebhook($conn) {
    $payload = json_decode(file_get_contents('php://input'), true);

    if (!$payload || !isset($payload['nfe_key']) || !isset($payload['status']) || !isset($payload['event_date'])) {
        sendResponse(400, array('error' => 'Payload JSON inválido ou faltando dados.'));
        return;
    }

    $delivery = getDeliveryByNFeKey($payload['nfe_key'], $conn);

    if (!$delivery) {
        sendResponse(404, array('error' => 'Entrega não encontrada para a chave NF-e fornecida.'));
        return;
    }

    $eventData = array(
        'entrega_id' => $delivery['id'],
        'status' => $payload['status'],
        'event_date' => $payload['event_date']
    );

    if (saveEvent($eventData, $conn)) {
        sendResponse(200, array('success' => true, 'message' => 'Evento registrado com sucesso.'));
    } else {
        sendResponse(500, array('error' => 'Falha ao salvar o evento no banco de dados.'));
    }
}

function getTrackingInfo($conn, $nfe_key) {
    $delivery = getDeliveryByNFeKey($nfe_key, $conn);

    if (!$delivery) {
        sendResponse(404, array('error' => 'Entrega não encontrada.'));
        return;
    }

    $stmt = $conn->prepare("SELECT status, event_date FROM eventos WHERE entrega_id = ? ORDER BY event_date DESC");
    $stmt->bind_param("i", $delivery['id']);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = array();
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }

    $response = array(
        'entrega' => $delivery,
        'eventos' => $events
    );

    sendResponse(200, $response);
}

function listDeliveries($conn) {
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * $limit;

    $result = $conn->query("SELECT * FROM entregas ORDER BY created_at DESC LIMIT $limit OFFSET $offset");

    $deliveries = array();
    while ($row = $result->fetch_assoc()) {
        $deliveries[] = $row;
    }

    sendResponse(200, $deliveries);
}

function getDeliveryDetails($conn, $nfe_key) {
    $delivery = getDeliveryByNFeKey($nfe_key, $conn);
    if ($delivery) {
        sendResponse(200, $delivery);
    } else {
        sendResponse(404, array('error' => 'Entrega não encontrada.'));
    }
}

function getMetrics($conn) {
    $totalResult = $conn->query("SELECT COUNT(*) as total FROM entregas");
    if (!$totalResult) {
        error_log("Erro SQL em getMetrics (total): " . $conn->error);
        sendResponse(500, array('error' => 'Erro interno ao buscar métricas (total).'));
        return;
    }
    $totalRow = $totalResult->fetch_assoc();
    $total = $totalRow['total'];

    $deliveredResult = $conn->query("SELECT COUNT(DISTINCT entrega_id) as delivered FROM eventos WHERE status LIKE 'Entrega realizada%'");
    if (!$deliveredResult) {
        error_log("Erro SQL em getMetrics (delivered): " . $conn->error);
        sendResponse(500, array('error' => 'Erro interno ao buscar métricas (delivered).'));
        return;
    }
    $deliveredRow = $deliveredResult->fetch_assoc();
    $delivered = $deliveredRow['delivered'];

    $in_progress = $total - $delivered;

    $metrics = array(
        'total_entregas' => (int)$total,
        'entregas_finalizadas' => (int)$delivered,
        'entregas_em_andamento' => (int)$in_progress
    );

    sendResponse(200, $metrics);
}

/**
 * Função auxiliar para enviar respostas JSON com código de status HTTP, compatível com PHP < 5.4.
 */
function sendResponse($statusCode, $data) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }

    $statusCodes = array(
        200 => 'OK',
        201 => 'Created',
        400 => 'Bad Request',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        500 => 'Internal Server Error'
    );

    $statusMessage = isset($statusCodes[$statusCode]) ? $statusCodes[$statusCode] : 'Unknown Status';

    header("HTTP/1.1 " . $statusCode . " " . $statusMessage);
    echo json_encode($data);
}

?>
<?php ob_end_flush(); ?>


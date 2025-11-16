<?php

/**
 * Faz o parsing do conteúdo XML de uma NF-e usando o parser clássico do PHP (compatível com PHP 5.3).
 *
 * @param string $xmlContent
 * @return array|false
 */
function parseNFeXML($xmlContent) {

    libxml_use_internal_errors(true);

    $xml = simplexml_load_string($xmlContent);

    if (!$xml) {
        $errors = libxml_get_errors();
        libxml_clear_errors();

        return array(
            'error' => 'XML inválido.',
            'details' => $errors
        );
    }

    // A raiz pode ser <nfeProc> ou <NFe>
    if (isset($xml->NFe)) {
        $inf = $xml->NFe->infNFe;
    } elseif (isset($xml->infNFe)) {
        $inf = $xml->infNFe;
    } else {
        return array('error' => 'Estrutura NFe não encontrada.');
    }

    // Informações essenciais
    $dest = $inf->dest;
    if (!$dest) {
        return array('error' => 'Destinatário ausente no XML.');
    }

    // =============================
    // LIMPEZA E VALIDAÇÃO DA CHAVE
    // =============================

    // Pega o atributo Id do infNFe
    $rawKey = (string)$inf['Id']; // Ex: "NFe24251135649219000796550010000918251114576"

    // Remove prefixo "NFe" se existir
    $rawKey = str_replace('NFe', '', $rawKey);

    // Mantém somente números
    $cleanKey = preg_replace('/\D/', '', $rawKey);

    // Verifica se possui exatamente 44 dígitos
    if (strlen($cleanKey) !== 44) {
        return array(
            'error' => 'Chave da NF-e inválida após limpeza.',
            'original' => $rawKey,
            'cleaned' => $cleanKey
        );
    }

    // Retorna os dados (a normalização do endereço e a geocodificação
    // ficarão a cargo do routes.php antes de inserir no banco)
    return array(
        'nfe_key'          => $cleanKey,
        'dest_name'        => (string)$dest->xNome,
        'dest_cep'         => (string)$dest->enderDest->CEP,
        'dest_logradouro'  => (string)$dest->enderDest->xLgr,
        'dest_numero'      => (string)$dest->enderDest->nro,
        'dest_bairro'      => (string)$dest->enderDest->xBairro,
        'dest_municipio'   => (string)$dest->enderDest->xMun,
        'dest_uf'          => (string)$dest->enderDest->UF
    );
}

/**
 * Normaliza partes do endereço para melhorar resultados de geocodificação.
 * Compatível com PHP 5.3.
 *
 * Entrada: strings brutas vindas do XML.
 * Saída: array associativo com logradouro, numero, bairro, cidade, uf, cep (todos trimados/limpos).
 */
function normalizeAddressParts($logradouro, $numero, $bairro, $cidade, $uf, $cep) {
    // Garantir strings
    $logradouro = isset($logradouro) ? trim((string)$logradouro) : '';
    $numero = isset($numero) ? trim((string)$numero) : '';
    $bairro = isset($bairro) ? trim((string)$bairro) : '';
    $cidade = isset($cidade) ? trim((string)$cidade) : '';
    $uf = isset($uf) ? trim((string)$uf) : '';
    $cep = isset($cep) ? trim((string)$cep) : '';

    // Normaliza CEP: só dígitos
    $cep_digits = preg_replace('/\D/', '', $cep);
    if (strlen($cep_digits) === 8) {
        $cep = $cep_digits; // mantemos sem hífen, Nominatim aceita
    } else {
        $cep = $cep_digits; // pode ficar vazio ou parcial
    }

    // Normaliza número: se for "0", "SN", "S/N", "SN." -> considera vazio (sem número)
    $numero_upper = strtoupper($numero);
    if ($numero_upper === '0' || $numero_upper === 'SN' || $numero_upper === 'S/N' || $numero_upper === 'SN.' || $numero_upper === 'S N') {
        $numero = '';
    }

    // Normaliza abreviações de logradouro no início (ex.: "R 1" -> "Rua 1")
    // Substituições simples — expande as mais comuns
    if (!empty($logradouro)) {
        // remove espaços duplos
        $logradouro = preg_replace('/\s+/', ' ', $logradouro);
        // tratar formatos como "R 1", "R. 1", "RUA 1" etc.
        $patterns = array(
            '/^R\s+/i', '/^R\.\s*/i', '/^RUA\s+/i',
            '/^AV\s+/i', '/^AV\.\s*/i', '/^AVENIDA\s+/i',
            '/^TRAV\s+/i', '/^TRAV\.\s*/i', '/^TRAVESSA\s+/i',
            '/^PRAÇA\s+/i', '/^PR\s+/i', '/^PR\.\s*/i'
        );
        $replacements = array(
            'Rua ', 'Rua ', 'Rua ',
            'Avenida ', 'Avenida ', 'Avenida ',
            'Travessa ', 'Travessa ', 'Travessa ',
            'Praça ', 'Praça ', 'Praça '
        );
        $logradouro = preg_replace($patterns, $replacements, $logradouro);
        $logradouro = trim($logradouro);
        // capitalizar primeira letra (simples)
        $logradouro = mb_convert_case($logradouro, MB_CASE_TITLE, "UTF-8");
    }

    // Normaliza cidade / uf (trim + uppercase uf)
    $cidade = mb_convert_case($cidade, MB_CASE_TITLE, "UTF-8");
    $uf = strtoupper($uf);

    // Normaliza bairro
    $bairro = mb_convert_case($bairro, MB_CASE_TITLE, "UTF-8");

    return array(
        'logradouro' => $logradouro,
        'numero' => $numero,
        'bairro' => $bairro,
        'cidade' => $cidade,
        'uf' => $uf,
        'cep' => $cep
    );
}

/**
 * Obtém coordenadas do CEP (ViaCEP + Nominatim)
 * Compatível com PHP 5.3.
 *
 * (Mantive disponível caso queira tentar apenas pelo CEP primeiro)
 */
function getCoordinatesForCEP($cep) {
    $cep = preg_replace('/[^0-9]/', '', $cep);

    if (empty($cep)) {
        return null;
    }

    // ViaCEP
    $viaCepUrl = "https://viacep.com.br/ws/{$cep}/json/";
    $addressJson = @file_get_contents($viaCepUrl);

    if ($addressJson === false) {
        return null;
    }

    $addressData = json_decode($addressJson, true);

    if (!is_array($addressData) || (isset($addressData['erro']) && $addressData['erro'])) {
        return null;
    }

    // Query para Nominatim com partes do ViaCEP
    $queryString = http_build_query(array(
        'street'     => $addressData['logradouro'],
        'city'       => $addressData['localidade'],
        'state'      => $addressData['uf'],
        'postalcode' => $addressData['cep'],
        'country'    => 'Brasil',
        'format'     => 'json',
        'limit'      => 1
    ));

    $nominatimUrl = "https://nominatim.openstreetmap.org/search?{$queryString}";

    $options = array(
        'http' => array(
            'header' => "User-Agent: LuminaTrack/1.0\r\n"
        )
    );

    $context = stream_context_create($options);
    $geoJson = @file_get_contents($nominatimUrl, false, $context);

    if ($geoJson === false) {
        return null;
    }

    $geoData = json_decode($geoJson, true);

    if (!is_array($geoData) || empty($geoData)) {
        return null;
    }

    return array(
        'lat' => $geoData[0]['lat'],
        'lng' => $geoData[0]['lon']
    );
}

/**
 * Obtém coordenadas usando múltiplos níveis de fallback
 *
 * Tenta:
 *  1) logradouro + número + bairro + cidade + uf
 *  2) logradouro + bairro + cidade + uf
 *  3) bairro + cidade + uf
 *  4) cep + cidade + uf
 *
 * Retorna array('lat' => ..., 'lng' => ...) ou null
 */
function getCoordinatesFromAddress($logradouro, $numero, $bairro, $cidade, $uf, $cep = null) {

    // Garante que todos os valores são strings (evita warnings)
    $logradouro = isset($logradouro) ? trim((string)$logradouro) : '';
    $numero = isset($numero) ? trim((string)$numero) : '';
    $bairro = isset($bairro) ? trim((string)$bairro) : '';
    $cidade = isset($cidade) ? trim((string)$cidade) : '';
    $uf = isset($uf) ? trim((string)$uf) : '';
    $cep = isset($cep) ? trim((string)$cep) : '';

    $attempts = array();

    // 1⃣ endereço completo (com número se existir)
    if (!empty($logradouro) && !empty($numero)) {
        $attempts[] = $logradouro . ' ' . $numero . ', ' . $bairro . ', ' . $cidade . ' - ' . $uf . ', Brasil';
    }

    // 2⃣ sem número, mas com logradouro
    if (!empty($logradouro)) {
        $attempts[] = $logradouro . ', ' . $bairro . ', ' . $cidade . ' - ' . $uf . ', Brasil';
    }

    // 3⃣ bairro + cidade
    if (!empty($bairro) && !empty($cidade)) {
        $attempts[] = $bairro . ', ' . $cidade . ' - ' . $uf . ', Brasil';
    }

    // 4⃣ CEP (se disponível)
    $cep_digits = preg_replace('/\D/', '', $cep);
    if (!empty($cep_digits)) {
        $attempts[] = $cep_digits . ', ' . $cidade . ' - ' . $uf . ', Brasil';
    }

    foreach ($attempts as $addr) {

        $queryString = http_build_query(array(
            'q'      => $addr,
            'format' => 'json',
            'limit'  => 1
        ));

        $url = "https://nominatim.openstreetmap.org/search?{$queryString}";

        $opts = array(
            'http' => array(
                'header' => "User-Agent: LuminaTrack/1.0\r\n"
            )
        );

        $context = stream_context_create($opts);
        $response = @file_get_contents($url, false, $context);

        if (!$response) {
            // se der erro na requisição (ex.: bloqueio), tenta próxima
            continue;
        }

        $data = json_decode($response, true);

        if (is_array($data) && !empty($data)) {
            return array(
                'lat' => $data[0]['lat'],
                'lng' => $data[0]['lon']
            );
        }
    }

    return null; // nenhuma tentativa obteve resultado
}

/**
 * Retorna entrega pela chave.
 */
function getDeliveryByNFeKey($nfe_key, $conn) {
    $stmt = $conn->prepare("SELECT * FROM entregas WHERE nfe_key = ?");
    $stmt->bind_param("s", $nfe_key);
    $stmt->execute();

    $result = $stmt->get_result();
    if (!$result) {
        return null;
    }

    return $result->fetch_assoc();
}

/**
 * Salva entrega.
 */
function saveDelivery($data, $conn) {

    $stmt = $conn->prepare(
        "INSERT INTO entregas (
            nfe_key, dest_name, dest_cep, dest_logradouro,
            dest_numero, dest_bairro, dest_municipio, dest_uf,
            dest_lat, dest_lng
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        error_log("Prepare error: " . $conn->error);
        return false;
    }

    // Obs: mantive "d" para lat/lng (double). Se for null, ainda funciona — mysqli aceita null ao enviar variável PHP null.
    $stmt->bind_param(
        "ssssssssdd",
        $data['nfe_key'],
        $data['dest_name'],
        $data['dest_cep'],
        $data['dest_logradouro'],
        $data['dest_numero'],
        $data['dest_bairro'],
        $data['dest_municipio'],
        $data['dest_uf'],
        $data['dest_lat'],
        $data['dest_lng']
    );

    if ($stmt->execute()) {
        return $stmt->insert_id;
    } else {
        error_log("Execute error: " . $stmt->error);
    }

    return false;
}

/**
 * Salva evento.
 */
function saveEvent($data, $conn) {
    $stmt = $conn->prepare(
        "INSERT INTO eventos (entrega_id, status, event_date) VALUES (?, ?, ?)"
    );

    $stmt->bind_param(
        "iss",
        $data['entrega_id'],
        $data['status'],
        $data['event_date']
    );

    return $stmt->execute();
}

?>


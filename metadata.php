<?php
/**
 * Proxy para buscar metadados do stream Icecast/Shoutcast
 * Contorna problemas de CORS permitindo que o site acesse os dados
 */

// Headers CORS - permite acesso de qualquer origem
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// URL do servidor de streaming
$streamServer = 'https://usu300opw28m.shares.zrok.io';

// Endpoints para tentar
$endpoints = [
    '/status-json.xsl',
    '/7.html'
];

$response = null;
$error = null;

// Tenta cada endpoint
foreach ($endpoints as $endpoint) {
    $url = $streamServer . $endpoint;
    
    try {
        // Configura contexto da requisição
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'user_agent' => 'ForroStreamMOC/1.0',
                'ignore_errors' => true
            ]
        ]);
        
        // Faz a requisição
        $result = file_get_contents($url, false, $context);
        
        if ($result !== false) {
            $response = $result;
            
            // Se for JSON, valida
            if (strpos($endpoint, 'json') !== false) {
                $json = json_decode($result, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    break; // JSON válido, sai do loop
                }
            } else {
                // Para 7.html, verifica se tem conteúdo
                if (strlen($result) > 0 && strpos($result, ',') !== false) {
                    break; // Tem conteúdo, sai do loop
                }
            }
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
        continue;
    }
}

// Retorna a resposta
if ($response) {
    echo $response;
} else {
    http_response_code(502);
    echo json_encode([
        'error' => 'Failed to fetch metadata',
        'details' => $error,
        'tried_urls' => array_map(function($e) use ($streamServer) {
            return $streamServer . $e;
        }, $endpoints)
    ]);
}
?>

<?php
/**
 * Proxy para buscar metadados do stream Icecast/Shoutcast
 * Contorna problemas de CORS permitindo que o site acesse os dados
 * PRIORIZA leitura do arquivo nowplaying.txt do RadioBOSS
 */

// Headers CORS - permite acesso de qualquer origem
header('Content-Type: text/plain; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// OPÇÃO 1: Ler arquivo nowplaying.txt LOCAL da hospedagem (MAIS SIMPLES)
// Se você fizer upload do nowplaying.txt para a mesma pasta do metadata.php
$nowPlayingFile = __DIR__ . '/nowplaying.txt';

if (file_exists($nowPlayingFile)) {
    // Lê o arquivo como bytes brutos
    $content = file_get_contents($nowPlayingFile);
    if ($content && trim($content) !== '') {
        // Remove BOM se existir (UTF-8 BOM)
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $content = substr($content, 3);
        }
        
        // Detecta encoding e converte para UTF-8 se necessário
        // RadioBOSS geralmente salva em Windows-1252/ANSI
        $encoding = mb_detect_encoding($content, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true);
        
        // Se detectou Windows-1252 ou ISO-8859-1, converte para UTF-8
        if ($encoding === 'Windows-1252' || $encoding === 'ISO-8859-1') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        // Se já é UTF-8 ou não detectou, usa como está
        
        echo trim($content);
        exit;
    }
}

// OPÇÃO 2: Buscar do servidor Python via Zrok (REQUER CONFIGURAÇÃO)
// Descomente as linhas abaixo se configurar o Zrok
/*
$pythonServerUrl = 'https://SUA-URL-ZROK.shares.zrok.io/nowplaying.txt';

try {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 3,
            'user_agent' => 'ForroStreamMOC-MetadataProxy/1.0',
            'ignore_errors' => true
        ]
    ]);
    
    $content = file_get_contents($pythonServerUrl, false, $context);
    
    if ($content !== false && trim($content) !== '' && strlen(trim($content)) > 0) {
        $encoding = mb_detect_encoding($content, ['UTF-8', 'ISO-8859-1', 'Windows-1252'], true);
        if ($encoding && $encoding !== 'UTF-8') {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        
        echo trim($content);
        exit;
    }
} catch (Exception $e) {
    // Falhou, continua para fallback
}
*/

// Se não encontrou nowplaying.txt, tenta endpoints do Icecast
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

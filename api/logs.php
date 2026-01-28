<?php
/**
 * API de Logs
 * 
 * Retorna as últimas N linhas do arquivo de log de sincronização
 * Usado pelo monitor.html para exibir logs em tempo real
 */

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

// Permitir CORS para desenvolvimento local
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

// Configurações
$logFile = __DIR__ . '/../logs/sync.log';
$defaultLines = 200;

// Número de linhas a retornar
$lines = isset($_GET['lines']) ? (int)$_GET['lines'] : $defaultLines;
$lines = max(1, min($lines, 1000)); // Limitar entre 1 e 1000 linhas

/**
 * Lê as últimas N linhas de um arquivo
 * Usa tail para performance em arquivos grandes
 */
function getLastLines($file, $numLines) {
    if (!file_exists($file)) {
        return null;
    }

    if (!is_readable($file)) {
        return false;
    }

    // Verificar se o arquivo está vazio
    if (filesize($file) === 0) {
        return '';
    }

    // Usar tail para performance (funciona em Linux/Mac)
    if (function_exists('exec') && strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
        $output = [];
        $command = sprintf('tail -n %d %s 2>&1', $numLines, escapeshellarg($file));
        exec($command, $output, $returnCode);
        
        if ($returnCode === 0) {
            return implode("\n", $output);
        }
    }

    // Fallback: ler arquivo inteiro (menos eficiente para arquivos grandes)
    $fileLines = file($file, FILE_IGNORE_NEW_LINES);
    if ($fileLines === false) {
        return false;
    }

    $totalLines = count($fileLines);
    $startLine = max(0, $totalLines - $numLines);
    $lastLines = array_slice($fileLines, $startLine);
    
    return implode("\n", $lastLines);
}

/**
 * Retorna informações sobre o arquivo de log
 */
function getLogInfo($file) {
    if (!file_exists($file)) {
        return [
            'exists' => false,
            'size' => 0,
            'modified' => null,
            'readable' => false
        ];
    }

    return [
        'exists' => true,
        'size' => filesize($file),
        'modified' => filemtime($file),
        'readable' => is_readable($file),
        'path' => $file
    ];
}

// Processar requisição
try {
    $logContent = getLastLines($logFile, $lines);
    $logInfo = getLogInfo($logFile);

    if ($logContent === null) {
        // Arquivo não existe
        http_response_code(200); // Ainda retorna 200, mas com mensagem
        echo json_encode([
            'success' => true,
            'logs' => '',
            'message' => 'Arquivo de log não encontrado. Execute uma sincronização primeiro.',
            'lineCount' => 0,
            'info' => $logInfo
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($logContent === false) {
        // Erro ao ler arquivo
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Erro ao ler arquivo de log',
            'message' => 'Verifique as permissões do arquivo',
            'info' => $logInfo
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Sucesso
    $actualLines = $logContent === '' ? 0 : count(explode("\n", $logContent));
    
    echo json_encode([
        'success' => true,
        'logs' => $logContent,
        'lineCount' => $actualLines,
        'requestedLines' => $lines,
        'info' => $logInfo,
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erro interno do servidor',
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

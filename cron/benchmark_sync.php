#!/usr/bin/env php
<?php
/**
 * Script de Benchmark de Performance da Sincronização
 * Mede o tempo e recursos utilizados durante a sincronização
 */

require_once __DIR__ . '/../services/SyncService.php';

// Configurar para execução CLI
set_time_limit(0);
ini_set('memory_limit', '512M');

echo "================================================\n";
echo "   BENCHMARK DE PERFORMANCE - SINCRONIZAÇÃO\n";
echo "================================================\n\n";

// Captura uso de memória inicial
$memoryStart = memory_get_usage();
$memoryPeakStart = memory_get_peak_usage();

// Captura tempo inicial
$timeStart = microtime(true);

echo "Início: " . date('Y-m-d H:i:s') . "\n";
echo "Memória inicial: " . number_format($memoryStart / 1024 / 1024, 2) . " MB\n";
echo "------------------------------------------------\n\n";

try {
    $sync = new SyncService();
    
    echo "Executando sincronização...\n\n";
    $stats = $sync->syncEvents();
    
    // Captura tempo final
    $timeEnd = microtime(true);
    $executionTime = $timeEnd - $timeStart;
    
    // Captura uso de memória final
    $memoryEnd = memory_get_usage();
    $memoryPeakEnd = memory_get_peak_usage();
    
    echo "\n================================================\n";
    echo "           RESULTADOS DO BENCHMARK\n";
    echo "================================================\n\n";
    
    echo "--- ESTATÍSTICAS DE SINCRONIZAÇÃO ---\n";
    echo "Total de eventos: {$stats['total']}\n";
    echo "Novos: {$stats['novos']}\n";
    echo "Atualizados: {$stats['atualizados']}\n";
    echo "Erros: {$stats['erros']}\n\n";
    
    echo "--- PERFORMANCE ---\n";
    echo "Tempo total: " . formatTime($executionTime) . "\n";
    echo "Tempo médio por evento: " . number_format(($executionTime / max($stats['total'], 1)) * 1000, 2) . " ms\n";
    echo "Eventos por segundo: " . number_format($stats['total'] / max($executionTime, 0.001), 2) . "\n\n";
    
    echo "--- USO DE MEMÓRIA ---\n";
    echo "Memória inicial: " . formatBytes($memoryStart) . "\n";
    echo "Memória final: " . formatBytes($memoryEnd) . "\n";
    echo "Memória utilizada: " . formatBytes($memoryEnd - $memoryStart) . "\n";
    echo "Pico de memória: " . formatBytes($memoryPeakEnd) . "\n";
    echo "Média de memória por evento: " . formatBytes(($memoryEnd - $memoryStart) / max($stats['total'], 1)) . "\n\n";
    
    echo "--- ESTIMATIVAS ---\n";
    echo "Tempo estimado para 10k eventos: " . formatTime(($executionTime / max($stats['total'], 1)) * 10000) . "\n";
    echo "Tempo estimado para 20k eventos: " . formatTime(($executionTime / max($stats['total'], 1)) * 20000) . "\n";
    echo "Tempo estimado para 50k eventos: " . formatTime(($executionTime / max($stats['total'], 1)) * 50000) . "\n\n";
    
    echo "================================================\n";
    echo "Fim: " . date('Y-m-d H:i:s') . "\n";
    echo "================================================\n";
    
    // Salva resultado em arquivo JSON
    $benchmarkResult = [
        'timestamp' => date('Y-m-d H:i:s'),
        'stats' => $stats,
        'performance' => [
            'execution_time_seconds' => $executionTime,
            'execution_time_formatted' => formatTime($executionTime),
            'time_per_event_ms' => ($executionTime / max($stats['total'], 1)) * 1000,
            'events_per_second' => $stats['total'] / max($executionTime, 0.001)
        ],
        'memory' => [
            'start_bytes' => $memoryStart,
            'end_bytes' => $memoryEnd,
            'used_bytes' => $memoryEnd - $memoryStart,
            'peak_bytes' => $memoryPeakEnd,
            'start_formatted' => formatBytes($memoryStart),
            'end_formatted' => formatBytes($memoryEnd),
            'used_formatted' => formatBytes($memoryEnd - $memoryStart),
            'peak_formatted' => formatBytes($memoryPeakEnd)
        ]
    ];
    
    $benchmarkFile = __DIR__ . '/../logs/benchmark_' . date('Ymd_His') . '.json';
    file_put_contents($benchmarkFile, json_encode($benchmarkResult, JSON_PRETTY_PRINT));
    echo "\nResultados salvos em: $benchmarkFile\n";
    
    exit(0);
    
} catch (Exception $e) {
    echo "\n\nERRO: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

/**
 * Formata bytes em formato legível
 */
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

/**
 * Formata tempo em formato legível
 */
function formatTime($seconds) {
    if ($seconds < 60) {
        return number_format($seconds, 2) . ' segundos';
    } elseif ($seconds < 3600) {
        $minutes = floor($seconds / 60);
        $secs = $seconds % 60;
        return $minutes . ' min ' . number_format($secs, 0) . ' seg';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return $hours . ' h ' . $minutes . ' min ' . number_format($secs, 0) . ' seg';
    }
}

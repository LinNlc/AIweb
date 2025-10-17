<?php
declare(strict_types=1);

require_once __DIR__ . '/Storage.php';

/**
 * 进度日志存取工具：负责 JSONL 的落地与读取。
 */
function progress_log_path(): string
{
    return ensure_storage_path('logs/progress.jsonl');
}

/**
 * 追加一条调度进度日志。
 */
function append_progress_log(array $entry): void
{
    $path = progress_log_path();
    $payload = ['timestamp' => date('c')] + $entry;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * 读取最近的进度日志，默认返回最新的 100 条。
 */
function read_progress_logs(int $limit = 100, ?string $team = null): array
{
    $path = progress_log_path();
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || !$lines) {
        return [];
    }
    $items = [];
    for ($i = count($lines) - 1; $i >= 0 && count($items) < $limit; $i--) {
        $line = $lines[$i];
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if ($team !== null && isset($decoded['team']) && $decoded['team'] !== $team) {
            continue;
        }
        $items[] = $decoded;
    }
    return array_reverse($items);
}

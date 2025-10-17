<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Rules.php';
require_once __DIR__ . '/../core/Utils.php';

/**
 * 进度日志 API：提供进度拉取与追加接口，供前端展示排班任务日志。
 */
function handle_progress_request(string $method, string $path): bool
{
    switch (true) {
        case $method === 'GET' && $path === '/progress':
            progress_list();
            return true;
        case $method === 'POST' && $path === '/progress/log':
            progress_append();
            return true;
        default:
            return false;
    }
}

/**
 * GET /progress
 * 返回最近的进度日志，默认 100 条，可按团队过滤。
 */
function progress_list(): void
{
    $limitRaw = $_GET['limit'] ?? 100;
    $limit = is_numeric($limitRaw) ? (int) $limitRaw : 100;
    if ($limit <= 0) {
        $limit = 100;
    }
    $limit = max(1, min(300, $limit));

    $team = $_GET['team'] ?? null;
    if ($team !== null && $team !== '') {
        $team = normalize_team_identifier($team);
    } else {
        $team = null;
    }

    $items = read_progress_logs($limit, $team);
    send_json(['items' => $items, 'limit' => $limit]);
}

/**
 * POST /progress/log
 * 写入一条新的排班进度日志。
 */
function progress_append(): void
{
    $payload = json_input();
    $message = isset($payload['message']) ? trim((string) $payload['message']) : '';
    if ($message === '') {
        send_error('缺少日志内容', 422);
    }

    $team = isset($payload['team']) ? normalize_team_identifier((string) $payload['team']) : 'default';
    $entry = [
        'team' => $team,
        'message' => $message,
    ];

    if (isset($payload['stage']) && $payload['stage'] !== '') {
        $entry['stage'] = (string) $payload['stage'];
    }
    if (isset($payload['progress']) && $payload['progress'] !== '') {
        $entry['progress'] = max(0, min(100, (int) $payload['progress']));
    }
    if (isset($payload['context']) && is_array($payload['context'])) {
        $entry['context'] = $payload['context'];
    }

    append_progress_log($entry);
    send_json(['ok' => true]);
}

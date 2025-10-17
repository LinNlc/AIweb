<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Scheduler.php';
require_once __DIR__ . '/../core/DTO.php';
require_once __DIR__ . '/../core/Rules.php';
require_once __DIR__ . '/../core/Repository.php';
require_once __DIR__ . '/../core/Progress.php';

/**
 * 处理排班相关的 API 路由。
 *
 * @param string $method HTTP 请求方法
 * @param string $path   归一化后的请求路径（去除 /api 前缀）
 *
 * @return bool 当路由已被处理时返回 true，便于上层提前结束。
 */
function handle_schedule_request(string $method, string $path): bool
{
    switch (true) {
        case $method === 'GET' && $path === '/schedule':
            schedule_fetch_latest();
            return true;

        case $method === 'POST' && $path === '/schedule/save':
            schedule_save_version();
            return true;

        default:
            return false;
    }
}

/**
 * GET /schedule
 * 读取某个团队（可选指定时间范围）的最新排班版本。
 */
function schedule_fetch_latest(): void
{
    $team = normalize_team_identifier($_GET['team'] ?? 'default');
    $start = (string) ($_GET['start'] ?? '');
    $end = (string) ($_GET['end'] ?? '');
    $historyYearStart = (string) ($_GET['historyYearStart'] ?? ($_GET['history_year_start'] ?? ''));

    if ($historyYearStart === '') {
        $historyYearStart = null;
    } elseif (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $historyYearStart)) {
        $historyYearStart = null;
    }
    if (!$team) {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $rangeStart = $start !== '' ? $start : null;
    $rangeEnd = $end !== '' ? $end : null;
    $row = repo_schedule_find_latest($pdo, $team, $rangeStart, $rangeEnd);

    if (!$row) {
        $viewStart = $start ?: date('Y-m-01');
        $viewEnd = $end ?: date('Y-m-t');
        $historyProfile = compute_history_profile($pdo, $team, $rangeStart, $historyYearStart);
        send_json([
            'team' => $team,
            'viewStart' => $viewStart,
            'viewEnd' => $viewEnd,
            'start' => $viewStart,
            'end' => $viewEnd,
            'employees' => [],
            'data' => (object) [],
            'note' => '',
            'created_at' => null,
            'created_by_name' => null,
            'version_id' => null,
            'versionId' => null,
            'historyProfile' => $historyProfile,
            'yearlyOptimize' => false,
        ]);
    }

    $result = build_schedule_payload($row, $team);
    $profileStart = $rangeStart ?: ($row['view_start'] ?? null);
    $result['historyProfile'] = compute_history_profile($pdo, $team, $profileStart, $historyYearStart);
    send_json($result);
}

/**
 * POST /schedule/save
 * 保存新的排班版本，带乐观锁校验。
 */
function schedule_save_version(): void
{
    $in = json_input();
    try {
        $normalized = normalize_schedule_request($in);
    } catch (\InvalidArgumentException $e) {
        send_error($e->getMessage(), 422);
    }

    $team = $normalized['team'];
    $viewStart = $normalized['viewStart'];
    $viewEnd = $normalized['viewEnd'];
    $employees = $normalized['employees'];
    $data = $normalized['data'];
    $baseVersion = $normalized['baseVersionId'];
    $note = $normalized['note'];
    $operator = $normalized['operator'];

    $cleanInput = $in;
    $cleanInput['team'] = $team;
    $cleanInput['viewStart'] = $viewStart;
    $cleanInput['viewEnd'] = $viewEnd;
    $cleanInput['employees'] = $employees;
    $cleanInput['data'] = $data;
    $cleanInput['note'] = $note;
    $cleanInput['operator'] = $operator;

    $pdo = db();
    $latestId = repo_schedule_latest_id($pdo, $team, $viewStart, $viewEnd);

    if ($latestId !== null && $baseVersion !== null && (int) $baseVersion !== $latestId) {
        send_error('保存冲突：已有新版本', 409, [
            'code' => 409,
            'latest_version_id' => $latestId,
        ]);
    }

    $snapshot = build_snapshot_payload($cleanInput, $team, $viewStart, $viewEnd, $employees, $data, $note, $operator);
    $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE);
    if ($snapshotJson === false) {
        $snapshotJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    $employeesJson = json_encode(array_values($employees), JSON_UNESCAPED_UNICODE);
    if ($employeesJson === false) {
        $employeesJson = json_encode(array_values($employees), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '[]';
    }

    $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE);
    if ($dataJson === false) {
        $dataJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) ?: '{}';
    }

    $newId = repo_schedule_insert_version($pdo, [
        'team' => $team,
        'employees' => $employeesJson,
        'data' => $dataJson,
        'view_start' => $viewStart,
        'view_end' => $viewEnd,
        'note' => $note,
        'created_at' => date('Y-m-d H:i:s'),
        'created_by_name' => $operator,
        'payload' => $snapshotJson,
    ]);

    append_progress_log([
        'team' => $team,
        'stage' => 'schedule_save',
        'message' => sprintf('保存排班版本（%s ~ %s）', $viewStart, $viewEnd),
        'progress' => 100,
        'context' => [
            'versionId' => $newId,
            'operator' => $operator,
        ],
    ]);

    send_json(['ok' => true, 'version_id' => $newId]);
}

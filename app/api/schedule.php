<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Scheduler.php';
require_once __DIR__ . '/../core/DTO.php';
require_once __DIR__ . '/../core/Rules.php';

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
    $row = null;

    if ($start && $end) {
        $stmt = $pdo->prepare(
            'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload
             FROM schedule_versions
             WHERE team=? AND view_start=? AND view_end=?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$team, $start, $end]);
        $row = $stmt->fetch();
    }

    if (!$row) {
        $stmt = $pdo->prepare(
            'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload
             FROM schedule_versions
             WHERE team=?
             ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$team]);
        $row = $stmt->fetch();
    }

    if (!$row) {
        $viewStart = $start ?: date('Y-m-01');
        $viewEnd = $end ?: date('Y-m-t');
        $historyProfile = compute_history_profile($pdo, $team, $start ?: null, $historyYearStart);
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
    $rangeStart = $start ?: ($row['view_start'] ?? null);
    $result['historyProfile'] = compute_history_profile($pdo, $team, $rangeStart, $historyYearStart);
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
    $stmt = $pdo->prepare(
        'SELECT id FROM schedule_versions
         WHERE team=? AND view_start=? AND view_end=?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$team, $viewStart, $viewEnd]);
    $current = $stmt->fetch();
    $latestId = $current ? (int) $current['id'] : null;

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

    $insert = $pdo->prepare(
        'INSERT INTO schedule_versions(team, view_start, view_end, employees, data, note, created_by_name, payload)
         VALUES(?,?,?,?,?,?,?,?)'
    );
    $insert->execute([
        $team,
        $viewStart,
        $viewEnd,
        $employeesJson,
        $dataJson,
        $note,
        $operator,
        $snapshotJson,
    ]);

    $newId = (int) $pdo->lastInsertId();

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

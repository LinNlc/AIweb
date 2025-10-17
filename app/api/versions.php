<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/Scheduler.php';
require_once __DIR__ . '/../core/DTO.php';
require_once __DIR__ . '/../core/Repository.php';
require_once __DIR__ . '/../core/Exporter.php';

/**
 * 处理排班历史版本与导出相关的 API 路由。
 *
 * @param string $method HTTP 请求方法
 * @param string $path   归一化后的请求路径（去除 /api 前缀）
 *
 * @return bool 已完成响应时返回 true。
 */
function handle_versions_request(string $method, string $path): bool
{
    switch (true) {
        case $method === 'GET' && $path === '/schedule/versions':
            versions_list();
            return true;

        case $method === 'GET' && $path === '/version':
            versions_fetch_by_id();
            return true;

        case $method === 'POST' && $path === '/schedule/version/delete':
            versions_delete();
            return true;

        case $method === 'GET' && $path === '/export/xlsx':
            versions_export_spreadsheet();
            return true;

        default:
            return false;
    }
}

/**
 * GET /schedule/versions
 * 列出指定团队在时间范围内的历史版本（最多 200 条）。
 */
function versions_list(): void
{
    $team = (string) ($_GET['team'] ?? 'default');
    $start = trim((string) ($_GET['start'] ?? ''));
    $end = trim((string) ($_GET['end'] ?? ''));

    if ($team === '') {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $rangeStart = $start !== '' ? $start : null;
    $rangeEnd = $end !== '' ? $end : null;
    $rows = repo_schedule_list_versions($pdo, $team, $rangeStart, $rangeEnd);
    $versions = array_map(static function ($row) {
        return [
            'id' => (int) $row['id'],
            'view_start' => $row['view_start'] ?? null,
            'view_end' => $row['view_end'] ?? null,
            'created_at' => $row['created_at'],
            'note' => $row['note'] ?? '',
            'created_by_name' => $row['created_by_name'] ?? '管理员',
        ];
    }, $rows);

    send_json(['versions' => $versions]);
}

/**
 * GET /version
 * 根据 ID 获取完整排班版本详情。
 */
function versions_fetch_by_id(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $row = repo_schedule_fetch_by_id($pdo, $id);

    if (!$row) {
        send_error('未找到', 404);
    }

    $team = $row['team'] ?? 'default';
    $result = build_schedule_payload($row, $team);
    $rangeStart = $result['viewStart'] ?? ($row['view_start'] ?? null);
    $result['historyProfile'] = compute_history_profile($pdo, $team, $rangeStart, null);
    send_json($result);
}

/**
 * POST /schedule/version/delete
 * 删除指定团队的单个历史版本。
 */
function versions_delete(): void
{
    $in = json_input();
    $id = (int) ($in['id'] ?? 0);
    $team = trim((string) ($in['team'] ?? ''));

    if ($id <= 0 || $team === '') {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $deleted = repo_schedule_delete($pdo, $id, $team);

    if (!$deleted) {
        send_error('记录不存在或已删除', 404);
    }

    send_json(['ok' => true, 'deleted' => true]);
}

/**
 * GET /export/xlsx
 * 导出当前视图的排班为 XLSX，失败时回退 CSV。
 */
function versions_export_spreadsheet(): void
{
    $team = (string) ($_GET['team'] ?? 'default');
    $start = trim((string) ($_GET['start'] ?? ''));
    $end = trim((string) ($_GET['end'] ?? ''));

    if ($team === '' || $start === '' || $end === '') {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $row = repo_schedule_find_by_range($pdo, $team, $start, $end);

    $employees = $row ? (json_decode($row['employees'], true) ?: []) : [];
    $data = $row ? (json_decode($row['data'], true) ?: []) : [];

    exporter_send_schedule($start, $end, $employees, $data);
}

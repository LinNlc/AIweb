<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/ScheduleService.php';

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

    $result = service_schedule_fetch($pdo, $team, $rangeStart, $rangeEnd, $historyYearStart);
    send_json($result);
}

/**
 * POST /schedule/save
 * 保存新的排班版本，带乐观锁校验。
 */
function schedule_save_version(): void
{
    $in = json_input();
    $pdo = db();

    try {
        $result = service_schedule_save($pdo, $in);
    } catch (\InvalidArgumentException $e) {
        send_error($e->getMessage(), 422);
        return;
    } catch (ScheduleConflictException $e) {
        send_error($e->getMessage(), 409, [
            'code' => 409,
            'latest_version_id' => $e->getLatestVersionId(),
        ]);
        return;
    }

    send_json(['ok' => true, 'version_id' => $result['version_id']]);
}

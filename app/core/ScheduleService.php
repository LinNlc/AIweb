<?php
declare(strict_types=1);

require_once __DIR__ . '/Rules.php';
require_once __DIR__ . '/DTO.php';
require_once __DIR__ . '/Scheduler.php';
require_once __DIR__ . '/Repository.php';
require_once __DIR__ . '/Progress.php';

/**
 * 自定义异常：用于标记排班保存时出现的乐观锁冲突。
 */
class ScheduleConflictException extends RuntimeException
{
    /** @var int 最新版本号 */
    private int $latestId;

    public function __construct(string $message, int $latestId)
    {
        parent::__construct($message, 409);
        $this->latestId = $latestId;
    }

    /**
     * 获取当前数据库中的最新排班版本 ID，方便接口层返回给前端。
     */
    public function getLatestVersionId(): int
    {
        return $this->latestId;
    }
}

/**
 * 汇总排班查询的核心流程，返回给前端所需的完整载荷。
 *
 * @param PDO         $pdo               数据库连接
 * @param string      $team              团队标识（已归一化）
 * @param string|null $rangeStart        查询区间的开始日期
 * @param string|null $rangeEnd          查询区间的结束日期
 * @param string|null $historyYearStart  历史统计的年份起点
 *
 * @return array<string,mixed>
 */
function service_schedule_fetch(
    PDO $pdo,
    string $team,
    ?string $rangeStart,
    ?string $rangeEnd,
    ?string $historyYearStart
): array {
    $row = repo_schedule_find_latest($pdo, $team, $rangeStart, $rangeEnd);

    if (!$row) {
        $viewStart = $rangeStart ?: date('Y-m-01');
        $viewEnd = $rangeEnd ?: date('Y-m-t');
        return [
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
            'historyProfile' => compute_history_profile($pdo, $team, $rangeStart, $historyYearStart),
            'yearlyOptimize' => false,
        ];
    }

    $result = build_schedule_payload($row, $team);
    $profileStart = $rangeStart ?: ($row['view_start'] ?? null);
    $result['historyProfile'] = compute_history_profile($pdo, $team, $profileStart, $historyYearStart);

    return $result;
}

/**
 * 封装排班保存的通用流程，包含乐观锁校验与快照构建。
 *
 * @param PDO  $pdo   数据库连接
 * @param array<string,mixed> $input 原始请求参数
 *
 * @throws InvalidArgumentException 参数校验失败
 * @throws ScheduleConflictException 出现乐观锁冲突
 *
 * @return array<string,mixed> 返回保存后的关键信息
 */
function service_schedule_save(PDO $pdo, array $input): array
{
    $normalized = normalize_schedule_request($input);

    $team = $normalized['team'];
    $viewStart = $normalized['viewStart'];
    $viewEnd = $normalized['viewEnd'];
    $employees = $normalized['employees'];
    $data = $normalized['data'];
    $baseVersion = $normalized['baseVersionId'];
    $note = $normalized['note'];
    $operator = $normalized['operator'];

    $latestId = repo_schedule_latest_id($pdo, $team, $viewStart, $viewEnd);
    if ($latestId !== null && $baseVersion !== null && (int) $baseVersion !== $latestId) {
        throw new ScheduleConflictException('保存冲突：已有新版本', (int) $latestId);
    }

    $snapshot = build_snapshot_payload(
        $input,
        $team,
        $viewStart,
        $viewEnd,
        $employees,
        $data,
        $note,
        $operator
    );

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

    return [
        'version_id' => $newId,
        'team' => $team,
        'viewStart' => $viewStart,
        'viewEnd' => $viewEnd,
        'operator' => $operator,
    ];
}

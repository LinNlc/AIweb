<?php
declare(strict_types=1);

require_once __DIR__ . '/Validation.php';

/**
 * 排班规则与数据校验模块。
 *
 * 该文件现在聚焦于组合校验逻辑，具体字段的清洗与转换
 * 已迁移至 `Validation.php`，便于其他模块单独复用。
 */

/**
 * 综合校验排班保存请求，返回清洗后的结果。
 *
 * @throws InvalidArgumentException 当出现必填项缺失或格式错误。
 */
function normalize_schedule_request(array $input): array
{
    $team = normalize_team_identifier($input['team'] ?? '');

    $viewStart = ensure_ymd((string) ($input['viewStart'] ?? ''), '开始日期');
    $viewEnd = ensure_ymd((string) ($input['viewEnd'] ?? ''), '结束日期');
    if (strcmp($viewStart, $viewEnd) > 0) {
        throw new InvalidArgumentException('开始日期不能晚于结束日期');
    }

    $employees = normalize_employees_list($input['employees'] ?? []);
    if (!$employees) {
        throw new InvalidArgumentException('成员列表不能为空');
    }

    $data = normalize_schedule_matrix($input['data'] ?? [], $employees);
    $note = normalize_note($input['note'] ?? '');
    $operator = normalize_operator_name($input['operator'] ?? '');
    $baseVersionId = extract_base_version_id($input);

    return [
        'team' => $team,
        'viewStart' => $viewStart,
        'viewEnd' => $viewEnd,
        'employees' => $employees,
        'data' => $data,
        'note' => $note,
        'operator' => $operator,
        'baseVersionId' => $baseVersionId,
    ];
}

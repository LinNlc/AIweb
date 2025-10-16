<?php
declare(strict_types=1);

/**
 * 数据传输对象（DTO）相关的辅助函数，集中处理排班结构的装配与序列化。
 *
 * 由于前端期望的数据结构较为复杂，这里提供统一的转换工具，
 * 便于 API 层调用时复用并保持字段一致性。
 */

/**
 * 根据数据库记录组装完整的排班载荷，确保字段齐全且类型正确。
 */
function build_schedule_payload(array $row, string $teamFallback): array
{
    $employees = array_values(decode_json_assoc($row['employees'] ?? ''));
    $dataRaw = decode_json_assoc($row['data'] ?? '');
    $payloadExtra = decode_json_assoc($row['payload'] ?? '');

    $base = [
        'team' => $row['team'] ?? $teamFallback,
        'viewStart' => $row['view_start'] ?? '',
        'viewEnd' => $row['view_end'] ?? '',
        'start' => $row['view_start'] ?? '',
        'end' => $row['view_end'] ?? '',
        'employees' => $employees,
        'data' => $dataRaw,
        'note' => $row['note'] ?? '',
        'version_id' => isset($row['id']) ? (int) $row['id'] : null,
        'versionId' => isset($row['id']) ? (int) $row['id'] : null,
        'created_at' => $row['created_at'] ?? null,
        'created_by_name' => $row['created_by_name'] ?? null,
    ];

    $merged = $payloadExtra ? array_replace($base, $payloadExtra) : $base;

    if (empty($merged['team'])) {
        $merged['team'] = $teamFallback;
    }
    if (empty($merged['viewStart'])) {
        $merged['viewStart'] = $base['viewStart'];
    }
    if (empty($merged['viewEnd'])) {
        $merged['viewEnd'] = $base['viewEnd'];
    }
    if (empty($merged['start'])) {
        $merged['start'] = $merged['viewStart'];
    }
    if (empty($merged['end'])) {
        $merged['end'] = $merged['viewEnd'];
    }
    if (!isset($merged['note'])) {
        $merged['note'] = $base['note'];
    }
    if (!isset($merged['version_id'])) {
        $merged['version_id'] = $base['version_id'];
    }
    if (!isset($merged['versionId'])) {
        $merged['versionId'] = $base['versionId'];
    }
    if (!isset($merged['employees']) || !is_array($merged['employees'])) {
        $merged['employees'] = $employees;
    } else {
        $merged['employees'] = array_values($merged['employees']);
    }
    if (!isset($merged['yearlyOptimize'])) {
        $merged['yearlyOptimize'] = false;
    }

    $dataMerged = $merged['data'] ?? [];
    if (!is_array($dataMerged) || !count($dataMerged)) {
        $merged['data'] = (object) [];
    } else {
        $merged['data'] = $dataMerged;
    }

    return $merged;
}

/**
 * 构建排班版本的快照数据，用于持久化和导出。
 *
 * @param array  $input     原始请求数据
 * @param string $team      团队标识
 * @param string $viewStart 可视区间起始日期
 * @param string $viewEnd   可视区间结束日期
 * @param array  $employees 成员列表
 * @param array  $data      排班矩阵
 * @param string $note      备注信息
 * @param string $operator  操作人姓名
 */
function build_snapshot_payload(
    array $input,
    string $team,
    string $viewStart,
    string $viewEnd,
    array $employees,
    array $data,
    string $note,
    string $operator
): array {
    return [
        'team' => $team,
        'viewStart' => $viewStart,
        'viewEnd' => $viewEnd,
        'start' => $viewStart,
        'end' => $viewEnd,
        'employees' => array_values($employees),
        'data' => $data,
        'note' => $note,
        'adminDays' => $input['adminDays'] ?? null,
        'restPrefs' => $input['restPrefs'] ?? null,
        'nightRules' => $input['nightRules'] ?? null,
        'nightWindows' => $input['nightWindows'] ?? null,
        'nightOverride' => $input['nightOverride'] ?? null,
        'rMin' => $input['rMin'] ?? null,
        'rMax' => $input['rMax'] ?? null,
        'pMin' => $input['pMin'] ?? null,
        'pMax' => $input['pMax'] ?? null,
        'mixMax' => $input['mixMax'] ?? null,
        'shiftColors' => $input['shiftColors'] ?? null,
        'staffingAlerts' => $input['staffingAlerts'] ?? null,
        'batchChecked' => $input['batchChecked'] ?? null,
        'albumSelected' => $input['albumSelected'] ?? null,
        'albumWhiteHour' => $input['albumWhiteHour'] ?? null,
        'albumMidHour' => $input['albumMidHour'] ?? null,
        'albumRangeStartMonth' => $input['albumRangeStartMonth'] ?? null,
        'albumRangeEndMonth' => $input['albumRangeEndMonth'] ?? null,
        'albumMaxDiff' => $input['albumMaxDiff'] ?? null,
        'albumAssignments' => $input['albumAssignments'] ?? null,
        'albumAutoNote' => $input['albumAutoNote'] ?? null,
        'albumHistory' => $input['albumHistory'] ?? null,
        'historyProfile' => $input['historyProfile'] ?? null,
        'yearlyOptimize' => $input['yearlyOptimize'] ?? null,
        'operator' => $operator,
    ];
}

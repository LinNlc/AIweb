<?php
declare(strict_types=1);

require_once __DIR__ . '/Utils.php';

/**
 * 历史排班统计相关的核心工具函数。
 *
 * 本模块负责从排班版本仓储中回放历史数据，生成给前端展示
 * 的统计概览。与排班生成算法（Scheduler.php）解耦，便于独立
 * 演进统计维度或在后续接入更多分析能力。
 */

/**
 * 计算历史排班概况，为前端提供换班次数、最近排班等辅助信息。
 *
 * @param PDO         $pdo          SQLite 连接
 * @param string      $team         团队标识
 * @param string|null $beforeStart  统计时跳过指定日期及之后的数据
 * @param string|null $yearStart    限制统计年份起点（含）
 *
 * @return array<string,mixed>
 */
function compute_history_profile(PDO $pdo, string $team, ?string $beforeStart = null, ?string $yearStart = null): array
{
    $profile = [
        'shiftTotals' => [],
        'periodCount' => 0,
        'lastAssignments' => [],
        'ranges' => [],
    ];

    if ($beforeStart !== null && $beforeStart === '') {
        $beforeStart = null;
    }
    if ($yearStart !== null && $yearStart === '') {
        $yearStart = null;
    }

    $sql = 'SELECT id, view_start, view_end, payload, employees, data FROM schedule_versions WHERE team=?';
    $params = [$team];
    if ($beforeStart) {
        $sql .= ' AND view_end < ?';
        $params[] = $beforeStart;
    }
    $sql .= ' ORDER BY view_end DESC, id DESC LIMIT 24';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
    if (!$rows) {
        return $profile;
    }

    $lastAssignmentDay = null;

    foreach ($rows as $index => $row) {
        $payload = decode_json_assoc($row['payload'] ?? '');
        $data = $payload['data'] ?? decode_json_assoc($row['data'] ?? '');
        if (!is_array($data)) {
            $data = [];
        }
        $employees = $payload['employees'] ?? json_decode($row['employees'] ?? '[]', true);
        if (!is_array($employees)) {
            $employees = [];
        }
        foreach ($employees as $emp) {
            if (!isset($profile['shiftTotals'][$emp])) {
                $profile['shiftTotals'][$emp] = ['white' => 0, 'mid' => 0, 'mid2' => 0, 'night' => 0, 'total' => 0];
            }
        }
        $hasEligibleDay = false;
        foreach ($data as $day => $assignments) {
            if (!is_array($assignments)) {
                continue;
            }
            if ($beforeStart && strcmp((string) $day, (string) $beforeStart) >= 0) {
                continue;
            }
            if ($yearStart && strcmp((string) $day, (string) $yearStart) < 0) {
                continue;
            }
            $hasEligibleDay = true;
            foreach ($assignments as $emp => $val) {
                if (!isset($profile['shiftTotals'][$emp])) {
                    $profile['shiftTotals'][$emp] = ['white' => 0, 'mid' => 0, 'mid2' => 0, 'night' => 0, 'total' => 0];
                }
                switch ($val) {
                    case '白':
                        $profile['shiftTotals'][$emp]['white']++;
                        $profile['shiftTotals'][$emp]['total']++;
                        break;
                    case '中1':
                        $profile['shiftTotals'][$emp]['mid']++;
                        $profile['shiftTotals'][$emp]['total']++;
                        break;
                    case '中2':
                        $profile['shiftTotals'][$emp]['mid2']++;
                        $profile['shiftTotals'][$emp]['total']++;
                        break;
                    case '夜':
                        $profile['shiftTotals'][$emp]['night']++;
                        $profile['shiftTotals'][$emp]['total']++;
                        break;
                    default:
                        break;
                }
            }
            if ($lastAssignmentDay === null || strcmp((string) $day, (string) $lastAssignmentDay) > 0) {
                $lastAssignmentDay = $day;
                $profile['lastAssignments'] = is_array($assignments) ? $assignments : [];
            }
        }
        if ($hasEligibleDay) {
            $profile['periodCount']++;
            $profile['ranges'][] = [
                'start' => $row['view_start'] ?? '',
                'end' => $row['view_end'] ?? '',
                'id' => isset($row['id']) ? (int) $row['id'] : null,
            ];
        }
    }

    return $profile;
}

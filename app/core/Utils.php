<?php
declare(strict_types=1);


/**
 * 获取 SQLite 数据库连接（懒加载单例）。
 *
 * @throws PDOException 连接失败时抛出异常
 */
function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec('PRAGMA busy_timeout = 5000;');

    // 初始化排班版本表
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS schedule_versions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            team TEXT NOT NULL,
            view_start TEXT NOT NULL,
            view_end TEXT NOT NULL,
            employees TEXT NOT NULL,
            data TEXT NOT NULL,
            note TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
            created_by_name TEXT,
            payload TEXT
        );
    SQL);

    try {
        $pdo->exec('ALTER TABLE schedule_versions ADD COLUMN payload TEXT');
    } catch (Throwable $e) {
        // 已存在该列时静默忽略
    }

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_sv_team_range ON schedule_versions(team, view_start, view_end, id);');

    // 初始化组织配置表
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS org_config (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            payload TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now','localtime'))
        );
    SQL);

    return $pdo;
}

/**
 * 解析并返回 JSON 请求体。
 */
function json_input(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) {
        return [];
    }
    $arr = json_decode($raw, true);
    return is_array($arr) ? $arr : [];
}

/**
 * 以 JSON 格式输出响应并终止脚本。
 */
function send_json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 以标准错误格式返回 JSON。
 */
function send_error(string $message, int $status = 400, array $extra = []): void {
    send_json(['message' => $message] + $extra, $status);
}

/**
 * 返回两个日期（含端点）之间的全部 YYYY-MM-DD 列表。
 */
function ymd_range(string $start, string $end): array {
    $out = [];
    $s = strtotime($start);
    $e = strtotime($end);
    if ($s === false || $e === false || $s > $e) {
        return $out;
    }
    for ($t = $s; $t <= $e; $t += 86400) {
        $out[] = date('Y-m-d', $t);
    }
    return $out;
}

/**
 * 将日期转换为中文星期。
 */
function cn_week(string $ymd): string {
    $w = date('w', strtotime($ymd));
    return ['日', '一', '二', '三', '四', '五', '六'][$w] ?? '';
}

/**
 * 将 JSON 字符串安全解析为关联数组。
 */
function decode_json_assoc(?string $json): array {
    if ($json === null || $json === '') {
        return [];
    }
    $decoded = json_decode($json, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * 组装排班版本的完整载荷。
 */
function build_schedule_payload(array $row, string $teamFallback): array {
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
 * 计算历史排班概况，用于前端辅助展示。
 */
function compute_history_profile(PDO $pdo, string $team, ?string $beforeStart = null, ?string $yearStart = null): array {
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

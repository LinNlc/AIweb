<?php
declare(strict_types=1);

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

        case $method === 'GET' && $path === '/schedule/versions':
            schedule_list_versions();
            return true;

        case $method === 'GET' && $path === '/version':
            schedule_fetch_by_id();
            return true;

        case $method === 'POST' && $path === '/schedule/version/delete':
            schedule_delete_version();
            return true;

        case $method === 'GET' && $path === '/export/xlsx':
            schedule_export_spreadsheet();
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
    $team = (string) ($_GET['team'] ?? 'default');
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
    $team = (string) ($in['team'] ?? 'default');
    $viewStart = (string) ($in['viewStart'] ?? '');
    $viewEnd = (string) ($in['viewEnd'] ?? '');
    $employees = $in['employees'] ?? [];
    $data = $in['data'] ?? new stdClass();
    $baseVersion = $in['baseVersionId'] ?? null;
    $note = (string) ($in['note'] ?? '');
    $operator = trim((string) ($in['operator'] ?? '管理员')) ?: '管理员';

    if (!$team || !$viewStart || !$viewEnd || !is_array($employees) || !is_array($data)) {
        send_error('参数不合法', 400);
    }

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

    $snapshot = build_snapshot_payload($in, $team, $viewStart, $viewEnd, $employees, $data, $note, $operator);
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
    send_json(['ok' => true, 'version_id' => $newId]);
}

/**
 * GET /schedule/versions
 * 列出历史排班版本，最多返回最近 200 条。
 */
function schedule_list_versions(): void
{
    $team = (string) ($_GET['team'] ?? 'default');
    $start = (string) ($_GET['start'] ?? '');
    $end = (string) ($_GET['end'] ?? '');

    if (!$team) {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $start = $start ?: '';
    $end = $end ?: '';
    $hasRange = $start !== '' && $end !== '' && strtotime($start) !== false && strtotime($end) !== false;

    if ($hasRange && $start > $end) {
        [$start, $end] = [$end, $start];
    }

    if ($hasRange) {
        $stmt = $pdo->prepare(
            'SELECT id, view_start, view_end, created_at, note, created_by_name
             FROM schedule_versions
             WHERE team=? AND view_start >= ? AND view_end <= ?
             ORDER BY created_at DESC, id DESC
             LIMIT 200'
        );
        $stmt->execute([$team, $start, $end]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, view_start, view_end, created_at, note, created_by_name
             FROM schedule_versions
             WHERE team=?
             ORDER BY created_at DESC, id DESC
             LIMIT 200'
        );
        $stmt->execute([$team]);
    }

    $rows = $stmt->fetchAll() ?: [];
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
 * 根据 ID 获取排班版本详情。
 */
function schedule_fetch_by_id(): void
{
    $id = (int) ($_GET['id'] ?? 0);
    if ($id <= 0) {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload
         FROM schedule_versions WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

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
 * 删除指定 ID 的排班版本。
 */
function schedule_delete_version(): void
{
    $in = json_input();
    $id = (int) ($in['id'] ?? 0);
    $team = trim((string) ($in['team'] ?? ''));

    if ($id <= 0 || $team === '') {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM schedule_versions WHERE id = ? AND team = ?');
    $stmt->execute([$id, $team]);

    if ($stmt->rowCount() === 0) {
        send_error('记录不存在或已删除', 404);
    }

    send_json(['ok' => true, 'deleted' => true]);
}

/**
 * GET /export/xlsx
 * 导出当前视图的排班，优先输出 XLSX，失败时回退 CSV。
 */
function schedule_export_spreadsheet(): void
{
    $team = (string) ($_GET['team'] ?? 'default');
    $start = (string) ($_GET['start'] ?? '');
    $end = (string) ($_GET['end'] ?? '');

    if (!$team || !$start || !$end) {
        send_error('参数缺失', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        'SELECT employees, data FROM schedule_versions
         WHERE team=? AND view_start=? AND view_end=?
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$team, $start, $end]);
    $row = $stmt->fetch();

    $employees = $row ? (json_decode($row['employees'], true) ?: []) : [];
    $data = $row ? (json_decode($row['data'], true) ?: []) : [];

    $dates = ymd_range($start, $end);
    $header = array_merge(['日期', '星期'], $employees);

    $autoloadPath = __DIR__ . '/../../vendor/autoload.php';
    $hasSpreadsheet = is_file($autoloadPath);

    if ($hasSpreadsheet) {
        require_once $autoloadPath;
        try {
            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();
            $column = 1;
            foreach ($header as $text) {
                $sheet->setCellValueByColumnAndRow($column++, 1, $text);
            }
            $rowIndex = 2;
            foreach ($dates as $date) {
                $rowValues = [$date, '周' . cn_week($date)];
                foreach ($employees as $employee) {
                    $rowValues[] = $data[$date][$employee] ?? '';
                }
                $column = 1;
                foreach ($rowValues as $value) {
                    $sheet->setCellValueByColumnAndRow($column++, $rowIndex, $value);
                }
                $rowIndex++;
            }

            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="排班_' . $start . '_' . $end . '.xlsx"');

            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            $writer->save('php://output');
            exit;
        } catch (\Throwable $e) {
            // 如果生成 XLSX 失败，继续向下回退到 CSV 导出。
        }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="排班_' . $start . '_' . $end . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
    fputcsv($out, $header);

    foreach ($dates as $date) {
        $rowValues = [$date, '周' . cn_week($date)];
        foreach ($employees as $employee) {
            $rowValues[] = $data[$date][$employee] ?? '';
        }
        fputcsv($out, $rowValues);
    }

    fclose($out);
    exit;
}

/**
 * 组装排班版本的快照数据，保证字段完整性。
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

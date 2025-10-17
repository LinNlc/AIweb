<?php
declare(strict_types=1);

/**
 * 排班规则与数据校验模块。
 *
 * 用于在写库前统一清洗、验证排班请求，保证 API 层获得结构化、
 * 可预测的数据，避免出现脏数据或跨模块的隐式依赖。
 */

/**
 * 归一化团队标识，空值时回退默认团队。
 */
function normalize_team_identifier($team): string
{
    $value = trim((string) $team);
    return $value !== '' ? $value : 'default';
}

/**
 * 校验并返回合法的 YYYY-MM-DD 日期。
 *
 * @throws \InvalidArgumentException 当格式不符合预期时抛出。
 */
function ensure_ymd(string $value, string $field): string
{
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
        throw new \InvalidArgumentException(sprintf('%s 需为 YYYY-MM-DD 格式', $field));
    }
    return $value;
}

/**
 * 统一处理员工列表，去除空白并保持原有顺序。
 */
function normalize_employees_list($employees): array
{
    if ($employees instanceof \Traversable) {
        $employees = iterator_to_array($employees, false);
    }
    if ($employees instanceof \stdClass) {
        $employees = (array) $employees;
    }
    if (!is_array($employees)) {
        return [];
    }

    $result = [];
    foreach ($employees as $employee) {
        $name = trim((string) $employee);
        if ($name === '') {
            continue;
        }
        if (!in_array($name, $result, true)) {
            $result[] = $name;
        }
    }

    return $result;
}

/**
 * 归一化排班矩阵，仅保留合法的员工与非空班次。
 */
function normalize_schedule_matrix($matrix, array $employees): array
{
    if ($matrix instanceof \Traversable) {
        $matrix = iterator_to_array($matrix);
    }
    if ($matrix instanceof \stdClass) {
        $matrix = (array) $matrix;
    }
    if (!is_array($matrix)) {
        return [];
    }

    $allowedEmployees = array_fill_keys($employees, true);
    $normalized = [];

    foreach ($matrix as $day => $row) {
        $dayKey = trim((string) $day);
        if ($dayKey === '') {
            continue;
        }
        if ($row instanceof \Traversable) {
            $row = iterator_to_array($row);
        }
        if ($row instanceof \stdClass) {
            $row = (array) $row;
        }
        if (!is_array($row)) {
            continue;
        }

        $cleanRow = [];
        foreach ($row as $employee => $value) {
            if (!isset($allowedEmployees[$employee])) {
                continue;
            }
            $shift = trim((string) $value);
            if ($shift === '') {
                continue;
            }
            $cleanRow[$employee] = $shift;
        }
        ksort($cleanRow);
        $normalized[$dayKey] = $cleanRow;
    }

    ksort($normalized);

    return $normalized;
}

/**
 * 归一化备注，限制最大长度避免异常数据。
 */
function normalize_note($note): string
{
    $text = trim((string) $note);
    if (mb_strlen($text, 'UTF-8') > 1000) {
        $text = mb_substr($text, 0, 1000, 'UTF-8');
    }
    return $text;
}

/**
 * 归一化操作人姓名。
 */
function normalize_operator_name($name): string
{
    $text = trim((string) $name);
    return $text !== '' ? $text : '管理员';
}

/**
 * 从请求体中解析基准版本号，兼容不同字段命名。
 */
function extract_base_version_id(array $input): ?int
{
    $candidates = [
        $input['baseVersionId'] ?? null,
        $input['base_version_id'] ?? null,
        $input['versionId'] ?? null,
        $input['version_id'] ?? null,
    ];

    foreach ($candidates as $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $number = (int) $value;
        if ($number > 0) {
            return $number;
        }
    }

    return null;
}

/**
 * 综合校验排班保存请求，返回清洗后的结果。
 *
 * @throws \InvalidArgumentException 当出现必填项缺失或格式错误。
 */
function normalize_schedule_request(array $input): array
{
    $team = normalize_team_identifier($input['team'] ?? '');

    $viewStart = ensure_ymd((string) ($input['viewStart'] ?? ''), '开始日期');
    $viewEnd = ensure_ymd((string) ($input['viewEnd'] ?? ''), '结束日期');
    if (strcmp($viewStart, $viewEnd) > 0) {
        throw new \InvalidArgumentException('开始日期不能晚于结束日期');
    }

    $employees = normalize_employees_list($input['employees'] ?? []);
    if (!$employees) {
        throw new \InvalidArgumentException('成员列表不能为空');
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

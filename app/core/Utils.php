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
 * 返回存储目录路径（以数据库所在目录为基准）。
 */
function storage_dir(): string
{
    static $dir = null;
    if ($dir !== null) {
        return $dir;
    }

    $dbDir = dirname(DB_FILE);
    if (!is_dir($dbDir)) {
        @mkdir($dbDir, 0770, true);
    }
    $dir = $dbDir;
    return $dir;
}

/**
 * 确保存储目录下的子路径存在。
 */
function ensure_storage_path(string $relative): string
{
    $base = storage_dir();
    $target = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
    $folder = is_dir($target) ? $target : dirname($target);
    if (!is_dir($folder)) {
        @mkdir($folder, 0770, true);
    }
    return $target;
}

/**
 * 追加一条进度日志到 storage/logs/progress.jsonl。
 */
function append_progress_log(array $entry): void
{
    $path = ensure_storage_path('logs/progress.jsonl');
    $payload = ['timestamp' => date('c')] + $entry;
    $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        return;
    }
    file_put_contents($path, $json . PHP_EOL, FILE_APPEND | LOCK_EX);
}

/**
 * 读取最近的进度日志。
 */
function read_progress_logs(int $limit = 100, ?string $team = null): array
{
    $path = ensure_storage_path('logs/progress.jsonl');
    if (!is_file($path)) {
        return [];
    }
    $lines = @file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!is_array($lines) || !$lines) {
        return [];
    }
    $items = [];
    for ($i = count($lines) - 1; $i >= 0 && count($items) < $limit; $i--) {
        $line = $lines[$i];
        $decoded = json_decode($line, true);
        if (!is_array($decoded)) {
            continue;
        }
        if ($team !== null && isset($decoded['team']) && $decoded['team'] !== $team) {
            continue;
        }
        $items[] = $decoded;
    }
    return array_reverse($items);
}


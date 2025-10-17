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


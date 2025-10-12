<?php
declare(strict_types=1);

/**
 * 排班助手 后端（无登录版 / PHP + SQLite）
 * 路径：/api/index.php
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);
date_default_timezone_set('Asia/Shanghai');

// ===== SQLite 连接 & 初始化 =====
const DB_FILE = __DIR__ . '/data/data.sqlite';
@mkdir(__DIR__ . '/data', 0770, true);

function db(): PDO {
  static $pdo = null;
  if ($pdo) return $pdo;
  $pdo = new PDO('sqlite:' . DB_FILE, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
  $pdo->exec('PRAGMA journal_mode = WAL;');
  $pdo->exec('PRAGMA busy_timeout = 5000;');

  // 只有排班版本表（无 users）
  $pdo->exec("
    CREATE TABLE IF NOT EXISTS schedule_versions (
      id INTEGER PRIMARY KEY AUTOINCREMENT,
      team TEXT NOT NULL,
      view_start TEXT NOT NULL, -- YYYY-MM-DD
      view_end TEXT NOT NULL,   -- YYYY-MM-DD
      employees TEXT NOT NULL,  -- JSON array
      data TEXT NOT NULL,       -- JSON object: { 'YYYY-MM-DD': { '张三':'夜', ... } }
      note TEXT,
      created_at TEXT NOT NULL DEFAULT (datetime('now','localtime')),
      created_by_name TEXT      -- 记录操作者名字（无登录时来自前端 operator）
    );
  ");
  $pdo->exec("CREATE INDEX IF NOT EXISTS idx_sv_team_range ON schedule_versions(team, view_start, view_end, id);");
  return $pdo;
}

// ===== 工具函数 =====
function json_input(): array {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $arr = json_decode($raw, true);
  return is_array($arr) ? $arr : [];
}
function send_json($data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function send_error(string $message, int $status = 400, array $extra = []): void {
  send_json(['message' => $message] + $extra, $status);
}
function ymd_range(string $start, string $end): array {
  $out = [];
  $s = strtotime($start);
  $e = strtotime($end);
  if ($s === false || $e === false || $s > $e) return $out;
  for ($t = $s; $t <= $e; $t += 86400) $out[] = date('Y-m-d', $t);
  return $out;
}
function cn_week(string $ymd): string {
  $w = date('w', strtotime($ymd)); // 0..6
  return ['日','一','二','三','四','五','六'][$w] ?? '';
}

// ===== 路由 =====
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/api#', '', $path) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'OPTIONS') { http_response_code(204); exit; }

// ===== 接口实现 =====
switch (true) {

  // 无登录版：占位，保证前端兼容
  case $method === 'GET' && $path === '/me':
    send_json(['user' => ['username' => 'admin', 'display_name' => '管理员']]);

  case $method === 'POST' && $path === '/login':
    send_json(['ok' => true, 'user' => ['username' => 'admin', 'display_name' => '管理员']]);

  case $method === 'POST' && $path === '/logout':
    send_json(['ok' => true]);

  // 读取某团队 + 时间段的最新排班版本
  case $method === 'GET' && $path === '/schedule':
    $team  = (string)($_GET['team']  ?? 'default');
    $start = (string)($_GET['start'] ?? '');
    $end   = (string)($_GET['end']   ?? '');
    if (!$team || !$start || !$end) send_error('参数缺失', 400);

    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT id, employees, data, view_start, view_end
      FROM schedule_versions
      WHERE team=? AND view_start=? AND view_end=?
      ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$team, $start, $end]);
    $row = $stmt->fetch();

    if (!$row) {
      send_json([
        'team'      => $team,
        'viewStart' => $start,
        'viewEnd'   => $end,
        'employees' => [],
        'data'      => (object)[],
        'version_id'=> null
      ]);
    } else {
      send_json([
        'team'      => $team,
        'viewStart' => $row['view_start'],
        'viewEnd'   => $row['view_end'],
        'employees' => json_decode($row['employees'], true) ?: [],
        'data'      => json_decode($row['data'], true) ?: (object)[],
        'version_id'=> (int)$row['id']
      ]);
    }

  // 保存（新版本）——乐观锁：baseVersionId 不等于最新时返回 409
  case $method === 'POST' && $path === '/schedule/save':
    $in   = json_input();
    $team = (string)($in['team'] ?? 'default');
    $vs   = (string)($in['viewStart'] ?? '');
    $ve   = (string)($in['viewEnd'] ?? '');
    $emps = $in['employees'] ?? [];
    $data = $in['data'] ?? new stdClass();
    $base = $in['baseVersionId'] ?? null; // 可能为 null
    $note = (string)($in['note'] ?? '');
    $operator = trim((string)($in['operator'] ?? '管理员'));

    if (!$team || !$vs || !$ve || !is_array($emps) || !is_array($data)) {
      send_error('参数不合法', 400);
    }

    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT id FROM schedule_versions
      WHERE team=? AND view_start=? AND view_end=?
      ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$team, $vs, $ve]);
    $cur = $stmt->fetch();
    $latestId = $cur ? (int)$cur['id'] : null;

    if ($latestId !== null && $base !== null && (int)$base !== $latestId) {
      send_error('保存冲突：已有新版本', 409, ['code'=>409,'latest_version_id'=>$latestId]);
    }

    $stmt = $pdo->prepare("
      INSERT INTO schedule_versions(team, view_start, view_end, employees, data, note, created_by_name)
      VALUES(?,?,?,?,?,?,?)
    ");
    $stmt->execute([
      $team, $vs, $ve,
      json_encode(array_values($emps), JSON_UNESCAPED_UNICODE),
      json_encode($data, JSON_UNESCAPED_UNICODE),
      $note, $operator ?: '管理员'
    ]);
    $newId = (int)$pdo->lastInsertId();
    send_json(['ok'=>true, 'version_id'=>$newId]);

  // 历史版本列表
  case $method === 'GET' && $path === '/schedule/versions':
    $team  = (string)($_GET['team']  ?? 'default');
    $start = (string)($_GET['start'] ?? '');
    $end   = (string)($_GET['end']   ?? '');
    if (!$team || !$start || !$end) send_error('参数缺失', 400);

    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT id, created_at, note, created_by_name
      FROM schedule_versions
      WHERE team=? AND view_start=? AND view_end=?
      ORDER BY id DESC
      LIMIT 200
    ");
    $stmt->execute([$team, $start, $end]);
    $rows = $stmt->fetchAll() ?: [];
    send_json(['versions' => array_map(function($r){
      return [
        'id' => (int)$r['id'],
        'created_at' => $r['created_at'],
        'note' => $r['note'] ?? '',
        'created_by_name' => $r['created_by_name'] ?? '管理员',
      ];
    }, $rows)]);

  // 按版本 ID 读取
  case $method === 'GET' && $path === '/version':
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) send_error('参数缺失', 400);
    $pdo = db();
    $stmt = $pdo->prepare('SELECT employees, data FROM schedule_versions WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) send_error('未找到', 404);
    send_json([
      'employees' => json_decode($row['employees'], true) ?: [],
      'data'      => json_decode($row['data'], true) ?: (object)[],
    ]);

  // 导出：优先 XLSX，失败回退 CSV
  case $method === 'GET' && $path === '/export/xlsx':
    $team  = (string)($_GET['team']  ?? 'default');
    $start = (string)($_GET['start'] ?? '');
    $end   = (string)($_GET['end']   ?? '');
    if (!$team || !$start || !$end) send_error('参数缺失', 400);

    $pdo = db();
    $stmt = $pdo->prepare("
      SELECT employees, data FROM schedule_versions
      WHERE team=? AND view_start=? AND view_end=?
      ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$team, $start, $end]);
    $row = $stmt->fetch();
    $employees = $row ? (json_decode($row['employees'], true) ?: []) : [];
    $data = $row ? (json_decode($row['data'], true) ?: []) : [];

    $dates = ymd_range($start, $end);
    $header = array_merge(['日期','星期'], $employees);

    $hasSpreadsheet = is_file(__DIR__ . '/vendor/autoload.php');
    if ($hasSpreadsheet) {
      require_once __DIR__ . '/vendor/autoload.php';
      try {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $col = 1;
        foreach ($header as $h) $sheet->setCellValueByColumnAndRow($col++, 1, $h);
        $r = 2;
        foreach ($dates as $d) {
          $rowVals = [$d, '周'.cn_week($d)];
          foreach ($employees as $e) $rowVals[] = $data[$d][$e] ?? '';
          $col = 1;
          foreach ($rowVals as $v) $sheet->setCellValueByColumnAndRow($col++, $r, $v);
          $r++;
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="排班_'.$start.'_'.$end.'.xlsx"');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
      } catch (\Throwable $e) { /* 回退 CSV */ }
    }

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="排班_'.$start.'_'.$end.'.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    fputcsv($out, $header);
    foreach ($dates as $d) {
      $rowVals = [$d, '周'.cn_week($d)];
      foreach ($employees as $e) $rowVals[] = $data[$d][$e] ?? '';
      fputcsv($out, $rowVals);
    }
    fclose($out);
    exit;

  default:
    send_error('Not Found', 404);
}

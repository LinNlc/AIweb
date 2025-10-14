<?php
declare(strict_types=1);

/**
 * 排班助手 后端（无登录版 / PHP + SQLite）
 * 路径：/api/index.php
 */

require __DIR__ . '/app/bootstrap.php';
require __DIR__ . '/app/api/schedule.php';
require __DIR__ . '/app/api/versions.php';

// ===== 路由 =====
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$path = preg_replace('#^/api#', '', $path) ?: '/';
$servingIndex = ($method === 'GET') && ($path === '/' || $path === '/index.html');
if ($servingIndex) {
  $indexFile = __DIR__ . '/app/public/index.html';
  if (is_file($indexFile)) {
    header('Content-Type: text/html; charset=utf-8');
    readfile($indexFile);
    exit;
  }
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'index.html 缺失';
  exit;
}
if ($method === 'OPTIONS') { http_response_code(204); exit; }

if (handle_schedule_request($method, $path)) {
  exit;
}

if (handle_versions_request($method, $path)) {
  exit;
}

// ===== 接口实现 =====
switch (true) {

  // 无登录版：占位，保证前端兼容
  case $method === 'GET' && $path === '/me':
    send_json(['user' => ['username' => 'admin', 'display_name' => '管理员']]);

  case $method === 'POST' && $path === '/login':
    send_json(['ok' => true, 'user' => ['username' => 'admin', 'display_name' => '管理员']]);

  case $method === 'POST' && $path === '/logout':
    send_json(['ok' => true]);

  case $method === 'GET' && $path === '/org-config':
    $pdo = db();
    $stmt = $pdo->query('SELECT payload, updated_at FROM org_config WHERE id = 1 LIMIT 1');
    $row = $stmt->fetch();
    $payload = [];
    if ($row && isset($row['payload'])) {
      $decoded = decode_json_assoc($row['payload']);
      if ($decoded) {
        $payload = $decoded;
      }
    }
    send_json([
      'config' => $payload,
      'updated_at' => $row['updated_at'] ?? null,
    ]);

  case $method === 'POST' && $path === '/org-config':
    $in = json_input();
    $config = $in['config'] ?? [];
    if (is_object($config)) {
      $config = json_decode(json_encode($config, JSON_UNESCAPED_UNICODE), true);
    }
    if (!is_array($config)) {
      send_error('配置格式错误', 400);
    }
    $json = json_encode($config, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
      $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    if ($json === false) {
      send_error('配置保存失败', 500);
    }
    $pdo = db();
    $stmt = $pdo->prepare("
      INSERT INTO org_config(id, payload, updated_at)
      VALUES(1, ?, datetime('now','localtime'))
      ON CONFLICT(id) DO UPDATE SET payload=excluded.payload, updated_at=excluded.updated_at
    ");
    $stmt->execute([$json]);
    send_json(['ok' => true]);

  default:
    send_error('Not Found', 404);
}

<?php
declare(strict_types=1);

/**
 * 排班助手 API 入口（无登录版 / PHP + SQLite）
 * 路径：/api/index.php
 */

require __DIR__ . '/../app/bootstrap.php';
require __DIR__ . '/../app/api/schedule.php';
require __DIR__ . '/../app/api/versions.php';
require __DIR__ . '/../app/api/progress.php';
require __DIR__ . '/../app/api/auth.php';
require __DIR__ . '/../app/api/org_config.php';

// ===== 路由 =====
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];
$path = preg_replace('#^/api#', '', $path) ?: '/';

if ($method === 'OPTIONS') {
  http_response_code(204);
  exit;
}

if (handle_schedule_request($method, $path)) {
  exit;
}

if (handle_versions_request($method, $path)) {
  exit;
}

if (handle_progress_request($method, $path)) {
  exit;
}

if (handle_auth_request($method, $path)) {
  exit;
}

if (handle_org_config_request($method, $path)) {
  exit;
}

send_error('Not Found', 404);

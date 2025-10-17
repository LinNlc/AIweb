<?php
declare(strict_types=1);

/**
 * 排班助手 前端入口（无登录版 / PHP + SQLite）
 * 路径：/index.php
 */

$indexFile = __DIR__ . '/app/public/index.html';

if (!is_file($indexFile)) {
  http_response_code(500);
  header('Content-Type: text/plain; charset=utf-8');
  echo 'index.html 缺失';
  exit;
}

header('Content-Type: text/html; charset=utf-8');
readfile($indexFile);

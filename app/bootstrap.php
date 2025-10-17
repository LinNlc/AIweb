<?php
declare(strict_types=1);

$config = require __DIR__ . '/config/app.php';

// ===== 应用环境初始化 =====
if (!empty($config['timezone'])) {
    date_default_timezone_set((string) $config['timezone']);
}

if (isset($config['display_errors'])) {
    ini_set('display_errors', $config['display_errors'] ? '1' : '0');
}
error_reporting(E_ALL);

// ===== 数据库路径解析 =====
$database = $config['database'] ?? [];
$legacyPath = (string)($database['legacy_path'] ?? '');
$storageDir = (string)($database['storage_dir'] ?? (__DIR__ . '/storage'));
$filename = (string)($database['filename'] ?? 'app.db');

if (!is_dir($storageDir)) {
    @mkdir($storageDir, 0770, true);
}

$defaultDbFile = rtrim($storageDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;
$dbFile = $defaultDbFile;

if ($legacyPath && is_file($legacyPath)) {
    $dbFile = $legacyPath;
} elseif ($legacyPath && is_dir(dirname($legacyPath)) && is_writable(dirname($legacyPath))) {
    $dbFile = $legacyPath;
}

if (!defined('DB_FILE')) {
    define('DB_FILE', $dbFile);
}

require_once __DIR__ . '/core/Utils.php';
require_once __DIR__ . '/core/Http.php';

return $config;

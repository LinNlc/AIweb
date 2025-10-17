<?php
declare(strict_types=1);

/**
 * 存储目录辅助函数：用于定位 SQLite 同级的 logs/、exports/ 等文件。
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
 * 确保存储目录中指定的相对路径存在，返回绝对路径。
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

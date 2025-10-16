<?php
declare(strict_types=1);

return [
    // 默认时区
    'timezone' => 'Asia/Shanghai',

    // 是否展示错误（生产环境建议关闭）
    'display_errors' => false,

    // 数据库文件配置
    'database' => [
        // 历史环境遗留的 SQLite 文件位置
        'legacy_path' => '/opt/1panel/apps/openresty/openresty/www/sites/xn--wyuz77ayygl2b/index/api/data/data.sqlite',
        // 新版应用默认存储目录
        'storage_dir' => __DIR__ . '/../storage',
        // 新版默认数据库文件名称
        'filename' => 'app.db',
    ],
];

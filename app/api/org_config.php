<?php
declare(strict_types=1);

/**
 * 处理组织配置读取与保存相关的 API 路由。
 *
 * @param string $method HTTP 请求方法
 * @param string $path   归一化后的请求路径（去除 /api 前缀）
 *
 * @return bool 已完成响应时返回 true。
 */
function handle_org_config_request(string $method, string $path): bool
{
    switch (true) {
        case $method === 'GET' && $path === '/org-config':
            org_config_fetch();
            return true;

        case $method === 'POST' && $path === '/org-config':
            org_config_save();
            return true;

        default:
            return false;
    }
}

/**
 * GET /org-config
 * 读取系统组织配置，包含最后更新时间。
 */
function org_config_fetch(): void
{
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
}

/**
 * POST /org-config
 * 保存组织配置，使用 JSON 存储并更新时间戳。
 */
function org_config_save(): void
{
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
    $stmt = $pdo->prepare(
        "INSERT INTO org_config(id, payload, updated_at)\n         VALUES(1, ?, datetime('now','localtime'))\n         ON CONFLICT(id) DO UPDATE SET payload=excluded.payload, updated_at=excluded.updated_at"
    );
    $stmt->execute([$json]);

    send_json(['ok' => true]);
}

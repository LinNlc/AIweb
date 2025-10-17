<?php
declare(strict_types=1);

require_once __DIR__ . '/../core/OrgConfig.php';

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
    $result = load_org_config();
    send_json($result);
}

/**
 * POST /org-config
 * 保存组织配置，使用 JSON 存储并更新时间戳。
 */
function org_config_save(): void
{
    $in = json_input();

    try {
        $config = normalize_org_config_payload($in['config'] ?? []);
    } catch (InvalidArgumentException $e) {
        send_error($e->getMessage(), 400);
        return;
    }

    try {
        save_org_config($config);
    } catch (RuntimeException $e) {
        send_error($e->getMessage(), 500);
        return;
    }

    send_json(['ok' => true]);
}

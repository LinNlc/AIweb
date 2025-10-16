<?php
declare(strict_types=1);

/**
 * 处理与登录态占位相关的 API 路由（无登录版只返回固定数据）。
 *
 * @param string $method HTTP 请求方法
 * @param string $path   归一化后的请求路径（去除 /api 前缀）
 *
 * @return bool 已拦截请求时返回 true，便于上层提前退出。
 */
function handle_auth_request(string $method, string $path): bool
{
    switch (true) {
        case $method === 'GET' && $path === '/me':
            auth_me();
            return true;

        case $method === 'POST' && $path === '/login':
            auth_login();
            return true;

        case $method === 'POST' && $path === '/logout':
            auth_logout();
            return true;

        default:
            return false;
    }
}

/**
 * GET /me
 * 返回前端期望的当前用户信息，占位用。
 */
function auth_me(): void
{
    send_json(['user' => ['username' => 'admin', 'display_name' => '管理员']]);
}

/**
 * POST /login
 * 无登录版固定返回成功状态，保证前端流程畅通。
 */
function auth_login(): void
{
    send_json(['ok' => true, 'user' => ['username' => 'admin', 'display_name' => '管理员']]);
}

/**
 * POST /logout
 * 返回固定成功响应。
 */
function auth_logout(): void
{
    send_json(['ok' => true]);
}

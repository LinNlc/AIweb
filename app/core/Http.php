<?php
declare(strict_types=1);

/**
 * 解析 JSON 请求体，并在解析失败时返回空数组。
 *
 * @return array<string,mixed>
 */
function json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * 将数据以 JSON 格式输出，并终止后续脚本执行。
 *
 * @param mixed $data   任意可序列化为 JSON 的数据
 * @param int   $status HTTP 状态码，默认 200
 */
function send_json($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * 输出带 message 字段的 JSON 错误响应。
 *
 * @param string               $message 人类可读的错误描述
 * @param int                  $status  HTTP 状态码
 * @param array<string,mixed>  $extra   附加字段，例如 code、details
 */
function send_error(string $message, int $status = 400, array $extra = []): void
{
    send_json(['message' => $message] + $extra, $status);
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/Utils.php';

/**
 * 组织配置存储与读取辅助模块。
 *
 * 将原本分散在 API 层的校验、JSON 编解码与数据库写入集中在此，
 * 方便后续在其他入口（如命令行或批量工具）中直接复用。
 */

/**
 * 将任意输入规范化为配置数组。
 *
 * @param mixed $value 任意来源的配置数据
 *
 * @throws InvalidArgumentException 当值无法转换为数组时抛出
 */
function normalize_org_config_payload(mixed $value): array
{
    if ($value === null || $value === '') {
        return [];
    }

    if (is_array($value)) {
        return $value;
    }

    if (is_object($value)) {
        $converted = json_decode(json_encode($value, JSON_UNESCAPED_UNICODE), true);
        if (is_array($converted)) {
            return $converted;
        }
    }

    throw new InvalidArgumentException('配置必须是对象或数组');
}

/**
 * 读取当前组织配置及最后更新时间。
 *
 * @return array{config: array, updated_at: ?string}
 */
function load_org_config(): array
{
    $pdo = db();
    $stmt = $pdo->query('SELECT payload, updated_at FROM org_config WHERE id = 1 LIMIT 1');
    $row = $stmt->fetch();

    $config = [];
    if ($row && isset($row['payload'])) {
        $decoded = decode_json_assoc($row['payload']);
        if ($decoded) {
            $config = $decoded;
        }
    }

    return [
        'config' => $config,
        'updated_at' => $row['updated_at'] ?? null,
    ];
}

/**
 * 将组织配置写入数据库，自动维护更新时间。
 *
 * @throws RuntimeException JSON 编码失败或数据库写入异常时抛出
 */
function save_org_config(array $config): void
{
    $json = json_encode($config, JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        $json = json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
    }
    if ($json === false) {
        throw new RuntimeException('配置保存失败');
    }

    $pdo = db();
    $stmt = $pdo->prepare(
        "INSERT INTO org_config(id, payload, updated_at)\n         VALUES(1, ?, datetime('now','localtime'))\n         ON CONFLICT(id) DO UPDATE SET payload=excluded.payload, updated_at=excluded.updated_at"
    );
    $stmt->execute([$json]);
}

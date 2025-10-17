<?php
declare(strict_types=1);

require_once __DIR__ . '/Utils.php';

/**
 * 排班相关的数据库访问仓储。
 *
 * 这里集中封装 schedule_versions 表的 CRUD 操作，
 * 便于 API 层按业务组合调用，同时保证 SQL 语句集中管理。
 */

/**
 * 精确匹配某个视图范围的排班版本。
 */
function repo_schedule_find_by_range(PDO $pdo, string $team, string $start, string $end): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload'
        . ' FROM schedule_versions'
        . ' WHERE team = ? AND view_start = ? AND view_end = ?'
        . ' ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$team, $start, $end]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * 根据团队与时间范围查找最新的排班版本。
 *
 * @param \PDO      $pdo   数据库连接
 * @param string    $team  团队标识（已归一化）
 * @param ?string   $start 视图开始日期（YYYY-MM-DD）
 * @param ?string   $end   视图结束日期（YYYY-MM-DD）
 *
 * @return array|null 查到时返回排班行，未找到返回 null。
 */
function repo_schedule_find_latest(PDO $pdo, string $team, ?string $start, ?string $end): ?array
{
    if ($start && $end) {
        $row = repo_schedule_find_by_range($pdo, $team, $start, $end);
        if ($row) {
            return $row;
        }
    }

    $stmt = $pdo->prepare(
        'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload'
        . ' FROM schedule_versions'
        . ' WHERE team = ?'
        . ' ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$team]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * 获取指定团队指定视图范围的最新版本 ID（用于乐观锁）。
 */
function repo_schedule_latest_id(PDO $pdo, string $team, string $viewStart, string $viewEnd): ?int
{
    $stmt = $pdo->prepare(
        'SELECT id FROM schedule_versions'
        . ' WHERE team = ? AND view_start = ? AND view_end = ?'
        . ' ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$team, $viewStart, $viewEnd]);
    $row = $stmt->fetch();

    return $row ? (int) $row['id'] : null;
}

/**
 * 插入新的排班版本记录。
 *
 * @param array $payload 需要写入表字段的数据（键名需和表字段一致）。
 *
 * @return int 新增记录的自增 ID。
 */
function repo_schedule_insert_version(PDO $pdo, array $payload): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO schedule_versions'
        . ' (team, employees, data, view_start, view_end, note, created_at, created_by_name, payload)'
        . ' VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $payload['team'],
        $payload['employees'],
        $payload['data'],
        $payload['view_start'],
        $payload['view_end'],
        $payload['note'],
        $payload['created_at'],
        $payload['created_by_name'],
        $payload['payload'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * 按团队与时间范围列出历史排班版本。
 *
 * @return array<int, array<string, mixed>>
 */
function repo_schedule_list_versions(PDO $pdo, string $team, ?string $start, ?string $end): array
{
    $hasRange = $start !== null && $start !== '' && $end !== null && $end !== ''
        && strtotime($start) !== false && strtotime($end) !== false;

    if ($hasRange && $start > $end) {
        [$start, $end] = [$end, $start];
    }

    if ($hasRange) {
        $stmt = $pdo->prepare(
            'SELECT id, view_start, view_end, created_at, note, created_by_name'
            . ' FROM schedule_versions'
            . ' WHERE team = ? AND view_start >= ? AND view_end <= ?'
            . ' ORDER BY created_at DESC, id DESC'
            . ' LIMIT 200'
        );
        $stmt->execute([$team, $start, $end]);
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, view_start, view_end, created_at, note, created_by_name'
            . ' FROM schedule_versions'
            . ' WHERE team = ?'
            . ' ORDER BY created_at DESC, id DESC'
            . ' LIMIT 200'
        );
        $stmt->execute([$team]);
    }

    return $stmt->fetchAll() ?: [];
}

/**
 * 根据主键读取完整排班版本。
 */
function repo_schedule_fetch_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, team, employees, data, view_start, view_end, note, created_at, created_by_name, payload'
        . ' FROM schedule_versions WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();

    return $row ?: null;
}

/**
 * 删除排班版本，返回是否删除成功。
 */
function repo_schedule_delete(PDO $pdo, int $id, string $team): bool
{
    $stmt = $pdo->prepare('DELETE FROM schedule_versions WHERE id = ? AND team = ?');
    $stmt->execute([$id, $team]);

    return $stmt->rowCount() > 0;
}

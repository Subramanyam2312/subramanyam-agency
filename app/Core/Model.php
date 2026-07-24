<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

/**
 * Base repository. Rows come back as plain associative arrays rather than entity
 * objects — the CMS is CRUD over flat records, and an ORM layer would buy nothing
 * here but indirection.
 *
 * Anything more involved than "select these rows with these conditions" gets a
 * hand-written query in the concrete model, which is easier to read and index than
 * a query builder that has to be reverse-engineered.
 */
abstract class Model
{
    protected static string $table = '';

    protected static string $primaryKey = 'id';

    /** Adds `deleted_at IS NULL` to every read and turns delete() into an update. */
    protected static bool $softDeletes = false;

    protected static bool $timestamps = true;

    /** Columns stored as JSON and decoded to arrays on read. */
    protected static array $jsonColumns = [];

    public static function table(): string
    {
        return static::$table;
    }

    public static function find(int|string $id): ?array
    {
        return static::first([static::$primaryKey => $id]);
    }

    public static function findBy(string $column, mixed $value): ?array
    {
        return static::first([$column => $value]);
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array<string,mixed>|null
     */
    public static function first(array $conditions, ?string $orderBy = null): ?array
    {
        $rows = static::all($conditions, $orderBy, 1);

        return $rows[0] ?? null;
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array<int,array<string,mixed>>
     */
    public static function all(array $conditions = [], ?string $orderBy = null, ?int $limit = null, int $offset = 0): array
    {
        $params = [];
        $where  = Database::buildWhere(static::scope($conditions), $params);

        $sql = sprintf('SELECT * FROM `%s` %s', Database::identifier(static::$table), $where);

        if ($orderBy !== null) {
            $sql .= ' ORDER BY ' . static::safeOrderBy($orderBy);
        }

        if ($limit !== null) {
            // Cast, not bound: MySQL will not accept a placeholder for LIMIT under
            // native prepared statements. Integer casting makes it injection-proof.
            $sql .= sprintf(' LIMIT %d OFFSET %d', max(0, $limit), max(0, $offset));
        }

        return array_map([static::class, 'hydrate'], Database::select($sql, $params));
    }

    /**
     * @param array<string,mixed> $conditions
     * @return array{data:array<int,array<string,mixed>>,total:int,per_page:int,current_page:int,last_page:int}
     */
    public static function paginate(
        array $conditions = [],
        int $page = 1,
        int $perPage = 20,
        ?string $orderBy = null
    ): array {
        $perPage = max(1, min(200, $perPage));
        $total   = static::count($conditions);
        $last    = max(1, (int) ceil($total / $perPage));
        $page    = max(1, min($page, $last));

        return [
            'data'         => static::all($conditions, $orderBy, $perPage, ($page - 1) * $perPage),
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $last,
        ];
    }

    /**
     * @param array<string,mixed> $conditions
     */
    public static function count(array $conditions = []): int
    {
        $params = [];
        $where  = Database::buildWhere(static::scope($conditions), $params);

        return (int) Database::scalar(
            sprintf('SELECT COUNT(*) FROM `%s` %s', Database::identifier(static::$table), $where),
            $params
        );
    }

    /**
     * @param array<string,mixed> $conditions
     */
    public static function exists(array $conditions): bool
    {
        return static::count($conditions) > 0;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function create(array $data): int
    {
        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] ??= $now;
            $data['updated_at'] ??= $now;
        }

        return Database::insert(static::$table, $data);
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function updateById(int|string $id, array $data): bool
    {
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return Database::update(static::$table, $data, [static::$primaryKey => $id]) >= 0;
    }

    /**
     * @param array<string,mixed> $conditions
     * @param array<string,mixed> $data
     */
    public static function updateWhere(array $conditions, array $data): int
    {
        if (static::$timestamps) {
            $data['updated_at'] = date('Y-m-d H:i:s');
        }

        return Database::update(static::$table, $data, $conditions);
    }

    /**
     * Soft-deletes when the model supports it, otherwise removes the row.
     */
    public static function deleteById(int|string $id): bool
    {
        if (static::$softDeletes) {
            return Database::update(
                static::$table,
                ['deleted_at' => date('Y-m-d H:i:s')],
                [static::$primaryKey => $id]
            ) > 0;
        }

        return Database::delete(static::$table, [static::$primaryKey => $id]) > 0;
    }

    public static function restore(int|string $id): bool
    {
        if (!static::$softDeletes) {
            return false;
        }

        return Database::update(static::$table, ['deleted_at' => null], [static::$primaryKey => $id]) > 0;
    }

    /**
     * Permanently removes a soft-deleted row. Only reachable from the trash screen.
     */
    public static function forceDelete(int|string $id): bool
    {
        return Database::delete(static::$table, [static::$primaryKey => $id]) > 0;
    }

    /**
     * Adds the soft-delete scope unless the caller opted out with '@with_trashed'
     * or is querying deleted_at explicitly.
     *
     * @param array<string,mixed> $conditions
     * @return array<string,mixed>
     */
    protected static function scope(array $conditions): array
    {
        $withTrashed = (bool) ($conditions['@with_trashed'] ?? false);
        unset($conditions['@with_trashed']);

        if (!static::$softDeletes || $withTrashed) {
            return $conditions;
        }

        foreach (array_keys($conditions) as $key) {
            if (str_starts_with((string) $key, 'deleted_at')) {
                return $conditions;
            }
        }

        $conditions['deleted_at'] = null;

        return $conditions;
    }

    /**
     * Validates "column ASC, other_column DESC" one clause at a time. Order-by comes
     * from query strings on admin list screens, so it cannot be trusted verbatim.
     */
    protected static function safeOrderBy(string $orderBy): string
    {
        $clauses = [];

        foreach (explode(',', $orderBy) as $clause) {
            $parts     = preg_split('/\s+/', trim($clause));
            $column    = Database::identifier((string) $parts[0]);
            $direction = strtoupper($parts[1] ?? 'ASC');

            if (!in_array($direction, ['ASC', 'DESC'], true)) {
                throw new InvalidArgumentException("Illegal sort direction: {$direction}");
            }

            $clauses[] = sprintf('`%s` %s', $column, $direction);
        }

        if ($clauses === []) {
            throw new InvalidArgumentException('Empty ORDER BY clause.');
        }

        return implode(', ', $clauses);
    }

    /**
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    protected static function hydrate(array $row): array
    {
        foreach (static::$jsonColumns as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = json_column($row[$column]);
            }
        }

        return $row;
    }
}

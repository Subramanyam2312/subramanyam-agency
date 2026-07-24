<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use RuntimeException;
use Throwable;

/**
 * Thin PDO layer. Every value reaches MySQL through a bound parameter — there is no
 * code path in this class that concatenates a caller-supplied value into SQL.
 *
 * Identifiers (table and column names) cannot be bound as parameters, so they are
 * validated against a strict pattern and back-quoted instead.
 */
final class Database
{
    private static ?PDO $pdo = null;

    /** Operators permitted in a condition key such as 'published_at <='. */
    private const OPERATORS = [
        '=', '!=', '<>', '<', '<=', '>', '>=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN', 'IS', 'IS NOT',
    ];

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = Config::get('database');

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );

        try {
            self::$pdo = new PDO($dsn, $config['user'], $config['password'], [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Real prepared statements, not client-side interpolation.
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ]);
        } catch (Throwable $e) {
            // Never leak credentials from the DSN into an error page or log line.
            throw new RuntimeException('Database connection failed. Check .env credentials.', 0, $e);
        }

        return self::$pdo;
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function query(string $sql, array $params = []): PDOStatement
    {
        $statement = self::connection()->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * @param array<string,mixed> $params
     * @return array<int,array<string,mixed>>
     */
    public static function select(string $sql, array $params = []): array
    {
        return self::query($sql, $params)->fetchAll();
    }

    /**
     * @param array<string,mixed> $params
     * @return array<string,mixed>|null
     */
    public static function selectOne(string $sql, array $params = []): ?array
    {
        $row = self::query($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /**
     * First column of the first row — for COUNT(*), MAX(), EXISTS() and friends.
     *
     * @param array<string,mixed> $params
     */
    public static function scalar(string $sql, array $params = []): mixed
    {
        $value = self::query($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /**
     * @param array<string,mixed> $data
     */
    public static function insert(string $table, array $data): int
    {
        $table   = self::identifier($table);
        $columns = [];
        $holders = [];
        $params  = [];

        foreach ($data as $column => $value) {
            $columns[] = self::identifier($column);
            $holders[] = ':' . $column;
            $params[':' . $column] = self::normalise($value);
        }

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            '`' . implode('`, `', $columns) . '`',
            implode(', ', $holders)
        );

        self::query($sql, $params);

        return (int) self::connection()->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $conditions
     */
    public static function update(string $table, array $data, array $conditions): int
    {
        if ($data === []) {
            return 0;
        }

        $table       = self::identifier($table);
        $assignments = [];
        $params      = [];

        foreach ($data as $column => $value) {
            $assignments[] = sprintf('`%s` = :set_%s', self::identifier($column), $column);
            $params[':set_' . $column] = self::normalise($value);
        }

        $where = self::buildWhere($conditions, $params);

        $sql = sprintf(
            'UPDATE `%s` SET %s %s',
            $table,
            implode(', ', $assignments),
            $where
        );

        return self::query($sql, $params)->rowCount();
    }

    /**
     * @param array<string,mixed> $conditions
     */
    public static function delete(string $table, array $conditions): int
    {
        $params = [];
        $where  = self::buildWhere($conditions, $params);

        if (trim($where) === '') {
            throw new InvalidArgumentException('Refusing to run an unconditional DELETE.');
        }

        return self::query(
            sprintf('DELETE FROM `%s` %s', self::identifier($table), $where),
            $params
        )->rowCount();
    }

    /**
     * Runs the callback inside a transaction, rolling back on any exception.
     */
    public static function transaction(callable $callback): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();

        try {
            $result = $callback($pdo);
            $pdo->commit();

            return $result;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Turns ['status' => 'published', 'published_at <=' => $now, 'id IN' => [1,2]]
     * into "WHERE `status` = :w_status AND `published_at` <= :w_published_at AND `id` IN (:w_id_0, :w_id_1)".
     *
     * @param array<string,mixed>  $conditions
     * @param array<string,mixed>  $params      Bound parameters, appended to by reference.
     */
    public static function buildWhere(array $conditions, array &$params): string
    {
        if ($conditions === []) {
            return '';
        }

        $clauses = [];

        foreach ($conditions as $key => $value) {
            // Raw fragments: ['@raw' => 'JSON_LENGTH(metrics) > 0']. No values interpolated.
            if ($key === '@raw') {
                foreach ((array) $value as $fragment) {
                    $clauses[] = '(' . $fragment . ')';
                }

                continue;
            }

            $parts    = preg_split('/\s+/', trim($key), 2);
            $column   = self::identifier($parts[0]);
            $operator = strtoupper(trim($parts[1] ?? '='));

            if (!in_array($operator, self::OPERATORS, true)) {
                throw new InvalidArgumentException("Unsupported SQL operator: {$operator}");
            }

            // NULL never compares with =; silently upgrade to IS / IS NOT.
            if ($value === null && in_array($operator, ['=', 'IS'], true)) {
                $clauses[] = sprintf('`%s` IS NULL', $column);
                continue;
            }

            if ($value === null && in_array($operator, ['!=', '<>', 'IS NOT'], true)) {
                $clauses[] = sprintf('`%s` IS NOT NULL', $column);
                continue;
            }

            if (in_array($operator, ['IN', 'NOT IN'], true)) {
                $values = (array) $value;

                if ($values === []) {
                    // IN () is a syntax error; an empty set matches nothing.
                    $clauses[] = $operator === 'IN' ? '1 = 0' : '1 = 1';
                    continue;
                }

                $holders = [];

                foreach (array_values($values) as $index => $item) {
                    $holder   = sprintf(':w_%s_%d', $column, $index);
                    $holders[] = $holder;
                    $params[$holder] = self::normalise($item);
                }

                $clauses[] = sprintf('`%s` %s (%s)', $column, $operator, implode(', ', $holders));
                continue;
            }

            $holder = ':w_' . $column;

            // Two conditions on the same column (a BETWEEN-style range) must not collide.
            $suffix = 0;
            while (array_key_exists($holder, $params)) {
                $holder = ':w_' . $column . '_' . (++$suffix);
            }

            $clauses[]       = sprintf('`%s` %s %s', $column, $operator, $holder);
            $params[$holder] = self::normalise($value);
        }

        return 'WHERE ' . implode(' AND ', $clauses);
    }

    /**
     * Validates a table or column name. Anything outside [A-Za-z0-9_] is rejected
     * outright rather than escaped, because there is no legitimate use for it here.
     */
    public static function identifier(string $name): string
    {
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
            throw new InvalidArgumentException("Illegal SQL identifier: {$name}");
        }

        return $name;
    }

    /**
     * PDO cannot bind booleans or arrays to MySQL in a useful way.
     */
    private static function normalise(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $value;
    }
}

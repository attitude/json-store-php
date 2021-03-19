<?php

namespace JSON\Store;

use UniversalTime\Identificator\UTID;

final class Store {
    const COMPARISON_OPERATORS = ['=', '!=', '>', '>=', '<', '<='];
    const ASSOC = \PDO::FETCH_ASSOC; // 2
    const COLUMN = \PDO::FETCH_COLUMN; // 7
    const JSON = 9999;

    private static $found = [];

    // TODO: Move to some Object/Array helper
    private static function extractKey(array|object $value, string $key): int|string {
        $value = (array) $value;

        if (isset($value[$key])) {
            return $value[$key];
        }

        throw new \Exception('Undefined key `{$key}`');
    }

    public static function prepareColumnInValues(string $column, array $values): string {
        $values = implode(',', array_map(fn() => '?', $values));

        return "`{$column}` IN ({$values})";
    }

    /**
     * Removes any null values from the document
     *
     * @param array $value
     * @return void
     */
    public static function filter(array $value): array {
        foreach ($value as $key => $keyValue) {
            if (is_array($keyValue)) {
                $value[$key] = static::filter($keyValue);
            }
        }

        $value = array_filter($value, fn($v) => isset($v));

        return $value;
    }

    /**
     * Replaces constants in a query string
     *
     * List of constants:
     *
     * - primaryKey
     * - connection
     * - table
     * - docKey
     *
     * All constants need to be enclosed in fouble curly braces.
     *
     * @param string $space
     * @param string $query
     * @return string
     */
    private static function constants(string $space, string $query): string {
        return strtr($query, [
            '{{primaryKey}}' => Space::primaryKey($space),
            '{{connection}}' => Space::connection($space),
            '{{table}}' => Space::table($space),
            '{{docKey}}' => Space::docKey($space),
        ]);
    }

    /**
     * Stores a document
     *
     * @param string $space
     * @param array $doc
     * @return int|string Document intentificator
     */
    public static function set(
        string $space,
        array $doc,
    ): int|string {
        $primaryKey = Space::primaryKey($space);
        $connection = Space::connection($space);

        $doc = static::filter($doc);

        try {
            $id = self::extractKey($doc, $primaryKey);
        } catch(\Exception $e) {
            $id = UTID::get();
            $doc[$primaryKey] = $id;
        }

        $json = json_encode($doc);
        $query = static::constants($space, "INSERT INTO `{{table}}` (`{{docKey}}`) VALUES (?) ON DUPLICATE KEY UPDATE `{{docKey}}` = ?;");

        $connection->beginTransaction();

        try {
            $statement = $connection->prepare($query);
            $statement->execute([$json, $json]);
            $connection->commit();

            return $id;
        } catch (\Throwable $e) {
            $connection->rollBack();
            throw new \Exception("Failed to insert `{$id}`", 500, $e);
        }
    }

    /**
     * Checks whether document exists
     *
     * @param string $space
     * @param int|string $id
     * @return bool
     */
    public static function exists(
        string $space,
        int|string $id,
    ): bool {
        $connection = Space::connection($space);

        $query = static::constants($space, "SELECT 1 from `{{table}}` WHERE `{{primaryKey}}` = ? LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute([$id]);

        return !!$statement->fetchColumn();
    }

    /**
     * Checks whether document exists
     *
     * @param string $space
     * @param string $key
     * @param int|string $value
     * @return bool
     */
    public static function existsBy(
        string $space,
        string $key,
        int|string $value,
    ): bool {
        $connection = Space::connection($space);

        $query = static::constants($space, "SELECT 1 from `{{table}}` WHERE `{$key}` = ? LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute([$value]);

        return !!$statement->fetchColumn();
    }

    /**
     * Checks whether document exists
     *
     * @param string $space
     * @param string $where
     * @param array $values
     * @return bool
     */
    public static function existsWhere(string $space, string $where, array $values): bool {
        $connection = Space::connection($space);

        $query = static::constants($space, "SELECT 1 from `{{table}}` WHERE {$where} LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute($values);

        return !!$statement->fetchColumn();
    }

    /**
     * Retrieves an item
     *
     * @param string $space
     * @param int|string $id
     * @return array
     */
    public static function get(
        string $space,
        int|string $id,
    ): array {
        $connection = Space::connection($space);

        $query = static::constants($space, "SELECT `{{docKey}}` from `{{table}}` WHERE `{{primaryKey}}` = ? LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute([$id]);

        $result = json_decode($statement->fetchColumn(), true);

        if ($result) {
            return $result;
        }

        throw new Exceptions\NotFound("Not found {$id}");
    }

    /**
     * Retrieves an item
     *
     * @param string $space
     * @param string $key
     * @param int|string $value
     * @return array
     */
    public static function getBy(
        string $space,
        string $key,
        int|string $value,
        string $operator = '='
    ): array {
        if (!in_array($operator, static::COMPARISON_OPERATORS)) {
            throw new \Exception("Unsupported operator: `{$operator}`; use one of ".json_encode(static::COMPARISON_OPERATORS), 400);
        }

        $connection = Space::connection($space);

        $query = static::constants($space, "SELECT `{{docKey}}` from `{{table}}` WHERE `{$key}` {$operator} ? LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute([$value]);

        $result = json_decode($statement->fetchColumn(), true);

        if ($result) {
            return $result;
        }

        throw new Exceptions\NotFound("Not found `{$key}` {$operator} ".json_encode($value));
    }

    protected static function assertWhereValues(string $where = null, array $values = []): void {
        if (count($values) !== substr_count($where, '?')) {
            throw new \Exception("Placeholders count in the WHERE clause does not match the count of VALUES", 400);
        }
    }

    protected static function queryFilter(
        string $where = null,
        int|float|string $before = null,
        int|float|string $after = null,
        string $orderby = null,
    ): string {
        $query = [];

        if (isset($orderby)) {
            $orderby = Assert::orderBy($orderby);
        }

        $query[] = isset($where) ? "WHERE {$where}" : "WHERE 1 = 1";
        $query[] = isset($before) ? "AND `{{primaryKey}}` < ?" : null;
        $query[] = isset($after) ? "AND `{{primaryKey}}` > ?" : null;
        $query[] = isset($orderby) ? "ORDER BY {$orderby}" : null;

        return implode(' ', array_filter($query));
    }

    /**
     * Retrieves an item using WHERE clause
     *
     * @param string $space
     * @param string $where
     * @param array $values
     * @return array
     */
    public static function getWhere(
        string $space,
        string $where,
        array $values = [],
        int|float|string $before = null,
        int|float|string $after = null,
        string $orderby = null,
    ): array {
        if (count($values) !== substr_count($where, '?')) {
            throw new \Exception("Placeholders count in the WHERE clause does not match the count of VALUES", 400);
        }

        $connection = Space::connection($space);

        $query[] = "SELECT `{{docKey}}` from `{{table}}` WHERE {$where}";
        $query[] = isset($before) ? "AND `{{primaryKey}}` < ?" : null;
        $query[] = isset($after) ? "AND `{{primaryKey}}` > ?" : null;
        $query[] = isset($orderby) ? "ORDER BY {$orderby}" : null;
        $query[] = "LIMIT 1;";

        $query = implode(' ', array_filter($query));
        $query = static::constants($space, $query);

        if (isset($before)) { $values[] = $before; }
        if (isset($after)) { $values[] = $after; }

        $statement = $connection->prepare($query);
        $statement->execute($values);

        $result = json_decode($statement->fetchColumn(), true);

        if ($result) {
            return $result;
        }

        throw new Exceptions\NotFound('Not found');
    }

    public static function count(
        string $space,
        string $where = null,
        array $values = [],
        int|float|string $before = null,
        int|float|string $after = null,
        string $join = null,
    ): int {
        static::assertWhereValues($where, $values);

        $values = array_values($values);
        $connection = Space::connection($space);

        $query = "SELECT COUNT(*) from `{{table}}` ".($join ? " {$join} " : '').static::queryFilter(
            where: $where,
            before: $before,
            after: $after,
        );
        $query = static::constants($space, $query).';';

        if (isset($before)) { $values[] = $before; }
        if (isset($after)) { $values[] = $after; }

        $statement = $connection->prepare($query);
        $statement->execute($values);

        return $statement->fetchColumn();
    }

    public static function fetch(
        string $space,
        string $where = null,
        array $values = [],
        int|float|string $before = null,
        int|float|string $after = null,
        string $orderby = null,
        int $limit = 10,
        string $join = null,
    ) {
        $count = static::count(
            space: $space,
            where: $where,
            values: $values,
            before: $before,
            after: $after,
            join: $join,
        );

        if ($count > 0) {
            static::assertWhereValues($where, $values);

            $values = array_values($values);
            $connection = Space::connection($space);

            unset(static::$found[$space]);

            $query = "SELECT `{{table}}`.`{{docKey}}` from `{{table}}` ".($join ? " {$join} " : '').static::queryFilter(
                where: $where,
                before: $before,
                after: $after,
                orderby: $orderby,
            );
            $query.= isset($limit) && $limit > 0 ? " LIMIT {$limit}" : '';

            $query = static::constants($space, $query).';';

            if (isset($before)) { $values[] = $before; }
            if (isset($after)) { $values[] = $after; }

            $statement = $connection->prepare($query);
            $statement->execute($values);

            $rows = $statement->fetchAll(\PDO::FETCH_COLUMN);
            $statement->closeCursor();

            if ($rows) {
                static::$found[$space] = $count;

                return array_map(fn(string $row) => json_decode($row, true), $rows);
            }
        }

        throw new Exceptions\NotFound();
    }

    public static function select(string $space, string $query, array $values = [], int $mode = 2): array {
        if (!strstr($query, '{{table}}')) {
            throw new \Exception("Table placeholder `{{table}}` must be used ", 400);
        }

        $connection = Space::connection($space);
        $query = static::constants($space, $query).';';
        unset(static::$found[$space]);

        $statement = $connection->prepare($query);
        $statement->execute($values);

        $rows = $statement->fetchAll($mode === static::JSON ? \PDO::FETCH_COLUMN : $mode);
        $statement->closeCursor();

        if ($rows) {
            return $mode === static::JSON
                ? array_map(fn(string $row) => json_decode($row, true), $rows)
                : $rows;
        }

        throw new Exceptions\NotFound();
    }

    public static function found(string $space): int {
        if (!isset(static::$found[$space])) {
            throw new \Exception("Run fetch before getting count in `$space` space", 400);
        }

        return static::$found[$space];
    }

    /**
     * Deletes an item
     *
     * @param string $space
     * @param int|string $id
     * @return void
     */
    public static function delete(
        string $space,
        int|string $id,
    ): void {
        $connection = Space::connection($space);

        $query = static::constants($space, "DELETE FROM `{{table}}` WHERE `{{primaryKey}}` = ?");
        $statement = $connection->prepare($query);
        $statement->execute([$id]);

        if ($statement->rowCount() === 0) {
            throw new \Exception('Failed to delete', 400);
        }
    }

    /**
     * Deletes item(s)
     *
     * @param string $space
     * @param string $key
     * @param int|string $value
     * @return void
     * */
    public static function deleteBy(
        string $space,
        string $key,
        int|string $value,
        string $operator = '=',
    ): void {
        $connection = Space::connection($space);

        $query = static::constants($space, "DELETE FROM `{{table}}` WHERE `{$key}` {$operator} ? LIMIT 1;");
        $statement = $connection->prepare($query);
        $statement->execute([$value]);

        if ($statement->rowCount() === 0) {
            throw new \Exception('Failed to delete', 400);
        }
    }

    /**
     * Deletes item(s)
     *
     * @param string $space
     * @param string $where
     * @param array $values
     * @return bool
     */
    public static function deleteWhere(
        string $space,
        string $where,
        array $values,
    ): int {
        $connection = Space::connection($space);

        $query = static::constants($space, "DELETE FROM `{{table}}` WHERE {$where};");
        $statement = $connection->prepare($query);
        $statement->execute($values);

        return $statement->rowCount();
    }
}

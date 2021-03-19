<?php

namespace JSON\Store;

use UniversalTime\Identificator\UTID;

final class Space {
    const PRIMARY_KEY = 'id';

    private static $spaces = [];

    /**
     * Asserts value is a valid space name
     *
     * @param mixed $value
     * @return string
     */
    public static function assertSpace($value): string {
        $value = Assert::hasValue($value);
        $value = Assert::string($value);
        $value = Assert::alpha($value);

        return $value;
    }

    /**
     * Asserts value is a valid table name
     *
     * @param mixed $value
     * @return string
     */
    public static function assertTable($value): string {
        $value = Assert::hasValue($value);
        $value = Assert::unquotedIdentifier($value);

        return $value;
    }

    /**
     * Assers type of the primary column
     *
     * @param string $value
     * @return string
     */
    public static function assertPrimaryType(string $value) {
        $value = trim(strtoupper($value));

        assert(
            preg_match('/^\w*INT\s+UNSIGNED$/', $value)
            ||
            preg_match('/^(?:VAR)?CHAR\(\d+\)$/', $value),
            "ID attribute must be CHAR(n) or unsigned INT, BIGINT,...; got `${value}` instead"
        );

        return $value;
    }

    /**
     * Registers a space
     *
     * @param string $space
     * @param \PDO $connection
     * @param string $table (optional)
     * @param string $primaryKey (optional)
     * @param string $docKey (optional)
     * @param string $primaryKeyType (optional)
     * @param array $columns (optional)
     * @return void
     */
    public static function register(
        string $space,
        \PDO $connection,
        string $table = null,
        string $primaryKey = null,
        string $docKey = null,
        string $primaryKeyType = null,
        array $columns = [],
    ): void {
        $space = self::assertSpace($space);
        $table = self::assertTable($table ?? $space);
        $docKey = Assert::quotedIdentifier($docKey ?? '@json');

        $primaryKey = Assert::quotedIdentifier($primaryKey ?? static::PRIMARY_KEY);
        $primaryKeyType = static::assertPrimaryType($primaryKeyType ?? UTID::type());

        self::$spaces[$space] = [
            'space' => $space,
            'connection' => $connection,
            'table' => $table,
            'primaryKey' => $primaryKey,
            'primaryKeyType' => $primaryKeyType,
            'docKey' => $docKey,
            'columns' => $columns,
        ];
    }

    private static function setupColumn(string $space, Column $column, array $tableColumn = null) {
        $connection = Space::connection($space);
        $table = Space::table($space);
        $docKey = Space::docKey($space);

        if ($tableColumn) {
            if ($column->name !== $tableColumn['Field']) {
                throw new \Exception('Unable to setup different columns', 400);
            }

            if (
                strtoupper($column->type) === strtoupper($tableColumn['Type'])
                &&
                $column->isNull() === ($tableColumn['Null'] === 'YES')
                &&
                $column->isUniqe() === ($tableColumn['Key'] === 'UNI')
            ) {
                return;
            }

            Table::dropColumn($connection, $table, $column);
        }

        Table::addColumn($connection, $table, $column, $docKey);
    }

    /**
     * Sets up the space in database
     *
     * @param string $space
     * @return void
     */
    public static function setup(string $space): void {
        $primaryKey = Space::primaryKey($space);
        $primaryKeyType = Space::primaryKeyType($space);
        $connection = Space::connection($space);
        $table = Space::table($space);
        $docKey = Space::docKey($space);
        $columns = Space::columns($space);

        if (!Space::exists($space)) {
            Table::create(
                connection: $connection,
                docKey: $docKey,
                name: $table,
                primaryKey: $primaryKey,
                primaryKeyType: $primaryKeyType,
            );
        }

        $tableColumns = Table::columns(
            connection: $connection,
            name: $table,
        );

        foreach ($columns as $column) {
            $index = array_search($column->name, array_column($tableColumns, 'Field'));

            if ($index !== false && $index >= 0) {
                self::setupColumn($space, $column, $tableColumns[$index]);
            } else {
                self::setupColumn($space, $column);
            }
        }
    }

    /**
     * Checks if space exists
     *
     * @param string $space
     * @return bool
     */
    public static function exists(string $space): bool {
        $connection = self::connection($space);
        $table = self::table($space);

        return Table::exists($connection, $table);
    }

    /**
     * Retrieves \PDO connection
     *
     * @param string $space
     * @return \PDO
     */
    public static function connection(string $space): \PDO {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['connection'];
    }

    /**
     * Retrieves primary key
     *
     * @param string $space
     * @return string
     */
    public static function primaryKey(string $space): string {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['primaryKey'];
    }

    /**
     * Retrieves primary key type
     *
     * @param string $space
     * @return string
     */
    public static function primaryKeyType(string $space): string {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['primaryKeyType'];
    }

    /**
     * Retrieves table name
     *
     * @param string $space
     * @return string
     */
    public static function table(string $space): string {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['table'];
    }

    /**
     * Retrieves document key
     *
     * @param string $space
     * @return string
     */
    public static function docKey(string $space): string {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['docKey'];
    }

    /**
     * Retrieves list of columns
     *
     * @param string $space
     * @return array<Column>
     */
    public static function columns(string $space): array {
        \assert(isset(self::$spaces[$space]), "Space `{$space}` is not registered");

        return self::$spaces[$space]['columns'];
    }
}
